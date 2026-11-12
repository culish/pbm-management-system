<?php

namespace App\Services\Soal;

use ZipArchive;

/**
 * Menentukan jenis berkas dari isinya, bukan dari ekstensi atau MIME yang dikirim browser.
 */
class DocumentInspector
{
    public const PDF = 'pdf';
    public const DOCX = 'docx';

    public function detect(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return null;
        }
        $head = (string) fread($handle, 1024);
        fclose($handle);

        if (str_contains($head, '%PDF-')) {
            return self::PDF;
        }

        if (str_starts_with($head, "PK\x03\x04") && $this->isDocx($path)) {
            return self::DOCX;
        }

        return null;
    }

    private function isDocx(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $ok = $zip->locateName('[Content_Types].xml') !== false
            && $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $ok;
    }
}
