<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\ExamSubmission;
use App\Models\MataKuliah;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $semester = Semester::where('is_active', true)->first();

        $perSemester = MataKuliah::query()
            ->selectRaw('semester_ke, count(*) as total')
            ->groupBy('semester_ke')
            ->orderBy('semester_ke')
            ->get()
            ->map(fn ($row) => ['semester_ke' => (int) $row->semester_ke, 'total' => (int) $row->total]);

        $perStatus = Document::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $aktivitas = AuditLog::with('user:id,name')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'aksi' => $log->action,
                'pengguna' => $log->user?->name,
                'waktu' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'stats' => [
                'mata_kuliah' => MataKuliah::count(),
                'dosen' => User::whereHas('role', fn ($q) => $q->where('key', 'dosen'))->where('is_active', true)->count(),
                'dokumen' => Document::count(),
                'semester_aktif' => $semester ? $semester->label() : null,
            ],
            'mata_kuliah_per_semester' => $perSemester,
            'dokumen_per_status' => collect(Document::STATUSES)->map(fn ($s) => [
                'status' => $s,
                'total' => (int) ($perStatus[$s] ?? 0),
            ])->values(),
            'soal_semester_aktif' => $this->examSummary($semester),
            'aktivitas_terbaru' => $aktivitas,
        ]);
    }

    /**
     * Status soal UTS/UAS semester aktif. Hanya versi terbaru tiap mata kuliah dan jenis ujian yang dihitung.
     */
    private function examSummary(?Semester $semester): array
    {
        $counts = collect(ExamSubmission::EXAM_TYPES)->mapWithKeys(fn ($t) => [$t => array_fill_keys(ExamSubmission::STATUSES, 0)])->all();

        if (! $semester) {
            return ['per_jenis' => $counts, 'mata_kuliah_aktif' => 0];
        }

        $latest = ExamSubmission::query()
            ->where('semester_id', $semester->id)
            ->whereRaw('version = (select max(e2.version) from exam_submissions e2 where e2.mata_kuliah_id = exam_submissions.mata_kuliah_id and e2.semester_id = exam_submissions.semester_id and e2.exam_type = exam_submissions.exam_type)')
            ->get(['exam_type', 'status']);

        foreach ($latest as $row) {
            $counts[$row->exam_type][$row->status]++;
        }

        $aktif = MataKuliah::whereHas('pengampu', fn ($q) => $q->where('mata_kuliah_pengampu.semester_id', $semester->id))->count();

        return ['per_jenis' => $counts, 'mata_kuliah_aktif' => $aktif];
    }
}
