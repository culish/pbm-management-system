<?php

namespace App\Services\Soal;

use App\Models\MataKuliah;
use App\Models\SignatureAuthorization;

/**
 * Mencari otorisasi KPS yang sah untuk satu mata kuliah dan jenis ujian.
 * Bila tidak ada, melempar SigningBlocked dengan alasan yang spesifik.
 */
class AuthorizationResolver
{
    public function resolve(MataKuliah $mataKuliah, string $examType, bool $lock = false): SignatureAuthorization
    {
        $query = SignatureAuthorization::query()
            ->active()
            ->where('program_studi_id', $mataKuliah->program_studi_id)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>', now())
            ->whereJsonContains('exam_types', $examType)
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $authorization = $query->first();

        if (! $authorization) {
            throw new SigningBlocked($this->explainMissing($mataKuliah, $examType));
        }

        $signer = $authorization->signer;
        if (! $signer || ! $signer->is_active || ! $signer->hasPermission('soal.authorize')
            || $signer->program_studi_id !== $authorization->program_studi_id) {
            throw new SigningBlocked('Akun KPS pemberi otorisasi sudah tidak aktif atau tidak lagi menjabat KPS program studi ini. Otorisasi baru diperlukan.');
        }

        if (! $authorization->imageIsIntact()) {
            throw new SigningBlocked('Berkas tanda tangan tidak sama dengan yang disetujui KPS. Pemrosesan dihentikan; hubungi administrator.');
        }

        return $authorization;
    }

    private function explainMissing(MataKuliah $mataKuliah, string $examType): string
    {
        $label = strtoupper($examType);
        $prodi = $mataKuliah->programStudi?->nama ?? 'mata kuliah ini';

        $latest = SignatureAuthorization::query()
            ->where('program_studi_id', $mataKuliah->program_studi_id)
            ->latest('id')
            ->first();

        if (! $latest) {
            return "Belum ada otorisasi tanda tangan dari KPS {$prodi}. Soal disimpan dan akan diproses setelah KPS memberi otorisasi.";
        }

        $tz = config('pbm.display_timezone');

        return match (true) {
            $latest->status === SignatureAuthorization::STATUS_REVOKED => 'Otorisasi tanda tangan KPS dicabut pada '
                .$latest->revoked_at->timezone($tz)->translatedFormat('j F Y H:i').'. Soal disimpan sampai ada otorisasi baru.',
            $latest->status === SignatureAuthorization::STATUS_ACTIVE && $latest->valid_until->isPast() => 'Otorisasi tanda tangan KPS kedaluwarsa pada '
                .$latest->valid_until->timezone($tz)->translatedFormat('j F Y').'. Soal disimpan sampai KPS memperbarui otorisasi.',
            $latest->status === SignatureAuthorization::STATUS_ACTIVE && $latest->valid_from->isFuture() => 'Otorisasi tanda tangan KPS baru berlaku mulai '
                .$latest->valid_from->timezone($tz)->translatedFormat('j F Y').'.',
            ! in_array($examType, $latest->exam_types, true) => "Otorisasi KPS saat ini tidak mencakup soal {$label}.",
            default => "Tidak ada otorisasi tanda tangan KPS yang berlaku untuk soal {$label}.",
        };
    }
}
