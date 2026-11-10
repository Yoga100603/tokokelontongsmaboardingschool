<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use App\Support\GalatApi;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /** Respons sukses standar: {sukses:true, pesan, data} (sama dengan versi Next.js). */
    protected function ok(mixed $data = null, ?string $pesan = null, int $status = 200): JsonResponse
    {
        return response()->json(['sukses' => true, 'pesan' => $pesan, 'data' => $data], $status);
    }

    protected function tolak(int $status, string $pesan, mixed $detail = null): never
    {
        GalatApi::tolak($status, $pesan, $detail);
    }

    protected function pengguna(): Pengguna
    {
        return auth()->user();
    }

    protected function tokoId(): int
    {
        return (int) auth()->user()->toko_id;
    }
}
