<?php

use App\Http\Controllers\OpnameController;
use Illuminate\Support\Facades\Route;

// Stok Opname (setara app/(app)/opname + app/api/opname/**). Kasir & pemilik.
Route::get('/opname', [OpnameController::class, 'halaman'])->name('opname');
Route::get('/opname/{id}', [OpnameController::class, 'halamanTampil'])->whereNumber('id')->name('opname.tampil');

Route::get('/api/opname', [OpnameController::class, 'index']);
Route::post('/api/opname', [OpnameController::class, 'store']);
Route::get('/api/opname/{id}', [OpnameController::class, 'show'])->whereNumber('id');
Route::put('/api/opname/{id}', [OpnameController::class, 'update'])->whereNumber('id');
Route::delete('/api/opname/{id}', [OpnameController::class, 'destroy'])->whereNumber('id');

// Aksi alur: ajukan (kasir/pemilik), setujui & tolak (pemilik saja).
Route::post('/api/opname/{id}/ajukan', [OpnameController::class, 'ajukan'])->whereNumber('id');
Route::post('/api/opname/{id}/{aksi}', [OpnameController::class, 'putuskan'])
    ->whereNumber('id')->whereIn('aksi', ['setujui', 'tolak'])->middleware('peran:pemilik');
