<?php

use App\Http\Controllers\LaporanController;
use Illuminate\Support\Facades\Route;

// Laporan penjualan per rentang tanggal + unduh CSV (pemilik saja).
Route::middleware('peran:pemilik')->group(function () {
    Route::get('/laporan', [LaporanController::class, 'halaman'])->name('laporan');
    Route::get('/api/laporan', [LaporanController::class, 'data']);
    Route::get('/api/laporan/unduh', [LaporanController::class, 'unduh']);
});
