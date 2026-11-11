<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'diajukan', 'disahkan', 'ditolak', 'arsip'];

    protected $fillable = [
        'title', 'category', 'status', 'owner_id', 'mata_kuliah_id',
        'disk_path', 'original_name', 'mime', 'size', 'sha256', 'version',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }
}
