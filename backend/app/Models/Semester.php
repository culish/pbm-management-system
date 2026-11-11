<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $fillable = ['tahun_ajaran', 'term', 'mulai', 'selesai', 'is_active'];

    protected function casts(): array
    {
        return [
            'mulai' => 'date',
            'selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function label(): string
    {
        return ucfirst($this->term).' '.$this->tahun_ajaran;
    }
}
