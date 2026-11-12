<?php

namespace App\Services\Soal;

use setasign\Fpdi\Tcpdf\Fpdi;

class SoalPdf extends Fpdi
{
    /** Konstruktor TCPDF selalu menyalakan tautan "Powered by TCPDF"; dimatikan setelahnya. */
    public function __construct(...$args)
    {
        parent::__construct(...$args);
        $this->tcpdflink = false;
    }
}
