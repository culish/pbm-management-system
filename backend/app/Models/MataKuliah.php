<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MataKuliah extends Model
{
    protected $table = 'mata_kuliah';

    protected $fillable = ['program_studi_id', 'kode', 'nama', 'sks', 'semester_ke', 'jenis'];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function pengampu(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mata_kuliah_pengampu')
            ->withPivot('semester_id')
            ->withTimestamps();
    }
}
