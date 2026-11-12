<?php

use Illuminate\Support\Facades\Route;

// Backend hanya melayani API; antarmuka disajikan oleh aplikasi Vue di folder frontend.
Route::get('/', fn () => response()->json(['nama' => 'PBM Management System API', 'api' => url('/api')]));
