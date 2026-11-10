<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Port app/api/pengguna/route.ts, app/api/pengguna/[id]/route.ts, app/api/pengguna/saya/route.ts.
 * Pengguna TIDAK memakai trait MilikToko → filter toko_id manual di setiap query.
 */
class PenggunaController extends Controller
{
    /** Setara MIN_SANDI di lib/akun.ts. */
    public const MIN_SANDI = 8;

    /** GET /api/pengguna — daftar akun di toko ini (pemilik). */
    public function daftar()
    {
        $akun = DB::table('pengguna')
            ->where('toko_id', $this->tokoId())
            ->orderByDesc('peran')->orderBy('nama')
            ->get(['id', 'nama', 'email', 'peran', 'aktif', 'terakhir_masuk', 'dibuat_pada'])
            ->map(fn ($a) => [
                'id' => (int) $a->id, 'nama' => $a->nama, 'email' => $a->email, 'peran' => $a->peran,
                'aktif' => (int) $a->aktif, 'terakhir_masuk' => $a->terakhir_masuk, 'dibuat_pada' => $a->dibuat_pada,
            ]);

        return $this->ok($akun);
    }

    /** POST /api/pengguna — tambah akun kasir (pemilik). toko_id selalu dari sesi pemilik. */
    public function tambah(Request $request)
    {
        $nama = TokoController::teks($request->input('nama'), 'Nama kasir', wajib: true, maks: 80);
        $email = TokoController::emailValid($request->input('email'));
        $sandi = TokoController::teks($request->input('kata_sandi'), 'Kata sandi', wajib: true, min: self::MIN_SANDI, maks: 100);
        $tokoId = $this->tokoId();

        $p = DB::transaction(function () use ($nama, $email, $sandi, $tokoId) {
            if (Pengguna::where('email', $email)->exists()) {
                $this->tolak(409, 'Email sudah dipakai akun lain.');
            }
            $p = new Pengguna(['nama' => $nama, 'email' => $email, 'kata_sandi' => $sandi]);
            $p->peran = 'kasir';
            $p->toko_id = $tokoId;
            $p->save();

            return $p;
        });

        return $this->ok(['id' => $p->id], "Akun kasir {$nama} berhasil dibuat.", 201);
    }

    /** PUT /api/pengguna/{id} — ubah akun kasir: nama, aktif/nonaktif, atur ulang sandi (pemilik). */
    public function ubah(Request $request, string $id)
    {
        if (! ctype_digit($id) || (int) $id <= 0) {
            $this->tolak(404, 'Data tidak ditemukan.');
        }
        $target = Pengguna::where('id', (int) $id)->where('toko_id', $this->tokoId())->first(['id', 'peran', 'aktif']);
        if (! $target) {
            $this->tolak(404, 'Akun tidak ditemukan.');
        }
        if ($target->peran !== 'kasir') {
            $this->tolak(422, 'Akun pemilik diubah lewat bagian Profil Saya.');
        }

        $nama = TokoController::teks($request->input('nama'), 'Nama', wajib: true, maks: 80);
        $aktif = ! empty($request->input('aktif'));
        $sandiMentah = $request->input('kata_sandi');

        // Nonaktif / ganti sandi → versi sesi naik sehingga kasir langsung keluar.
        if ($sandiMentah !== null && $sandiMentah !== '') {
            $sandi = TokoController::teks($sandiMentah, 'Kata sandi baru', min: self::MIN_SANDI, maks: 100);
            $ubah = ['nama' => $nama, 'aktif' => $aktif, 'kata_sandi' => Hash::make($sandi), 'versi_sesi' => DB::raw('versi_sesi + 1')];
        } else {
            $ubah = ['nama' => $nama, 'aktif' => $aktif];
            if ((bool) $target->aktif !== $aktif) {
                $ubah['versi_sesi'] = DB::raw('versi_sesi + 1');
            }
        }
        DB::transaction(fn () => Pengguna::whereKey($target->id)->where('toko_id', $this->tokoId())->update($ubah));

        return $this->ok(null, 'Akun kasir diperbarui.');
    }

    /** GET /api/pengguna/saya — data pengguna aktif (setara PenggunaAktif). */
    public function saya()
    {
        $u = $this->pengguna();

        return $this->ok([
            'id' => $u->id, 'nama' => $u->nama, 'email' => $u->email, 'peran' => $u->peran,
            'toko_id' => (int) $u->toko_id, 'toko_nama' => $u->toko->nama, 'toko_slug' => $u->toko->slug,
        ]);
    }

    /** PUT /api/pengguna/saya — ubah nama sendiri dan/atau kata sandi (wajib sandi lama). */
    public function ubahSaya(Request $request)
    {
        $u = $this->pengguna();
        $nama = TokoController::teks($request->input('nama'), 'Nama', wajib: true, maks: 80);
        $baruMentah = $request->input('sandi_baru');

        if ($baruMentah !== null && $baruMentah !== '') {
            $baru = TokoController::teks($baruMentah, 'Kata sandi baru', min: self::MIN_SANDI, maks: 100);
            $versi = DB::transaction(function () use ($u, $nama, $baru, $request) {
                $r = Pengguna::whereKey($u->id)->lockForUpdate()->first(['id', 'kata_sandi', 'versi_sesi']);
                if (! $r || ! Hash::check((string) $request->input('sandi_lama', ''), $r->kata_sandi)) {
                    $this->tolak(422, 'Kata sandi lama salah.');
                }
                Pengguna::whereKey($u->id)->update([
                    'nama' => $nama, 'kata_sandi' => Hash::make($baru), 'versi_sesi' => DB::raw('versi_sesi + 1'),
                ]);

                return (int) $r->versi_sesi + 1;
            });
            // Sesi di perangkat lain keluar, perangkat ini tetap masuk.
            $request->session()->put('versi_sesi', $versi);

            return $this->ok(null, 'Profil dan kata sandi diperbarui.');
        }

        Pengguna::whereKey($u->id)->update(['nama' => $nama]);

        return $this->ok(null, 'Profil diperbarui.');
    }
}
