<?php

namespace Tests\Concerns;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Pengguna;
use App\Models\Toko;

/**
 * Helper fixture bersama untuk feature test semua modul.
 *   $toko = $this->buatToko();
 *   $pemilik = $this->buatAkun($toko, 'pemilik');
 *   $this->masukSebagai($pemilik);           // login + versi_sesi di session (lolos cek.sesi)
 *   $b = $this->buatBarang($toko, ['stok' => 10]);
 */
trait PunyaToko
{
    protected function buatToko(array $ubah = []): Toko
    {
        return Toko::create(array_merge(['nama' => 'Toko Uji', 'slug' => 'toko-uji-'.uniqid()], $ubah));
    }

    protected function buatAkun(Toko $toko, string $peran = 'pemilik', array $ubah = []): Pengguna
    {
        $p = new Pengguna(array_merge([
            'nama' => ucfirst($peran).' Uji', 'email' => uniqid($peran).'@uji.id',
            'kata_sandi' => 'rahasia123', 'peran' => $peran,
        ], $ubah));
        $p->toko_id = $toko->id;
        $p->save();

        return $p->fresh();
    }

    protected function masukSebagai(Pengguna $p): static
    {
        return $this->actingAs($p)->withSession(['versi_sesi' => $p->versi_sesi]);
    }

    protected function buatKategori(Toko $toko, string $nama = 'Umum'): KategoriBarang
    {
        $k = new KategoriBarang(['nama' => $nama]);
        $k->toko_id = $toko->id;
        $k->save();

        return $k;
    }

    /** Barang dengan stok langsung di-set (tanpa mutasi) — khusus fixture test. */
    protected function buatBarang(Toko $toko, array $ubah = []): Barang
    {
        $stok = $ubah['stok'] ?? 10;
        unset($ubah['stok']);
        $b = new Barang(array_merge([
            'kode_barang' => 'K'.uniqid(), 'nama_barang' => 'Barang Uji', 'satuan' => 'pcs',
            'harga_jual' => 5000, 'harga_modal' => 4000, 'stok_minimum' => 2,
        ], $ubah));
        $b->toko_id = $toko->id;
        $b->stok = $stok;
        $b->save();

        return $b->fresh();
    }
}
