<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResetSandiController extends Controller
{
    public function create(Request $request)
    {
        return view('auth.reset-sandi', ['token' => (string) $request->query('token', '')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'kata_sandi' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], ['kata_sandi.confirmed' => 'Konfirmasi kata sandi tidak sama.'], ['kata_sandi' => 'kata sandi baru']);

        $berhasil = DB::transaction(function () use ($data) {
            $u = Pengguna::where('token_reset', hash('sha256', $data['token']))
                ->where('token_reset_kedaluwarsa', '>=', now())
                ->where('aktif', true)
                ->lockForUpdate()
                ->first();
            if (! $u) {
                return false;
            }

            // Naikkan versi sesi → semua sesi lama otomatis keluar.
            $u->kata_sandi = $data['kata_sandi'];
            $u->token_reset = null;
            $u->token_reset_kedaluwarsa = null;
            $u->versi_sesi = $u->versi_sesi + 1;
            $u->save();

            return true;
        });

        if (! $berhasil) {
            return back()->withErrors(['umum' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.']);
        }

        return redirect()->route('masuk', ['reset' => 1]);
    }
}
