<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamSubmissionRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\ExamSubmission;
use App\Models\MataKuliah;
use App\Models\Semester;
use App\Models\SignatureAuthorization;
use App\Services\Soal\DocumentInspector;
use App\Services\Soal\ExamSigningService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamSubmissionController extends Controller
{
    private const MIME = [
        DocumentInspector::PDF => 'application/pdf',
        DocumentInspector::DOCX => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $semesters = Semester::orderByDesc('tahun_ajaran')->orderByDesc('term')->get();
        $semesterId = (int) ($request->integer('semester_id') ?: $semesters->firstWhere('is_active', true)?->id);

        $courses = MataKuliah::query()
            ->whereHas('pengampu', fn ($q) => $q->where('users.id', $user->id)->where('mata_kuliah_pengampu.semester_id', $semesterId))
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'program_studi_id']);

        $covered = SignatureAuthorization::query()
            ->active()
            ->whereIn('program_studi_id', $courses->pluck('program_studi_id')->unique())
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>', now())
            ->get(['program_studi_id', 'exam_types'])
            ->groupBy('program_studi_id')
            ->map(fn ($rows) => $rows->pluck('exam_types')->flatten()->unique()->values()->all());

        return response()->json([
            'semester_id' => $semesterId ?: null,
            'semesters' => $semesters->map(fn (Semester $s) => [
                'id' => $s->id, 'label' => $s->label(), 'is_active' => $s->is_active,
            ]),
            'mata_kuliah' => $courses->map(fn (MataKuliah $mk) => [
                'id' => $mk->id,
                'kode' => $mk->kode,
                'nama' => $mk->nama,
                'otorisasi' => $covered->get($mk->program_studi_id, []),
            ]),
            'max_upload_mb' => (int) (config('pbm.soal.max_upload_kb') / 1024),
            'penanda' => config('pbm.soal.signature_marker'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ExamSubmission::STATUSES)],
            'exam_type' => ['nullable', Rule::in(ExamSubmission::EXAM_TYPES)],
            'semester_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $page = ExamSubmission::query()
            ->visibleTo($request->user())
            ->with(['mataKuliah:id,kode,nama', 'semester', 'lecturer:id,name'])
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['exam_type'] ?? null, fn ($q, $v) => $q->where('exam_type', $v))
            ->when($validated['semester_id'] ?? null, fn ($q, $v) => $q->where('semester_id', $v))
            ->when($validated['q'] ?? null, function ($q, $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->whereHas('mataKuliah', fn ($mk) => $mk->whereRaw('lower(nama) like ?', [$like])->orWhereRaw('lower(kode) like ?', [$like]));
            })
            ->latest('id')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'data' => collect($page->items())->map(fn (ExamSubmission $s) => $this->present($s)),
            'meta' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
            ],
            'ringkasan' => ExamSubmission::query()->visibleTo($request->user())
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function store(StoreExamSubmissionRequest $request, DocumentInspector $inspector, ExamSigningService $signing): JsonResponse
    {
        $user = $request->user();
        $mataKuliah = MataKuliah::findOrFail($request->integer('mata_kuliah_id'));
        $semesterId = $request->integer('semester_id');

        $isPengampu = $mataKuliah->pengampu()
            ->where('users.id', $user->id)
            ->where('mata_kuliah_pengampu.semester_id', $semesterId)
            ->exists();

        if (! $isPengampu) {
            throw ValidationException::withMessages([
                'mata_kuliah_id' => 'Anda tidak terdaftar sebagai pengampu mata kuliah ini pada semester yang dipilih.',
            ]);
        }

        $file = $request->file('berkas');
        $type = $inspector->detect($file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension());

        if (! $type || $type !== $extension) {
            throw ValidationException::withMessages([
                'berkas' => 'Isi berkas bukan PDF atau DOCX yang valid. Simpan ulang dokumen dari aplikasi pengolah kata lalu unggah kembali.',
            ]);
        }

        $bytes = file_get_contents($file->getRealPath());
        $path = 'documents/soal/'.$semesterId.'/'.Str::uuid().'.'.$type;
        Storage::disk('local')->put($path, $bytes);

        $submission = DB::transaction(function () use ($user, $mataKuliah, $semesterId, $request, $file, $type, $path, $bytes) {
            // Mengunci baris mata kuliah agar nomor versi tidak bentrok saat dua unggahan bersamaan.
            MataKuliah::whereKey($mataKuliah->id)->lockForUpdate()->first();

            $version = 1 + (int) ExamSubmission::where('mata_kuliah_id', $mataKuliah->id)
                ->where('semester_id', $semesterId)
                ->where('exam_type', $request->input('exam_type'))
                ->max('version');

            $original = Document::create([
                'title' => 'Soal '.strtoupper($request->input('exam_type')).' '.$mataKuliah->nama,
                'category' => 'soal_asli',
                'status' => 'diajukan',
                'owner_id' => $user->id,
                'mata_kuliah_id' => $mataKuliah->id,
                'disk_path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => self::MIME[$type],
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'version' => $version,
            ]);

            $submission = ExamSubmission::create([
                'mata_kuliah_id' => $mataKuliah->id,
                'semester_id' => $semesterId,
                'exam_type' => $request->input('exam_type'),
                'version' => $version,
                'lecturer_id' => $user->id,
                'original_document_id' => $original->id,
                'status' => ExamSubmission::STATUS_WAITING,
            ]);

            Audit::record('soal.submitted', $submission, [
                'sha256' => $original->sha256,
                'nama_berkas' => $original->original_name,
                'jenis_berkas' => $type,
                'version' => $version,
            ]);

            return $submission;
        });

        $submission = $signing->process($submission);

        return response()->json(['data' => $this->present($submission->load('mataKuliah', 'semester', 'lecturer'))], 201);
    }

    public function show(Request $request, ExamSubmission $soal): JsonResponse
    {
        abort_unless($soal->isVisibleTo($request->user()), 404);

        $soal->load('mataKuliah.programStudi', 'semester', 'lecturer', 'originalDocument', 'signedDocument', 'authorization');

        $history = AuditLog::with('user:id,name')
            ->where('subject_type', $soal->getMorphClass())
            ->where('subject_id', $soal->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AuditLog $log) => [
                'aksi' => $log->action,
                'pengguna' => $log->user?->name,
                'waktu' => $log->created_at?->toIso8601String(),
                'keterangan' => $log->metadata['reason'] ?? null,
            ]);

        $versions = ExamSubmission::query()
            ->visibleTo($request->user())
            ->where('mata_kuliah_id', $soal->mata_kuliah_id)
            ->where('semester_id', $soal->semester_id)
            ->where('exam_type', $soal->exam_type)
            ->with('lecturer:id,name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (ExamSubmission $s) => [
                'id' => $s->id,
                'versi' => $s->version,
                'status' => $s->status,
                'pengunggah' => $s->lecturer?->name,
                'dibuat' => $s->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $this->present($soal) + [
                'berkas_asli' => $this->presentDocument($soal->originalDocument),
                'berkas_final' => $soal->signedDocument ? $this->presentDocument($soal->signedDocument) : null,
                'otorisasi' => $soal->authorization ? [
                    'id' => $soal->authorization->id,
                    'penanda_tangan' => $soal->authorization->signer_name,
                    'jabatan' => $soal->authorization->signer_title,
                    'status' => $soal->authorization->effectiveStatus(),
                ] : null,
                'bisa_diproses_ulang' => $soal->canBeReprocessedBy($request->user()),
                'riwayat' => $history,
                'versi_lain' => $versions,
            ],
        ]);
    }

    public function reprocess(Request $request, ExamSubmission $soal, ExamSigningService $signing): JsonResponse
    {
        abort_unless($soal->isVisibleTo($request->user()), 404);

        if (! $soal->canBeReprocessedBy($request->user())) {
            return response()->json(['message' => $soal->status === ExamSubmission::STATUS_SIGNED
                ? 'Soal ini sudah disahkan. Unggah versi baru bila ada perubahan.'
                : 'Anda tidak dapat memproses ulang soal ini.'], 422);
        }

        $isLatest = ! ExamSubmission::where('mata_kuliah_id', $soal->mata_kuliah_id)
            ->where('semester_id', $soal->semester_id)
            ->where('exam_type', $soal->exam_type)
            ->where('version', '>', $soal->version)
            ->exists();

        if (! $isLatest) {
            return response()->json(['message' => 'Sudah ada versi yang lebih baru. Hanya versi terbaru yang dapat diproses.'], 422);
        }

        Audit::record('soal.reprocess_requested', $soal);
        $soal = $signing->process($soal);

        return response()->json(['data' => $this->present($soal->load('mataKuliah', 'semester', 'lecturer'))]);
    }

    public function file(Request $request, ExamSubmission $soal, string $jenis): StreamedResponse
    {
        abort_unless($soal->isVisibleTo($request->user()), 404);

        $document = match ($jenis) {
            'asli' => $soal->originalDocument,
            'final' => $soal->signedDocument,
            default => null,
        };
        abort_unless($document, 404);

        Audit::record('soal.file_accessed', $soal, ['berkas' => $jenis, 'document_id' => $document->id]);

        $disposition = $request->boolean('unduh') || $document->mime !== 'application/pdf' ? 'attachment' : 'inline';

        return Storage::disk('local')->response($document->disk_path, $document->original_name, [
            'Content-Type' => $document->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $disposition);
    }

    private function present(ExamSubmission $s): array
    {
        return [
            'id' => $s->id,
            'mata_kuliah' => $s->mataKuliah ? ['id' => $s->mataKuliah->id, 'kode' => $s->mataKuliah->kode, 'nama' => $s->mataKuliah->nama] : null,
            'semester' => $s->semester?->label(),
            'jenis_ujian' => $s->exam_type,
            'versi' => $s->version,
            'dosen' => $s->lecturer?->name,
            'status' => $s->status,
            'alasan' => $s->status_reason,
            'penempatan' => $s->placement,
            'kode_verifikasi' => $s->verification_code,
            'disahkan_pada' => $s->signed_at?->toIso8601String(),
            'dibuat' => $s->created_at?->toIso8601String(),
        ];
    }

    private function presentDocument(Document $d): array
    {
        return [
            'nama' => $d->original_name,
            'mime' => $d->mime,
            'ukuran' => $d->size,
            'sha256' => $d->sha256,
            'versi' => $d->version,
            'diunggah' => $d->created_at?->toIso8601String(),
        ];
    }
}
