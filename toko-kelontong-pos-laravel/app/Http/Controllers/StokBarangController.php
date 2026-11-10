<?php

namespace App\Http\Controllers;

use App\Http\Requests\BarangValidasi as V;
use App\Models\Barang;
use App\Services\MutasiStokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Port app/api/barang/[id]/stok/route.ts — riwayat mutasi & stok masuk/keluar (semua peran). */
class StokBarangController extends Controller
{
    /** GET /api/barang/{id}/stok — 100 mutasi terakhir satu barang. */
    public function index(int $id)
    {
        if (! Barang::whereKey($id)->exists()) {
            $this->tolak(404, 'Barang tidak ditemukan.');
        }
        $rows = DB::table('mutasi_stok as m')
            ->leftJoin('pengguna as p', 'p.id', '=', 'm.pengguna_id')
            ->where('m.barang_id', $id)
            ->where('m.toko_id', $this->tokoId())
            ->orderByDesc('m.id')
            ->limit(100)
            ->get(['m.id', 'm.jenis', 'm.jumlah', 'm.stok_sebelum', 'm.stok_sesudah', 'm.keterangan', 'm.referensi', 'm.dibuat_pada', 'p.nama as oleh'])
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'jenis' => $m->jenis,
                'jumlah' => (int) $m->jumlah,
                'stok_sebelum' => (int) $m->stok_sebelum,
                'stok_sesudah' => (int) $m->stok_sesudah,
                'keterangan' => (string) $m->keterangan,
                'referensi' => (string) $m->referensi,
                'dibuat_pada' => $m->dibuat_pada ? substr((string) $m->dibuat_pada, 0, 19) : null,
                'oleh' => $m->oleh,
            ])->values();

        return $this->ok($rows);
    }

    /** POST /api/barang/{id}/stok — catat stok masuk (kiriman) atau keluar (rusak, kedaluwarsa, dipakai sendiri). */
    public function store(Request $request, int $id, MutasiStokService $stok)
    {
        $b = V::body($request);
        $jenis = match ($b['jenis'] ?? null) {
            'keluar' => 'keluar',
            'masuk' => 'masuk',
            default => $this->tolak(422, 'Jenis mutasi harus masuk atau keluar.'),
        };
        $jumlah = V::angka($b['jumlah'] ?? null, 'Jumlah', min: 1, maks: 1_000_000);
        $ket = V::teks($b['keterangan'] ?? null, 'Keterangan', wajib: $jenis === 'keluar', maks: 200);

        $hasil = DB::transaction(fn () => $stok->ubah(
            $this->tokoId(), $id, $this->pengguna()->id, $jenis,
            $jenis === 'masuk' ? $jumlah : -$jumlah,
            $ket !== '' ? $ket : ($jenis === 'masuk' ? 'Barang masuk' : 'Barang keluar'),
        ));

        return $this->ok(
            ['stok_sebelum' => $hasil['stok_sebelum'], 'stok_sesudah' => $hasil['stok_sesudah']],
            $jenis === 'masuk' ? "Stok masuk {$jumlah} dicatat." : "Stok keluar {$jumlah} dicatat.",
        );
    }
}
