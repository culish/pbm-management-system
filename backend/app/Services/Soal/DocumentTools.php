<?php

namespace App\Services\Soal;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Pembungkus alat baris perintah: LibreOffice (DOCX ke PDF) dan Poppler
 * (mencari koordinat penanda), dan qpdf (menormalkan PDF yang tidak terbaca FPDI).
 */
class DocumentTools
{
    public function docxToPdf(string $docxPath): string
    {
        $workDir = $this->tempDir();
        $profileDir = $workDir.'/profile';
        $input = $workDir.'/soal.docx';
        File::copy($docxPath, $input);

        $result = Process::timeout(config('pbm.tools.timeout'))->run([
            config('pbm.tools.soffice'),
            '-env:UserInstallation=file://'.$profileDir,
            '--headless', '--norestore', '--convert-to', 'pdf',
            '--outdir', $workDir, $input,
        ]);

        $output = $workDir.'/soal.pdf';
        if (! $result->successful() || ! is_file($output)) {
            Log::warning('Konversi DOCX gagal', ['stderr' => $result->errorOutput(), 'exit' => $result->exitCode()]);
            File::deleteDirectory($workDir);
            throw new DocumentProcessingFailed('Dokumen DOCX tidak dapat dikonversi ke PDF. Pastikan berkas tidak rusak, atau unggah versi PDF.');
        }

        $pdf = $this->tempDir().'/converted.pdf';
        File::move($output, $pdf);
        File::deleteDirectory($workDir);

        return $pdf;
    }

    /**
     * Menulis ulang struktur PDF tanpa object stream (isi halaman tidak berubah),
     * agar bisa dibaca parser FPDI. Dipakai untuk PDF dari Word atau PDF 1.5 ke atas.
     */
    public function normalizePdf(string $pdfPath): string
    {
        $output = $this->tempDir().'/normalized.pdf';

        $result = Process::timeout(config('pbm.tools.timeout'))->run([
            config('pbm.tools.qpdf'), '--object-streams=disable', '--decrypt', $pdfPath, $output,
        ]);

        // Kode keluar 3 dari qpdf berarti berhasil dengan peringatan.
        if (! in_array($result->exitCode(), [0, 3], true) || ! is_file($output)) {
            Log::warning('Normalisasi PDF gagal', ['stderr' => $result->errorOutput(), 'exit' => $result->exitCode()]);
            throw new DocumentProcessingFailed('PDF tidak dapat dibaca. Berkas mungkin rusak atau dilindungi kata sandi.');
        }

        return $output;
    }

    /**
     * Mencari penanda area tanda tangan. Koordinat dalam point, titik asal di kiri atas halaman.
     *
     * @return array{page:int, x:float, y:float, width:float, height:float}|null
     */
    public function findMarker(string $pdfPath, string $marker): ?array
    {
        $result = Process::timeout(config('pbm.tools.timeout'))->run([
            config('pbm.tools.pdftotext'), '-bbox', $pdfPath, '-',
        ]);

        if (! $result->successful()) {
            return null;
        }

        $html = $result->output();
        $found = null;
        $page = 0;

        preg_match_all('/<page\b|<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="([\d.]+)">([^<]*)<\/word>/', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            if ($m[0] === '<page') {
                $page++;
                continue;
            }
            if (trim(html_entity_decode($m[5])) === $marker) {
                // Penanda terakhir yang dipakai, karena area pengesahan biasanya di akhir dokumen.
                $found = [
                    'page' => $page,
                    'x' => (float) $m[1],
                    'y' => (float) $m[2],
                    'width' => (float) $m[3] - (float) $m[1],
                    'height' => (float) $m[4] - (float) $m[2],
                ];
            }
        }

        return $found;
    }

    public function tempDir(): string
    {
        $dir = storage_path('app/tmp/'.Str::uuid());
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
