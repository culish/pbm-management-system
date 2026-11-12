<?php

return [
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    'soal' => [
        'max_upload_kb' => (int) env('SOAL_MAX_UPLOAD_KB', 20480),
        'signature_marker' => env('SOAL_SIGNATURE_MARKER', '[TTD_KPS]'),
        'max_authorization_days' => (int) env('TTD_MAX_AUTHORIZATION_DAYS', 366),
    ],

    'tools' => [
        'soffice' => env('SOFFICE_BINARY', 'soffice'),
        'pdftotext' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'qpdf' => env('QPDF_BINARY', 'qpdf'),
        'timeout' => (int) env('DOCUMENT_TOOL_TIMEOUT', 120),
    ],
];
