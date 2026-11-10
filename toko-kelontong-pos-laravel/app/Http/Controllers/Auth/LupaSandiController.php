<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pemulihan kata sandi. Token acak (berlaku 30 menit) disimpan dalam bentuk hash sha256.
 * SMTP belum dikonfigurasi → tautan ditulis ke storage/logs/laravel.log ("[lupa-sandi]").
 * Respons selalu sama agar keberadaan email tidak bisa ditebak.
 */
class LupaSandiController extends Controller
{
    public const MENIT_BERLAKU = 30;

    public function create()
    {
        return view('auth.lupa-sandi');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:120']]);
        $email = Str::lower(trim($data['email']));

        $u = Pengguna::where('email', $email)->where('aktif', true)->first();
        if ($u) {
            $token = bin2hex(random_bytes(32));
            $u->forceFill([
                'token_reset' => hash('sha256', $token),
                'token_reset_kedaluwarsa' => now()->addMinutes(self::MENIT_BERLAKU),
            ])->saveQuietly();

            // TODO(SMTP): ganti dengan Mail::to($u)->send(...) setelah layanan email tersedia.
            Log::info("[lupa-sandi] Tautan reset untuk {$email}: ".route('reset-sandi', ['token' => $token]));
        }

        return back()->with('sukses', 'Jika email terdaftar, tautan untuk mengatur ulang kata sandi sudah dikirim.');
    }
}
