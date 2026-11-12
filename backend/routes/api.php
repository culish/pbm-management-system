<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamSubmissionController;
use App\Http\Controllers\Api\SignatureAuthorizationController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:60,1');

Route::get('/verifikasi/{kode}', [VerificationController::class, 'show'])
    ->where('kode', '[A-Za-z0-9]{6,16}')
    ->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(EnsurePermission::class.':dashboard.view');

    Route::prefix('soal')->group(function () {
        Route::get('/opsi', [ExamSubmissionController::class, 'options'])
            ->middleware(EnsurePermission::class.':soal.upload');
        Route::post('/', [ExamSubmissionController::class, 'store'])
            ->middleware([EnsurePermission::class.':soal.upload', 'throttle:20,1']);

        Route::middleware(EnsurePermission::class.':soal.upload,soal.status,soal.authorize,dokumen.manage')->group(function () {
            Route::get('/', [ExamSubmissionController::class, 'index']);
            Route::get('/{soal}', [ExamSubmissionController::class, 'show']);
            Route::post('/{soal}/proses', [ExamSubmissionController::class, 'reprocess'])->middleware('throttle:20,1');
            Route::get('/{soal}/berkas/{jenis}', [ExamSubmissionController::class, 'file'])->whereIn('jenis', ['asli', 'final']);
        });
    });

    Route::prefix('otorisasi-ttd')->middleware(EnsurePermission::class.':soal.authorize,signature.manage')->group(function () {
        Route::get('/', [SignatureAuthorizationController::class, 'index']);
        Route::post('/', [SignatureAuthorizationController::class, 'store'])
            ->middleware(EnsurePermission::class.':soal.authorize');
        Route::post('/{otorisasi}/cabut', [SignatureAuthorizationController::class, 'revoke']);
        Route::get('/{otorisasi}/gambar', [SignatureAuthorizationController::class, 'image']);
    });
});
