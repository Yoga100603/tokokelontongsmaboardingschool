<?php

use App\Http\Controllers\DasborController;
use Illuminate\Support\Facades\Route;

// Dasbor kondisi toko (pemilik saja).
Route::middleware('peran:pemilik')->group(function () {
    Route::get('/dasbor', [DasborController::class, 'halaman'])->name('dasbor');
    Route::get('/api/dasbor', [DasborController::class, 'data']);
});
