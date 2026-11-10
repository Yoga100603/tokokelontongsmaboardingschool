<?php

use App\Http\Controllers\BerandaController;
use App\Http\Controllers\KatalogController;
use Illuminate\Support\Facades\Route;

// Publik (tanpa login). Menimpa route `beranda` di _placeholder.php (dimuat lebih dulu).
Route::get('/', [BerandaController::class, 'index'])->name('beranda');
Route::get('/toko/{slug}', [KatalogController::class, 'tampil'])->name('katalog');
