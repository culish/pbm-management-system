<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSubmission extends Model
{
    public const STATUS_SIGNED = 'disahkan';
    public const STATUS_WAITING = 'menunggu_otorisasi';
    public const STATUS_FAILED = 'gagal_diproses';

    public const STATUSES = [self::STATUS_SIGNED, self::STATUS_WAITING, self::STATUS_FAILED];

    public const EXAM_TYPES = ['uts', 'uas'];

    protected $fillable = [
        'mata_kuliah_id', 'semester_id', 'exam_type', 'version', 'lecturer_id',
        'original_document_id', 'signed_document_id', 'authorization_id',
        'status', 'status_reason', 'placement', 'verification_code', 'signed_at',
    ];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function originalDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'original_document_id');
    }

    public function signedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'signed_document_id');
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(SignatureAuthorization::class, 'authorization_id');
    }

    /**
     * Membatasi daftar sesuai hak akses: administrator dokumen melihat semua,
     * KPS melihat program studinya, dosen melihat unggahannya sendiri.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $permissions = $user->permissionKeys();

        if (in_array('dokumen.manage', $permissions, true)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user, $permissions) {
            $q->where('lecturer_id', $user->id);

            if (in_array('soal.authorize', $permissions, true) && $user->program_studi_id) {
                $q->orWhereHas('mataKuliah', fn (Builder $mk) => $mk->where('program_studi_id', $user->program_studi_id));
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    public function canBeReprocessedBy(User $user): bool
    {
        if ($this->status === self::STATUS_SIGNED) {
            return false;
        }

        $permissions = $user->permissionKeys();

        return $this->lecturer_id === $user->id
            || in_array('dokumen.manage', $permissions, true)
            || (in_array('soal.authorize', $permissions, true)
                && $user->program_studi_id === $this->mataKuliah->program_studi_id);
    }
}
