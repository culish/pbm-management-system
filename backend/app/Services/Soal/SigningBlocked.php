<?php

namespace App\Services\Soal;

use RuntimeException;

/** Tanda tangan tidak boleh dipasang karena otorisasi KPS tidak memenuhi syarat. */
class SigningBlocked extends RuntimeException
{
}
