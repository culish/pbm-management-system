<?php

namespace App\Services\Soal;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParserException;

/**
 * Menyalin setiap halaman PDF apa adanya lalu menambahkan blok pengesahan:
 * di posisi penanda bila ada, atau pada lembar pengesahan baru di akhir dokumen.
 */
class PdfStamper
{
    private const FONT = 'dejavusans';
    private const BLOCK_WIDTH = 200.0;
    private const IMAGE_HEIGHT = 56.0;

    public function __construct(private DocumentTools $tools)
    {
    }

    /**
     * @param  array{page:int, x:float, y:float, width:float, height:float}|null  $marker
     * @param  array{signer_name:string, signer_nip:?string, signer_title:string, image_path:string, signed_at:string, code:string, lembar:array<string,string>}  $stamp
     */
    public function stamp(string $sourcePdf, ?array $marker, array $stamp, string $outputPath): void
    {
        try {
            $this->render($sourcePdf, $marker, $stamp, $outputPath);
        } catch (CrossReferenceException|PdfParserException) {
            $this->render($this->tools->normalizePdf($sourcePdf), $marker, $stamp, $outputPath);
        }
    }

    private function render(string $sourcePdf, ?array $marker, array $stamp, string $outputPath): void
    {
        $pdf = new SoalPdf('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCreator('PBM Management System');
        $pdf->SetTitle($stamp['lembar']['Dokumen'] ?? 'Soal ujian');

        $pageCount = $pdf->setSourceFile($sourcePdf);
        $size = ['width' => 595.28, 'height' => 841.89, 'orientation' => 'P'];

        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            if ($marker && $marker['page'] === $i) {
                $this->drawAtMarker($pdf, $marker, $stamp, $size);
            }
        }

        if (! $marker) {
            $this->drawApprovalSheet($pdf, $stamp, $size);
        }

        $pdf->Output($outputPath, 'F');
    }

    private function drawAtMarker(SoalPdf $pdf, array $marker, array $stamp, array $size): void
    {
        // Menutup teks penanda agar tidak ikut tercetak.
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($marker['x'] - 1, $marker['y'] - 1, $marker['width'] + 2, $marker['height'] + 2, 'F');

        $x = min($marker['x'], $size['width'] - self::BLOCK_WIDTH - 12);
        $y = min($marker['y'], $size['height'] - self::IMAGE_HEIGHT - 60);

        $this->drawSignatureBlock($pdf, $x, $y, $stamp);
    }

    private function drawApprovalSheet(SoalPdf $pdf, array $stamp, array $size): void
    {
        $pdf->AddPage('P', [595.28, 841.89]);
        $margin = 64.0;

        $pdf->SetTextColor(20, 43, 74);
        $pdf->SetFont(self::FONT, 'B', 15);
        $pdf->SetXY($margin, 72);
        $pdf->Cell(0, 20, 'LEMBAR PENGESAHAN SOAL', 0, 1);

        $pdf->SetDrawColor(20, 43, 74);
        $pdf->SetLineWidth(1);
        $pdf->Line($margin, 98, 595.28 - $margin, 98);

        $pdf->SetTextColor(30, 41, 59);
        $y = 116.0;
        foreach ($stamp['lembar'] as $label => $value) {
            $pdf->SetFont(self::FONT, '', 10.5);
            $pdf->SetXY($margin, $y);
            $pdf->Cell(130, 16, $label, 0, 0);
            $pdf->Cell(10, 16, ':', 0, 0);
            $pdf->MultiCell(595.28 - 2 * $margin - 140, 16, $value, 0, 'L');
            $y = max($y + 20, $pdf->GetY() + 4);
        }

        $pdf->SetFont(self::FONT, '', 10.5);
        $pdf->SetXY(595.28 - $margin - self::BLOCK_WIDTH, $y + 28);
        $pdf->Cell(self::BLOCK_WIDTH, 14, 'Mengesahkan,', 0, 2);
        $pdf->MultiCell(self::BLOCK_WIDTH, 14, $stamp['signer_title'], 0, 'L');

        $this->drawSignatureBlock($pdf, 595.28 - $margin - self::BLOCK_WIDTH, $pdf->GetY() + 4, $stamp);
    }

    private function drawSignatureBlock(SoalPdf $pdf, float $x, float $y, array $stamp): void
    {
        $pdf->Image($stamp['image_path'], $x, $y, 0, self::IMAGE_HEIGHT, 'PNG', '', '', true, 300, '', false, false, 0, 'LT');

        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFont(self::FONT, 'BU', 10);
        $pdf->SetXY($x, $y + self::IMAGE_HEIGHT + 2);
        $pdf->Cell(self::BLOCK_WIDTH, 13, $stamp['signer_name'], 0, 2);

        if ($stamp['signer_nip']) {
            $pdf->SetFont(self::FONT, '', 9);
            $pdf->Cell(self::BLOCK_WIDTH, 12, 'NIP '.$stamp['signer_nip'], 0, 2);
        }

        $pdf->SetFont(self::FONT, '', 6.5);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->MultiCell(
            self::BLOCK_WIDTH, 8.5,
            'Disahkan elektronik '.$stamp['signed_at'].' melalui PBM Management System. Kode verifikasi '.$stamp['code'],
            0, 'L',
        );
    }
}
