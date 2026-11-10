<?php

use App\Http\Controllers\StrukController;
use Illuminate\Support\Facades\Route;

// Modul Riwayat Struk (port app/(app)/struk/**). Endpoint data ada di routes/modul/kasir.php (/api/transaksi).
Route::get('/struk', [StrukController::class, 'index'])->name('struk');
Route::get('/struk/{nomor}', [StrukController::class, 'show'])->name('struk.tampil');
