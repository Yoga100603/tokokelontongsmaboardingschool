<?php

namespace Tests\Feature;

use App\Models\ItemTransaksi;
use App\Models\Pengguna;
use App\Models\Toko;
use App\Models\Transaksi;
use App\Models\TutupBuku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class PenjualanTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    /** Transaksi fixture langsung via model. $item = [[jumlah, harga_jual, harga_modal], ...] */
    private function trx(Toko $toko, Pengguna $kasir, string $tanggal, array $item, array $ubah = []): Transaksi
    {
        $total = array_sum(array_map(fn ($i) => $i[0] * $i[1], $item));
        $modal = array_sum(array_map(fn ($i) => $i[0] * $i[2], $item));
        $t = new Transaksi(array_merge([
            'pengguna_id' => $kasir->id, 'nomor_transaksi' => 'TRX-'.uniqid(), 'tanggal' => $tanggal,
            'waktu' => $tanggal.' 10:00:00', 'total' => $total, 'total_modal' => $modal,
            'uang_bayar' => $total, 'kembalian' => 0, 'metode_bayar' => 'tunai', 'nama_pelanggan' => '', 'status' => 'selesai',
        ], $ubah));
        $t->toko_id = $toko->id;
        $t->save();
        foreach ($item as $k => [$jml, $jual, $mdl]) {
            ItemTransaksi::create([
                'transaksi_id' => $t->id, 'barang_id' => null, 'nama_barang' => 'Barang '.$k, 'satuan' => 'pcs',
                'jumlah' => $jml, 'harga_jual' => $jual, 'harga_modal' => $mdl, 'subtotal' => $jml * $jual,
            ]);
        }

        return $t;
    }

    public function test_rekap_harian_hanya_selesai_dan_toko_sendiri(): void
    {
        $toko = $this->buatToko();
        $pemilik = $this->buatAkun($toko, 'pemilik');
        $hari = now()->toDateString();
        $kemarin = now()->subDay()->toDateString();

        // 2 transaksi selesai hari ini: total 10000+6000, modal 8000+4000, item 2+3
        $this->trx($toko, $pemilik, $hari, [[2, 5000, 4000]], ['uang_bayar' => 20000, 'kembalian' => 10000]);
        $this->trx($toko, $pemilik, $hari, [[3, 2000, 1000]], ['metode_bayar' => 'qris', 'waktu' => $hari.' 11:00:00']);
        // dibatalkan: tidak dihitung
        $this->trx($toko, $pemilik, $hari, [[9, 9000, 1000]], ['status' => 'dibatalkan']);
        // kemarin
        $this->trx($toko, $pemilik, $kemarin, [[1, 8000, 5000]]);
        // toko lain: tidak dihitung / tidak tampil
        $lain = $this->buatToko();
        $this->trx($lain, $this->buatAkun($lain, 'pemilik'), $hari, [[5, 7000, 1000]], ['nomor_transaksi' => 'LAIN-1']);

        $d = $this->masukSebagai($pemilik)->getJson('/api/penjualan?tanggal='.$hari)->assertOk()->json('data');

        $this->assertSame($hari, $d['tanggal']);
        $this->assertSame([
            'jumlah_transaksi' => 2, 'total_penjualan' => 16000, 'total_modal' => 11000,
            'laba_kotor' => 5000, 'item_terjual' => 5, 'uang_tunai' => 16000,
        ], $d['ringkasan']);
        $this->assertSame(1, $d['kemarin']['jumlah_transaksi']);
        $this->assertSame(8000, $d['kemarin']['total_penjualan']);
        $this->assertNull($d['tutup_buku']);
        // daftar transaksi: semua transaksi toko sendiri hari itu (termasuk dibatalkan, seperti sumber), terbaru dulu
        $this->assertCount(3, $d['transaksi']);
        $this->assertNotContains('LAIN-1', array_column($d['transaksi'], 'nomor_transaksi'));
        $this->assertSame(11, (int) substr($d['transaksi'][0]['waktu'], 11, 2));
        $this->assertArrayHasKey('laba', $d['transaksi'][0]);
        $this->assertEqualsCanonicalizing(['tunai', 'qris'], array_column($d['per_metode'], 'metode_bayar'));
        // fixture memakai nama "Barang 0" untuk tiap transaksi → tergabung 2+3; yang dibatalkan (9) & toko lain (5) tidak ikut
        $this->assertSame(5, $d['terlaris'][0]['jumlah']);
        $this->assertSame(16000, $d['terlaris'][0]['penjualan']);
    }

    public function test_kasir_tidak_melihat_modal_dan_laba(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $this->trx($toko, $kasir, now()->toDateString(), [[1, 5000, 4000]]);

        $d = $this->masukSebagai($kasir)->getJson('/api/penjualan')->assertOk()->json('data');
        $this->assertArrayNotHasKey('laba_kotor', $d['ringkasan']);
        $this->assertArrayNotHasKey('total_modal', $d['kemarin']);
        $this->assertArrayNotHasKey('laba', $d['transaksi'][0]);
        $this->assertArrayNotHasKey('laba', $d['terlaris'][0]);
        $this->assertSame(5000, $d['ringkasan']['total_penjualan']);
    }

    public function test_tanggal_tidak_valid_jatuh_ke_hari_ini(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko))->getJson('/api/penjualan?tanggal=2026-02-30')
            ->assertOk()->assertJsonPath('data.tanggal', now()->toDateString());
    }

    public function test_tutup_buku_menyimpan_snapshot_dan_menolak_dua_kali(): void
    {
        $toko = $this->buatToko();
        $pemilik = $this->buatAkun($toko, 'pemilik');
        $hari = now()->toDateString();
        $this->trx($toko, $pemilik, $hari, [[2, 5000, 4000]]);
        $this->trx($toko, $pemilik, $hari, [[1, 1000, 500]], ['status' => 'dibatalkan']);

        $this->masukSebagai($pemilik)->postJson('/api/penjualan/tutup-buku', ['tanggal' => $hari, 'catatan' => '  uang laci sesuai  '])
            ->assertOk()
            ->assertJsonPath('pesan', "Buku tanggal {$hari} berhasil ditutup.")
            ->assertJsonPath('data.total_penjualan', 10000)
            ->assertJsonPath('data.laba_kotor', 2000);

        $tb = TutupBuku::withoutGlobalScopes()->where('toko_id', $toko->id)->sole();
        $this->assertSame(1, $tb->jumlah_transaksi);
        $this->assertSame(10000, $tb->total_penjualan);
        $this->assertSame(8000, $tb->total_modal);
        $this->assertSame(2000, $tb->laba_kotor);
        $this->assertSame(10000, $tb->uang_tunai);
        $this->assertSame('uang laci sesuai', $tb->catatan);
        $this->assertSame($pemilik->id, $tb->ditutup_oleh);
        $this->assertTrue(TutupBuku::terkunci($hari));

        $this->postJson('/api/penjualan/tutup-buku', ['tanggal' => $hari])
            ->assertStatus(409)->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', "Buku tanggal {$hari} sudah ditutup sebelumnya.");
        $this->assertSame(1, TutupBuku::withoutGlobalScopes()->count());

        // status tutup buku tampil di rekap harian
        $this->getJson('/api/penjualan?tanggal='.$hari)
            ->assertJsonPath('data.tutup_buku.total_penjualan', 10000)
            ->assertJsonPath('data.tutup_buku.ditutup_oleh_nama', $pemilik->nama);
    }

    public function test_tutup_buku_aturan_tanggal(): void
    {
        $toko = $this->buatToko();
        $pemilik = $this->buatAkun($toko, 'pemilik');
        $this->masukSebagai($pemilik);

        $this->postJson('/api/penjualan/tutup-buku', ['tanggal' => now()->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonPath('pesan', 'Tidak bisa menutup buku untuk tanggal yang belum terjadi.');
        $this->postJson('/api/penjualan/tutup-buku', ['tanggal' => '2026-13-01'])
            ->assertStatus(422)->assertJsonPath('pesan', 'Tanggal tidak valid.');
        $this->postJson('/api/penjualan/tutup-buku', [])
            ->assertStatus(422)->assertJsonPath('pesan', 'Tanggal tidak valid.');
        // tanpa transaksi
        $this->postJson('/api/penjualan/tutup-buku', ['tanggal' => now()->subDays(3)->toDateString()])
            ->assertStatus(422)->assertJsonPath('pesan', 'Belum ada transaksi pada tanggal ini, tutup buku tidak diperlukan.');
        // catatan terlalu panjang
        $this->trx($toko, $pemilik, now()->toDateString(), [[1, 1000, 500]]);
        $this->postJson('/api/penjualan/tutup-buku', ['tanggal' => now()->toDateString(), 'catatan' => str_repeat('a', 301)])
            ->assertStatus(422)->assertJsonPath('pesan', 'Catatan maksimal 300 karakter.');
        $this->assertSame(0, TutupBuku::withoutGlobalScopes()->count());
    }

    public function test_tutup_buku_tanggal_lampau_boleh(): void
    {
        $toko = $this->buatToko();
        $pemilik = $this->buatAkun($toko, 'pemilik');
        $lalu = now()->subDays(2)->toDateString();
        $this->trx($toko, $pemilik, $lalu, [[1, 3000, 2000]]);
        $this->masukSebagai($pemilik)->postJson('/api/penjualan/tutup-buku', ['tanggal' => $lalu])->assertOk();
        $this->assertTrue(TutupBuku::terkunci($lalu));
    }

    public function test_kasir_ditolak_tutup_buku(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $this->trx($toko, $kasir, now()->toDateString(), [[1, 1000, 500]]);

        $this->masukSebagai($kasir)->postJson('/api/penjualan/tutup-buku', ['tanggal' => now()->toDateString()])
            ->assertStatus(403)->assertJsonPath('sukses', false);
        $this->assertSame(0, TutupBuku::withoutGlobalScopes()->count());
    }

    public function test_halaman_penjualan_untuk_pemilik_dan_kasir(): void
    {
        $this->withoutVite();
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'kasir'))->get('/penjualan')
            ->assertOk()->assertSee('Penjualan Harian')->assertDontSee('@click="tutup = true"', false);
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'))->get('/penjualan')
            ->assertOk()->assertSee('@click="tutup = true"', false);
    }
}
