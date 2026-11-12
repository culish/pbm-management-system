<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint publik untuk memeriksa kode verifikasi yang tercetak pada soal yang disahkan.
 * Hanya mengembalikan metadata, tidak pernah isi soal.
 */
class VerificationController extends Controller
{
    public function show(string $kode): JsonResponse
    {
        $submission = ExamSubmission::query()
            ->where('verification_code', strtoupper($kode))
            ->where('status', ExamSubmission::STATUS_SIGNED)
            ->with(['mataKuliah.programStudi', 'semester', 'signedDocument', 'authorization'])
            ->first();

        if (! $submission) {
            return response()->json(['message' => 'Kode verifikasi tidak ditemukan.'], 404);
        }

        $auth = $submission->authorization;

        return response()->json([
            'data' => [
                'kode' => $submission->verification_code,
                'dokumen' => 'Soal '.strtoupper($submission->exam_type).' versi '.$submission->version,
                'mata_kuliah' => $submission->mataKuliah->kode.' '.$submission->mataKuliah->nama,
                'prodi' => $submission->mataKuliah->programStudi?->nama,
                'semester' => $submission->semester->label(),
                'penanda_tangan' => $auth?->signer_name,
                'jabatan' => $auth?->signer_title,
                'disahkan_pada' => $submission->signed_at?->toIso8601String(),
                'sha256' => $submission->signedDocument?->sha256,
                'otorisasi_dicabut_pada' => $auth?->revoked_at && $auth->status === 'dicabut' ? $auth->revoked_at->toIso8601String() : null,
            ],
        ]);
    }
}
