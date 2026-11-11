<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
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
            'aktivitas_terbaru' => $aktivitas,
        ]);
    }
}
