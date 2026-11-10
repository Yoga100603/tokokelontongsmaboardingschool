<?php

namespace App\Http\Controllers;

use App\Services\BerkasService;

/** Menyajikan gambar upload (foto barang / logo) — publik karena dipakai katalog. */
class BerkasController extends Controller
{
    public function tampil(string $nama, BerkasService $berkas)
    {
        $path = $berkas->path($nama);
        abort_unless($path, 404);
        $ext = pathinfo($nama, PATHINFO_EXTENSION);

        return response()->file($path, [
            'Content-Type' => BerkasService::MIME[$ext],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
