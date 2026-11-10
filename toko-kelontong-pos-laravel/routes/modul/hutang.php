<?php

use App\Http\Controllers\HutangController;
use Illuminate\Support\Facades\Route;

// Buku Hutang (setara app/(app)/hutang + app/api/hutang). Kasir & pemilik.
Route::get('/hutang', [HutangController::class, 'halaman'])->name('hutang');
Route::get('/api/hutang', [HutangController::class, 'index']);
Route::post('/api/hutang', [HutangController::class, 'store']);
Route::patch('/api/hutang', [HutangController::class, 'lunas']);
