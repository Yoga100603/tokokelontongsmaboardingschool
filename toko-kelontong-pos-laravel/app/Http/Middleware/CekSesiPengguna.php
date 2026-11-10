<?php

namespace App\Http\Middleware;

use App\Models\Pengguna;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validasi ulang sesi ke DB tiap request: akun masih aktif dan versi sesi sama.
 * Ganti sandi / dinonaktifkan pemilik → versi_sesi naik → sesi lama langsung keluar.
 */
class CekSesiPengguna
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        // Baca ulang dari DB (bukan dari objek user yang mungkin sudah di-cache guard).
        $kini = $u ? Pengguna::query()->whereKey($u->getKey())->first(['aktif', 'versi_sesi']) : null;

        if ($u && (! $kini || ! $kini->aktif || (int) $request->session()->get('versi_sesi') !== (int) $kini->versi_sesi)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['sukses' => false, 'pesan' => 'Sesi berakhir. Silakan masuk kembali.'], 401)
                : redirect()->route('masuk', ['sesi' => 1]);
        }

        return $next($request);
    }
}
