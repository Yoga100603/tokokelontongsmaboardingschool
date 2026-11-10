<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\KategoriBarang;
use App\Models\Pengguna;
use App\Models\Toko;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Pendaftaran toko baru: membuat toko + akun pemilik + kategori awal, lalu langsung login. */
class DaftarController extends Controller
{
    public const KATEGORI_AWAL = ['Sembako', 'Makanan', 'Minuman', 'Kebersihan'];

    public function create()
    {
        return view('auth.daftar');
    }

    public function store(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'nama_toko' => ['required', 'string', 'min:3', 'max:80'],
            'nama' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:pengguna,email'],
            'kata_sandi' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'email.unique' => 'Email sudah terdaftar. Silakan masuk atau gunakan email lain.',
            'kata_sandi.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ], [
            'nama_toko' => 'nama toko', 'nama' => 'nama pemilik', 'kata_sandi' => 'kata sandi',
        ]);

        $pemilik = DB::transaction(function () use ($data) {
            $dasar = Str::slug($data['nama_toko']) ?: 'toko';
            $slug = $dasar;
            for ($i = 2; Toko::where('slug', $slug)->lockForUpdate()->exists(); $i++) {
                $slug = "$dasar-$i";
            }

            $toko = Toko::create(['nama' => $data['nama_toko'], 'slug' => $slug, 'email' => $data['email']]);

            foreach (self::KATEGORI_AWAL as $k) {
                $kat = new KategoriBarang(['nama' => $k]);
                $kat->toko_id = $toko->id;
                $kat->save();
            }

            $p = new Pengguna([
                'nama' => $data['nama'], 'email' => $data['email'],
                'kata_sandi' => $data['kata_sandi'], 'peran' => 'pemilik',
            ]);
            $p->toko_id = $toko->id;
            $p->terakhir_masuk = now();
            $p->save();

            return $p;
        });

        Auth::login($pemilik);
        $request->session()->regenerate();
        $request->session()->put('versi_sesi', $pemilik->fresh()->versi_sesi);

        return redirect()->route('pengaturan', ['baru' => 1])->with('sukses', 'Toko berhasil didaftarkan.');
    }
}
