<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class MasukController extends Controller
{
    public const MAKS_PERCOBAAN = 5;

    public function create()
    {
        return view('auth.masuk');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:120'],
            'kata_sandi' => ['required', 'string', 'max:100'],
        ], [], ['kata_sandi' => 'kata sandi']);

        $email = Str::lower(trim($data['email']));
        $kunci = 'masuk:'.$request->ip().':'.$email;

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            return $this->gagal('Terlalu banyak percobaan masuk. Tunggu 15 menit lalu coba lagi.');
        }
        RateLimiter::hit($kunci, 15 * 60);

        $u = Pengguna::where('email', $email)->first();
        // Pesan sama untuk email tidak ada / sandi salah agar akun tidak bisa ditebak.
        if (! $u || ! Hash::check($data['kata_sandi'], $u->kata_sandi)) {
            return $this->gagal('Email atau kata sandi salah.');
        }
        if (! $u->aktif) {
            return $this->gagal('Akun ini sudah dinonaktifkan oleh pemilik toko.');
        }

        RateLimiter::clear($kunci);
        $u->forceFill(['terakhir_masuk' => now()])->saveQuietly();

        Auth::login($u);
        $request->session()->regenerate();
        $request->session()->put('versi_sesi', $u->versi_sesi);

        return redirect()->intended($u->isPemilik() ? route('dasbor') : route('kasir'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk')->with('info', 'Anda sudah keluar.');
    }

    private function gagal(string $pesan)
    {
        return back()->withInput(['email' => request('email')])->withErrors(['umum' => $pesan]);
    }
}
