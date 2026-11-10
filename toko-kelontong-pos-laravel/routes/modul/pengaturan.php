<?php

use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\TokoController;
use Illuminate\Support\Facades\Route;

// Modul Akun & Pengaturan (sudah di dalam grup ['auth', 'cek.sesi']).
Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan');

Route::get('/api/toko', [TokoController::class, 'tampil']);
Route::get('/api/pengguna/saya', [PenggunaController::class, 'saya']);
Route::put('/api/pengguna/saya', [PenggunaController::class, 'ubahSaya']);

Route::middleware('peran:pemilik')->group(function () {
    Route::put('/api/toko', [TokoController::class, 'ubah']);
    Route::post('/api/toko/logo', [TokoController::class, 'logo']);
    Route::get('/api/pengguna', [PenggunaController::class, 'daftar']);
    Route::post('/api/pengguna', [PenggunaController::class, 'tambah']);
    Route::put('/api/pengguna/{id}', [PenggunaController::class, 'ubah'])->where('id', '[^/]+');
});
