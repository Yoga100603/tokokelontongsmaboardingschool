<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use App\Models\Toko;
use Illuminate\Http\Request;

/** Port app/page.tsx — landing page publik di "/". */
class BerandaController extends Controller
{
    public function index(Request $request)
    {
        $toko = Toko::where('katalog_aktif', true)->orderBy('id')->limit(6)->get(['nama', 'slug', 'alamat']);

        return view('publik.beranda', ['toko' => $toko, 'u' => $this->penggunaAktif($request)]);
    }

    /**
     * Setara penggunaAktif() sumber: route publik tidak lewat middleware cek.sesi,
     * jadi validasi ringan di sini (akun aktif & versi sesi cocok) tanpa mengubah sesi.
     */
    private function penggunaAktif(Request $request): ?Pengguna
    {
        $u = $request->user();
        if (! $u) {
            return null;
        }
        $kini = Pengguna::whereKey($u->getKey())->first(['id', 'aktif', 'versi_sesi']);
        if (! $kini || ! $kini->aktif || (int) $request->session()->get('versi_sesi') !== (int) $kini->versi_sesi) {
            return null;
        }

        return $u;
    }
}
