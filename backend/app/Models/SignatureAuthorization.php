<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Persetujuan eksplisit KPS agar gambar tanda tangannya dipakai pada soal UTS/UAS
 * program studinya, untuk jenis ujian dan rentang waktu tertentu.
 */
class SignatureAuthorization extends Model
{
    public const STATUS_ACTIVE = 'aktif';
    public const STATUS_REVOKED = 'dicabut';
    public const STATUS_REPLACED = 'diganti';

    public const CONSENT_STATEMENT = 'Saya, selaku Ketua Program Studi, menyetujui penggunaan gambar tanda tangan saya '
        .'untuk mengesahkan soal %s program studi %s yang diunggah oleh dosen pengampu melalui PBM Management System, '
        .'berlaku sampai %s atau sampai otorisasi ini dicabut.';

    protected $fillable = [
        'signer_id', 'program_studi_id', 'signer_name', 'signer_nip', 'signer_title', 'exam_types',
        'image_path', 'image_sha256', 'consent_statement', 'valid_from', 'valid_until', 'status',
        'revoked_at', 'revoked_by', 'revoke_reason', 'granted_ip',
    ];

    protected function casts(): array
    {
        return [
            'exam_types' => 'array',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('revoked_at');
    }

    /**
     * Status efektif saat ini. Kolom `status` tidak berubah ketika masa berlaku habis,
     * jadi kedaluwarsa dihitung dari `valid_until`.
     */
    public function effectiveStatus(): string
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return $this->status;
        }

        if ($this->valid_until->isPast()) {
            return 'kedaluwarsa';
        }

        return $this->valid_from->isFuture() ? 'belum_berlaku' : self::STATUS_ACTIVE;
    }

    public function imageIsIntact(): bool
    {
        $disk = Storage::disk('local');

        return $disk->exists($this->image_path)
            && hash_equals($this->image_sha256, hash('sha256', $disk->get($this->image_path)));
    }
}
