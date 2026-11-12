<?php

namespace App\Services\Soal;

use RuntimeException;

/** Berkas tidak dapat dikonversi atau dibubuhi tanda tangan. Pesan aman ditampilkan ke pengguna. */
class DocumentProcessingFailed extends RuntimeException
{
}
