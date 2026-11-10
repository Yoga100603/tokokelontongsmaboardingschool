<?php

use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\TutupBukuController;
use Illuminate\Support\Facades\Route;

// Penjualan harian (pemilik & kasir) + Tutup Buku Harian (pemilik).
Route::get('/penjualan', [PenjualanController::class, 'halaman'])->name('penjualan');
Route::get('/api/penjualan', [PenjualanController::class, 'data']);
Route::post('/api/penjualan/tutup-buku', [TutupBukuController::class, 'simpan'])->middleware('peran:pemilik');
