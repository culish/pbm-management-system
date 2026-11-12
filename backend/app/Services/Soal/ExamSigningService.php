<?php

namespace App\Services\Soal;

use App\Models\Document;
use App\Models\ExamSubmission;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alur pengesahan soal: cek otorisasi KPS, konversi DOCX, bubuhkan tanda tangan,
 * simpan PDF final. Berkas asli tidak pernah diubah.
 */
class ExamSigningService
{
    public function __construct(
        private AuthorizationResolver $resolver,
        private DocumentTools $tools,
        private PdfStamper $stamper,
    ) {
    }

    public function process(ExamSubmission $submission): ExamSubmission
    {
        $submission->loadMissing('mataKuliah.programStudi', 'semester', 'lecturer', 'originalDocument');

        try {
            DB::transaction(function () use ($submission) {
                // Baris otorisasi dikunci selama pemrosesan, sehingga pencabutan yang
                // berjalan bersamaan menunggu dan tidak bisa terlewat.
                $authorization = $this->resolver->resolve($submission->mataKuliah, $submission->exam_type, lock: true);
                $this->sign($submission, $authorization);
            });
        } catch (SigningBlocked $e) {
            $this->markUnsigned($submission, ExamSubmission::STATUS_WAITING, $e->getMessage(), 'soal.signing_blocked');
        } catch (DocumentProcessingFailed $e) {
            $this->markUnsigned($submission, ExamSubmission::STATUS_FAILED, $e->getMessage(), 'soal.signing_failed');
        } catch (Throwable $e) {
            Log::error('Pemrosesan soal gagal', ['submission' => $submission->id, 'error' => $e->getMessage()]);
            $this->markUnsigned(
                $submission,
                ExamSubmission::STATUS_FAILED,
                'Dokumen tidak dapat diproses. Periksa apakah PDF rusak atau terkunci kata sandi, lalu unggah ulang.',
                'soal.signing_failed',
            );
        }

        return $submission->refresh();
    }

    private function sign(ExamSubmission $submission, $authorization): void
    {
        $disk = Storage::disk('local');
        $original = $submission->originalDocument;
        $work = $this->tools->tempDir();

        try {
            $source = $work.'/source';
            File::put($source, $disk->get($original->disk_path));

            $pdfPath = $original->mime === 'application/pdf' ? $source : $this->tools->docxToPdf($source);

            $marker = $this->tools->findMarker($pdfPath, config('pbm.soal.signature_marker'));

            $imagePath = $work.'/ttd.png';
            File::put($imagePath, $disk->get($authorization->image_path));

            $code = $this->newVerificationCode();
            $signedAt = now();
            $tz = config('pbm.display_timezone');
            $mk = $submission->mataKuliah;

            $output = $work.'/final.pdf';
            $this->stamper->stamp($pdfPath, $marker, [
                'signer_name' => $authorization->signer_name,
                'signer_nip' => $authorization->signer_nip,
                'signer_title' => $authorization->signer_title,
                'image_path' => $imagePath,
                'signed_at' => $signedAt->copy()->timezone($tz)->translatedFormat('j F Y H:i').' WIB',
                'code' => $code,
                'lembar' => [
                    'Dokumen' => 'Soal '.strtoupper($submission->exam_type).' versi '.$submission->version,
                    'Mata kuliah' => "{$mk->kode} {$mk->nama}",
                    'Program studi' => $mk->programStudi?->nama ?? '-',
                    'Semester' => $submission->semester->label(),
                    'Dosen pengunggah' => $submission->lecturer->name,
                    'Berkas asli' => $original->original_name,
                    'SHA-256 berkas asli' => $original->sha256,
                ],
            ], $output);

            if (is_file($pdfPath) && $pdfPath !== $source) {
                File::delete($pdfPath);
            }

            $bytes = File::get($output);
            $path = 'documents/soal/'.$submission->semester_id.'/'.Str::uuid().'.pdf';
            $disk->put($path, $bytes);

            $signed = Document::create([
                'title' => 'Soal '.strtoupper($submission->exam_type).' '.$mk->nama.' (disahkan)',
                'category' => 'soal_final',
                'status' => 'disahkan',
                'owner_id' => $submission->lecturer_id,
                'mata_kuliah_id' => $mk->id,
                'disk_path' => $path,
                'original_name' => Str::slug("soal-{$submission->exam_type}-{$mk->kode}-v{$submission->version}").'-disahkan.pdf',
                'mime' => 'application/pdf',
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'version' => $submission->version,
            ]);

            $submission->update([
                'status' => ExamSubmission::STATUS_SIGNED,
                'status_reason' => null,
                'signed_document_id' => $signed->id,
                'authorization_id' => $authorization->id,
                'placement' => $marker ? 'penanda' : 'lembar_pengesahan',
                'verification_code' => $code,
                'signed_at' => $signedAt,
            ]);

            Audit::record('soal.signed', $submission, [
                'authorization_id' => $authorization->id,
                'signer' => $authorization->signer_name,
                'original_sha256' => $original->sha256,
                'signed_sha256' => $signed->sha256,
                'placement' => $submission->placement,
                'version' => $submission->version,
            ]);
        } finally {
            File::deleteDirectory($work);
        }
    }

    private function markUnsigned(ExamSubmission $submission, string $status, string $reason, string $action): void
    {
        $submission->update(['status' => $status, 'status_reason' => $reason]);
        Audit::record($action, $submission, ['reason' => $reason, 'version' => $submission->version]);
    }

    private function newVerificationCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (ExamSubmission::where('verification_code', $code)->exists());

        return $code;
    }
}
