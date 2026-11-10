<?php

use App\Http\Controllers\KasirController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

// Modul Kasir + Transaksi (port app/(app)/kasir & app/api/transaksi/**)
Route::get('/kasir', [KasirController::class, 'index'])->name('kasir');

Route::get('/api/transaksi', [TransaksiController::class, 'index']);
Route::post('/api/transaksi', [TransaksiController::class, 'store']);
Route::get('/api/transaksi/{nomor}', [TransaksiController::class, 'show']);
Route::post('/api/transaksi/{nomor}/log', [TransaksiController::class, 'log']);
