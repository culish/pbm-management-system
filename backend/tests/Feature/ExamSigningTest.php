<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\ExamSubmission;
use App\Models\SignatureAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SoalFixtures;
use Tests\TestCase;

class ExamSigningTest extends TestCase
{
    use RefreshDatabase, SoalFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSoal();
    }

    public function test_upload_without_authorization_is_kept_but_not_signed(): void
    {
        $response = $this->upload()->assertCreated();

        $response->assertJsonPath('data.status', ExamSubmission::STATUS_WAITING);
        $this->assertStringContainsString('Belum ada otorisasi', $response->json('data.alasan'));
        $this->assertNull(ExamSubmission::first()->signed_document_id);
        $this->assertSame(1, Document::count());
        $this->assertTrue(AuditLog::where('action', 'soal.signing_blocked')->exists());
    }

    public function test_authorized_upload_is_signed_at_marker_and_original_is_untouched(): void
    {
        $this->grantAuthorization();
        $file = $this->pdfUpload();
        $originalBytes = $file->getContent();

        $response = $this->upload($file)->assertCreated();
        $response->assertJsonPath('data.status', ExamSubmission::STATUS_SIGNED)
            ->assertJsonPath('data.penempatan', 'penanda');

        $submission = ExamSubmission::with('originalDocument', 'signedDocument')->first();
        $disk = Storage::disk('local');

        $this->assertSame($originalBytes, $disk->get($submission->originalDocument->disk_path));
        $this->assertSame(hash('sha256', $originalBytes), $submission->originalDocument->sha256);

        $final = $disk->get($submission->signedDocument->disk_path);
        $this->assertStringStartsWith('%PDF-', $final);
        $this->assertSame(hash('sha256', $final), $submission->signedDocument->sha256);
        $this->assertNotNull($submission->authorization_id);

        // Blok tanda tangan berada di halaman yang memuat penanda (halaman 2), bukan di halaman tambahan.
        $text = $this->pdfText($final, page: 2);
        $this->assertStringContainsString('Dr. Siti Rahmawati', $text);
        $this->assertStringContainsString($submission->verification_code, $text);
        $this->assertSame(2, $this->pdfPages($final));

        $this->getJson('/api/verifikasi/'.$submission->verification_code)
            ->assertOk()
            ->assertJsonPath('data.sha256', $submission->signedDocument->sha256)
            ->assertJsonPath('data.penanda_tangan', 'Dr. Siti Rahmawati, M.T.');
    }

    public function test_document_without_marker_gets_an_approval_sheet(): void
    {
        $this->grantAuthorization();

        $this->upload($this->pdfUpload(withMarker: false))->assertCreated()
            ->assertJsonPath('data.penempatan', 'lembar_pengesahan');

        $final = Storage::disk('local')->get(ExamSubmission::first()->signedDocument->disk_path);
        $this->assertSame(3, $this->pdfPages($final));
        $sheet = $this->pdfText($final, page: 3);
        $this->assertStringContainsString('LEMBAR PENGESAHAN SOAL', $sheet);
        $this->assertStringContainsString('Termodinamika', $sheet);
        $this->assertStringNotContainsString('TCPDF', $sheet);
    }

    public function test_waiting_submission_is_signed_after_kps_authorizes_and_reprocess_is_requested(): void
    {
        $id = $this->upload()->json('data.id');
        $grant = $this->grantAuthorization();
        $this->assertSame(1, $grant['soal_menunggu']);

        $this->actingAs($this->dosen)->postJson("/api/soal/{$id}/proses")
            ->assertOk()->assertJsonPath('data.status', ExamSubmission::STATUS_SIGNED);

        $this->actingAs($this->dosen)->postJson("/api/soal/{$id}/proses")->assertStatus(422);
    }

    public function test_revoked_authorization_blocks_signing(): void
    {
        $grant = $this->grantAuthorization();

        $this->actingAs($this->admin)->postJson("/api/otorisasi-ttd/{$grant['data']['id']}/cabut", ['alasan' => 'KPS berganti jabatan'])
            ->assertOk()->assertJsonPath('data.status', 'dicabut');

        $response = $this->upload()->assertCreated();
        $response->assertJsonPath('data.status', ExamSubmission::STATUS_WAITING);
        $this->assertStringContainsString('dicabut', $response->json('data.alasan'));
    }

    public function test_expired_authorization_blocks_signing(): void
    {
        $this->grantAuthorization(['valid_until' => now()->addDays(3)->toDateString()]);
        $this->travel(5)->days();

        $response = $this->upload()->assertCreated();
        $response->assertJsonPath('data.status', ExamSubmission::STATUS_WAITING);
        $this->assertStringContainsString('kedaluwarsa', $response->json('data.alasan'));
    }

    public function test_authorization_only_covers_selected_exam_types(): void
    {
        $this->grantAuthorization(['exam_types' => ['uts']]);

        $this->upload(type: 'uts')->assertJsonPath('data.status', ExamSubmission::STATUS_SIGNED);
        $response = $this->upload(type: 'uas')->assertJsonPath('data.status', ExamSubmission::STATUS_WAITING);
        $this->assertStringContainsString('UAS', $response->json('data.alasan'));
    }

    public function test_inactive_kps_account_blocks_signing(): void
    {
        $this->grantAuthorization();
        $this->kps->update(['is_active' => false]);

        $this->upload()->assertJsonPath('data.status', ExamSubmission::STATUS_WAITING);
    }

    public function test_tampered_signature_image_blocks_signing(): void
    {
        $this->grantAuthorization();
        Storage::disk('local')->put(SignatureAuthorization::first()->image_path, 'gambar lain');

        $response = $this->upload()->assertJsonPath('data.status', ExamSubmission::STATUS_WAITING);
        $this->assertStringContainsString('tidak sama', $response->json('data.alasan'));
    }

    public function test_admin_cannot_grant_authorization_on_behalf_of_kps(): void
    {
        $this->actingAs($this->admin)->post('/api/otorisasi-ttd', [
            'signer_name' => 'Siapa Saja',
            'signer_title' => 'Ketua Program Studi',
            'exam_types' => ['uts'],
            'valid_until' => now()->addMonth()->toDateString(),
            'gambar' => $this->signatureImage(),
            'persetujuan' => '1',
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertSame(0, SignatureAuthorization::count());
    }

    public function test_authorization_requires_explicit_consent(): void
    {
        $this->actingAs($this->kps)->post('/api/otorisasi-ttd', [
            'signer_name' => 'Dr. Siti Rahmawati, M.T.',
            'signer_title' => 'Ketua Program Studi',
            'exam_types' => ['uts'],
            'valid_until' => now()->addMonth()->toDateString(),
            'gambar' => $this->signatureImage(),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('persetujuan');
    }

    public function test_new_authorization_replaces_previous_one(): void
    {
        $this->grantAuthorization();
        $this->grantAuthorization();

        $this->assertSame(1, SignatureAuthorization::where('status', 'aktif')->count());
        $this->assertSame(1, SignatureAuthorization::where('status', 'diganti')->count());
    }

    public function test_only_assigned_lecturer_can_upload(): void
    {
        $this->upload(as: $this->dosenLain)->assertStatus(422)->assertJsonValidationErrors('mata_kuliah_id');
    }

    public function test_file_content_must_match_pdf_or_docx(): void
    {
        $fake = UploadedFile::fake()->createWithContent('soal.pdf', 'ini hanya teks biasa');

        $this->upload($fake)->assertStatus(422)->assertJsonValidationErrors('berkas');
        $this->upload(UploadedFile::fake()->create('soal.exe', 10))->assertStatus(422);
    }

    public function test_submissions_are_scoped_by_role(): void
    {
        $id = $this->upload()->json('data.id');

        $this->actingAs($this->dosenLain)->getJson("/api/soal/{$id}")->assertNotFound();
        $this->actingAs($this->dosenLain)->getJson("/api/soal/{$id}/berkas/asli")->assertNotFound();
        $this->actingAs($this->dosenLain)->getJson('/api/soal')->assertJsonPath('meta.total', 0);

        $this->actingAs($this->kps)->getJson("/api/soal/{$id}")->assertOk();
        $this->actingAs($this->admin)->getJson('/api/soal')->assertJsonPath('meta.total', 1);

        $laboran = $this->makeUser('laboran', 'lab@test.local', $this->prodi->id);
        $this->actingAs($laboran)->getJson('/api/soal')->assertForbidden();
    }

    public function test_versions_increment_per_course_semester_and_exam_type(): void
    {
        $this->upload();
        $this->upload();
        $this->upload(type: 'uas');

        $this->assertEquals([1, 2], ExamSubmission::where('exam_type', 'uts')->orderBy('version')->pluck('version')->all());
        $this->assertEquals([1], ExamSubmission::where('exam_type', 'uas')->pluck('version')->all());

        $first = ExamSubmission::where('exam_type', 'uts')->where('version', 1)->first();
        $this->grantAuthorization();
        $this->actingAs($this->dosen)->postJson("/api/soal/{$first->id}/proses")->assertStatus(422);
    }

    public function test_docx_is_converted_to_pdf_before_signing(): void
    {
        if (! Process::run(['which', config('pbm.tools.soffice')])->successful()) {
            $this->markTestSkipped('LibreOffice tidak terpasang.');
        }

        $this->grantAuthorization();
        $response = $this->upload($this->docxUpload(), 'uas')->assertCreated();
        $response->assertJsonPath('data.status', ExamSubmission::STATUS_SIGNED)
            ->assertJsonPath('data.penempatan', 'penanda');

        $submission = ExamSubmission::with('originalDocument')->first();
        $this->assertStringEndsWith('wordprocessingml.document', $submission->originalDocument->mime);
    }

    public function test_pdf_with_object_streams_is_normalized_then_signed(): void
    {
        $this->requireQpdf();
        $this->grantAuthorization();

        $file = $this->qpdfVariant(['--object-streams=generate', '--min-version=1.5']);

        $this->upload($file)->assertCreated()
            ->assertJsonPath('data.status', ExamSubmission::STATUS_SIGNED)
            ->assertJsonPath('data.penempatan', 'penanda');
    }

    public function test_password_protected_pdf_fails_with_clear_message(): void
    {
        $this->requireQpdf();
        $this->grantAuthorization();

        $file = $this->qpdfVariant(['--encrypt', 'buka', 'pemilik', '256', '--']);

        $response = $this->upload($file)->assertCreated()
            ->assertJsonPath('data.status', ExamSubmission::STATUS_FAILED);
        $this->assertStringContainsString('kata sandi', $response->json('data.alasan'));
        $this->assertNull(ExamSubmission::first()->signed_document_id);
    }

    public function test_dashboard_reports_latest_exam_status(): void
    {
        $this->upload();
        $this->grantAuthorization();
        $this->upload();

        $this->actingAs($this->kps)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('soal_semester_aktif.per_jenis.uts.disahkan', 1)
            ->assertJsonPath('soal_semester_aktif.per_jenis.uts.menunggu_otorisasi', 0)
            ->assertJsonPath('soal_semester_aktif.mata_kuliah_aktif', 1);
    }

    private function requireQpdf(): void
    {
        if (! Process::run(['which', config('pbm.tools.qpdf')])->successful()) {
            $this->markTestSkipped('qpdf tidak terpasang.');
        }
    }

    private function qpdfVariant(array $options): UploadedFile
    {
        $in = tempnam(sys_get_temp_dir(), 'pdf');
        $out = $in.'-out.pdf';
        file_put_contents($in, $this->pdfUpload()->getContent());
        Process::run(['qpdf', ...$options, $in, $out])->throw();

        return UploadedFile::fake()->createWithContent('soal.pdf', file_get_contents($out));
    }

    private function pdfText(string $bytes, ?int $page = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $bytes);
        $range = $page ? ['-f', (string) $page, '-l', (string) $page] : [];

        return Process::run(['pdftotext', ...$range, $path, '-'])->output();
    }

    private function pdfPages(string $bytes): int
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $bytes);
        preg_match('/Pages:\s+(\d+)/', Process::run(['pdfinfo', $path])->output(), $m);

        return (int) ($m[1] ?? 0);
    }
}
