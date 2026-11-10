<?php

use App\Http\Controllers\Auth\DaftarController;
use App\Http\Controllers\Auth\LupaSandiController;
use App\Http\Controllers\Auth\MasukController;
use App\Http\Controllers\BerkasController;
use Illuminate\Support\Facades\Route;

/*
| Struktur route:
|   routes/publik/*.php  → tanpa login (landing, katalog /toko/{slug})
|   routes/modul/*.php   → wajib login + cek.sesi (halaman Blade + endpoint JSON /api/*)
| Tiap modul punya file sendiri. Pembatasan pemilik: ->middleware('peran:pemilik').
*/

// ── Publik ───────────────────────────────────────────────────────────
Route::get('/api/berkas/{nama}', [BerkasController::class, 'tampil'])->name('berkas');

foreach (glob(__DIR__.'/publik/*.php') as $f) {
    require $f;
}

// ── Tamu (belum login) ───────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [MasukController::class, 'create'])->name('masuk');
    Route::post('/masuk', [MasukController::class, 'store']);

    Route::get('/daftar', [DaftarController::class, 'create'])->name('daftar');
    Route::post('/daftar', [DaftarController::class, 'store'])->middleware('throttle:daftar');

    Route::get('/lupa-sandi', [LupaSandiController::class, 'create'])->name('lupa-sandi');
    Route::post('/lupa-sandi', [LupaSandiController::class, 'store'])->middleware('throttle:lupa-sandi');

    Route::get('/reset-sandi', [App\Http\Controllers\Auth\ResetSandiController::class, 'create'])->name('reset-sandi');
    Route::post('/reset-sandi', [App\Http\Controllers\Auth\ResetSandiController::class, 'store']);
});

// ── Aplikasi (wajib login, sesi divalidasi ulang ke DB) ──────────────
Route::middleware(['auth', 'cek.sesi'])->group(function () {
    Route::post('/keluar', [MasukController::class, 'destroy'])->name('keluar');

    // _placeholder.php dimuat PERTAMA (urutan glob: "_" < huruf) lalu ditimpa route modul asli.
    foreach (glob(__DIR__.'/modul/*.php') as $f) {
        require $f;
    }
});
