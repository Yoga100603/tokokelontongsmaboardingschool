<?php

use App\Http\Controllers\BarangController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\StokBarangController;
use Illuminate\Support\Facades\Route;

// Modul Barang & Stok (port app/(app)/barang + app/api/barang + app/api/kategori).
Route::get('/barang', [BarangController::class, 'halaman'])->name('barang');

// Semua peran: daftar/detail barang (dipakai juga oleh Kasir), kategori, riwayat & mutasi stok.
Route::get('/api/barang', [BarangController::class, 'index']);
Route::get('/api/barang/{id}', [BarangController::class, 'show'])->whereNumber('id');
Route::get('/api/barang/{id}/stok', [StokBarangController::class, 'index'])->whereNumber('id');
Route::post('/api/barang/{id}/stok', [StokBarangController::class, 'store'])->whereNumber('id');
Route::get('/api/kategori', [KategoriController::class, 'index']);

// Khusus pemilik: kelola barang, foto, kategori.
Route::middleware('peran:pemilik')->group(function () {
    Route::post('/api/barang', [BarangController::class, 'store']);
    Route::put('/api/barang/{id}', [BarangController::class, 'update'])->whereNumber('id');
    Route::delete('/api/barang/{id}', [BarangController::class, 'destroy'])->whereNumber('id');
    Route::post('/api/barang/{id}/foto', [BarangController::class, 'foto'])->whereNumber('id');
    Route::post('/api/kategori', [KategoriController::class, 'store']);
    Route::delete('/api/kategori/{id}', [KategoriController::class, 'destroy'])->whereNumber('id');
});
