<?php

namespace Tests\Feature;

use App\Models\Hutang;
use App\Models\ItemTransaksi;
use App\Models\MutasiStok;
use App\Models\TutupBuku;
use App\Services\MutasiStokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class KasirTransaksiTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function bayar(array $body)
    {
        return $this->postJson('/api/transaksi', $body);
    }

    public function test_halaman_kasir_tampil_dan_wajib_login(): void
    {
        $this->get('/kasir')->assertRedirect('/masuk');
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'kasir'))
            ->get('/kasir')->assertOk()->assertSee('Keranjang')->assertDontSee('Buku hari ini sudah ditutup oleh pemilik');
    }

    public function test_halaman_kasir_memberi_tanda_bila_hari_ini_tutup_buku(): void
    {
        $toko = $this->buatToko();
        $p = $this->buatAkun($toko, 'pemilik');
        $this->tutupBuku($toko->id, $p->id, now()->toDateString());
        $this->masukSebagai($p)->get('/kasir')->assertOk()->assertSee('Buku hari ini sudah ditutup oleh pemilik');
    }

    public function test_transaksi_tunai_atomik_harga_dihitung_server(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 10, 'harga_jual' => 5000, 'harga_modal' => 4000, 'nama_barang' => 'Teh']);
        $b = $this->buatBarang($toko, ['stok' => 3, 'harga_jual' => 12000, 'harga_modal' => 9000, 'nama_barang' => 'Kopi']);

        $r = $this->masukSebagai($kasir)->bayar([
            // harga/total/subtotal dari klien harus diabaikan
            'item' => [
                ['barang_id' => $a->id, 'jumlah' => 2, 'harga_jual' => 1],
                ['barang_id' => $b->id, 'jumlah' => 1, 'harga_jual' => 1],
                ['barang_id' => $a->id, 'jumlah' => 1],
            ],
            'metode_bayar' => 'tunai', 'uang_bayar' => 30000, 'total' => 1,
        ]);

        $r->assertCreated()->assertJsonPath('sukses', true)->assertJsonPath('pesan', 'Transaksi berhasil disimpan.')
            ->assertJsonPath('data.total', 27000)->assertJsonPath('data.kembalian', 3000)
            ->assertJsonPath('data.uang_bayar', 30000)->assertJsonPath('data.metode_bayar', 'tunai')
            ->assertJsonPath('data.item.0.jumlah', 3)->assertJsonPath('data.item.0.subtotal', 15000)
            ->assertJsonMissingPath('data.total_modal');

        $nomor = $r->json('data.nomor_transaksi');
        $this->assertMatchesRegularExpression('/^TRX-\d{8}-0001$/', $nomor);
        $this->assertSame(7, $a->fresh()->stok);
        $this->assertSame(2, $b->fresh()->stok);

        $trx = DB::table('transaksi')->where('nomor_transaksi', $nomor)->first();
        $this->assertSame(21000, (int) $trx->total_modal);
        $this->assertSame((int) $toko->id, (int) $trx->toko_id);
        $this->assertSame(2, ItemTransaksi::where('transaksi_id', $trx->id)->count());

        $mutasi = MutasiStok::withoutGlobalScopes()->where('referensi', $nomor)->orderBy('id')->get();
        $this->assertCount(2, $mutasi);
        $this->assertSame(['penjualan', 'penjualan'], $mutasi->pluck('jenis')->all());
        $this->assertSame(-3, (int) $mutasi[0]->jumlah);
        $this->assertSame(10, (int) $mutasi[0]->stok_sebelum);
        $this->assertSame(7, (int) $mutasi[0]->stok_sesudah);
    }

    public function test_pemilik_pun_tidak_dapat_total_modal_di_respons_bayar_tapi_dapat_di_detail(): void
    {
        $toko = $this->buatToko();
        $pemilik = $this->buatAkun($toko, 'pemilik');
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 5]);

        $nomor = $this->masukSebagai($pemilik)->bayar(['item' => [['barang_id' => $a->id, 'jumlah' => 1]], 'metode_bayar' => 'qris'])
            ->assertCreated()->assertJsonMissingPath('data.total_modal')->assertJsonPath('data.uang_bayar', 5000)
            ->json('data.nomor_transaksi');

        $this->getJson("/api/transaksi/{$nomor}")->assertOk()->assertJsonPath('data.total_modal', 4000);
        $this->masukSebagai($kasir)->getJson("/api/transaksi/{$nomor}")->assertOk()->assertJsonMissingPath('data.total_modal');
        $this->getJson('/api/transaksi')->assertOk()->assertJsonMissingPath('data.transaksi.0.total_modal');
    }

    public function test_stok_tidak_cukup_422_tanpa_perubahan(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 10]);
        $b = $this->buatBarang($toko, ['stok' => 1, 'nama_barang' => 'Gula', 'satuan' => 'kg']);

        $this->masukSebagai($kasir)->bayar([
            'item' => [['barang_id' => $a->id, 'jumlah' => 2], ['barang_id' => $b->id, 'jumlah' => 2]],
            'metode_bayar' => 'tunai', 'uang_bayar' => 100000,
        ])->assertStatus(422)->assertJsonPath('sukses', false)->assertJsonPath('pesan', 'Stok Gula tidak cukup. Sisa 1 kg.');

        $this->assertSame(10, $a->fresh()->stok);
        $this->assertSame(1, $b->fresh()->stok);
        $this->assertSame(0, DB::table('transaksi')->count());
        $this->assertSame(0, DB::table('item_transaksi')->count());
        $this->assertSame(0, DB::table('mutasi_stok')->count());
    }

    public function test_gagal_di_tengah_rollback_semua(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 10]);
        $b = $this->buatBarang($toko, ['stok' => 10]);

        // Mutasi stok barang kedua gagal (setelah transaksi, item pertama & stok pertama sudah ditulis)
        $this->app->instance(MutasiStokService::class, new class extends MutasiStokService
        {
            private int $n = 0;

            public function ubah(int $tokoId, int $barangId, ?int $penggunaId, string $jenis, int $perubahan, string $keterangan = '', string $referensi = ''): array
            {
                if (++$this->n === 2) {
                    throw new \RuntimeException('Simulasi gangguan database');
                }

                return parent::ubah($tokoId, $barangId, $penggunaId, $jenis, $perubahan, $keterangan, $referensi);
            }
        });

        $this->masukSebagai($kasir)->bayar([
            'item' => [['barang_id' => $a->id, 'jumlah' => 2], ['barang_id' => $b->id, 'jumlah' => 3]],
            'metode_bayar' => 'hutang', 'nama_pelanggan' => 'Bu Sri',
        ])->assertStatus(500);

        $this->assertSame(10, $a->fresh()->stok);
        $this->assertSame(10, $b->fresh()->stok);
        foreach (['transaksi', 'item_transaksi', 'mutasi_stok', 'hutang'] as $tabel) {
            $this->assertSame(0, DB::table($tabel)->count(), "Tabel {$tabel} harus kosong setelah rollback");
        }
    }

    public function test_barang_toko_lain_ditolak(): void
    {
        $tokoA = $this->buatToko();
        $tokoB = $this->buatToko();
        $kasirA = $this->buatAkun($tokoA, 'kasir');
        $milikB = $this->buatBarang($tokoB, ['stok' => 10]);

        $this->masukSebagai($kasirA)->bayar([
            'item' => [['barang_id' => $milikB->id, 'jumlah' => 1]], 'metode_bayar' => 'tunai', 'uang_bayar' => 10000,
        ])->assertStatus(422)->assertJsonPath('pesan', 'Ada barang yang tidak ditemukan atau sudah tidak dijual. Muat ulang daftar barang.');

        $this->assertSame(10, $milikB->fresh()->stok);
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_barang_nonaktif_ditolak(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 10, 'aktif' => false]);

        $this->masukSebagai($kasir)->bayar(['item' => [['barang_id' => $a->id, 'jumlah' => 1]], 'metode_bayar' => 'transfer'])
            ->assertStatus(422);
    }

    public function test_hari_tutup_buku_ditolak(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 10]);
        $this->tutupBuku($toko->id, $kasir->id, now()->toDateString());

        $this->masukSebagai($kasir)->bayar([
            'item' => [['barang_id' => $a->id, 'jumlah' => 1]], 'metode_bayar' => 'tunai', 'uang_bayar' => 5000,
        ])->assertStatus(409)->assertJsonPath('pesan', 'Buku hari ini sudah ditutup. Transaksi baru tidak bisa disimpan.');

        $this->assertSame(10, $a->fresh()->stok);
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_tutup_buku_toko_lain_tidak_berpengaruh(): void
    {
        $tokoA = $this->buatToko();
        $tokoB = $this->buatToko();
        $kasirA = $this->buatAkun($tokoA, 'kasir');
        $pemilikB = $this->buatAkun($tokoB, 'pemilik');
        $this->tutupBuku($tokoB->id, $pemilikB->id, now()->toDateString());
        $a = $this->buatBarang($tokoA, ['stok' => 10]);

        $this->masukSebagai($kasirA)->bayar(['item' => [['barang_id' => $a->id, 'jumlah' => 1]], 'metode_bayar' => 'qris'])
            ->assertCreated();
    }

    public function test_nomor_transaksi_unik_per_toko(): void
    {
        $tokoA = $this->buatToko();
        $tokoB = $this->buatToko();
        $kasirA = $this->buatAkun($tokoA, 'kasir');
        $kasirB = $this->buatAkun($tokoB, 'kasir');
        $a = $this->buatBarang($tokoA, ['stok' => 10]);
        $b = $this->buatBarang($tokoB, ['stok' => 10]);
        $tgl = now()->format('Ymd');
        $body = fn ($id) => ['item' => [['barang_id' => $id, 'jumlah' => 1]], 'metode_bayar' => 'qris'];

        $n1 = $this->masukSebagai($kasirA)->bayar($body($a->id))->assertCreated()->json('data.nomor_transaksi');
        $n2 = $this->bayar($body($a->id))->assertCreated()->json('data.nomor_transaksi');
        $n3 = $this->masukSebagai($kasirB)->bayar($body($b->id))->assertCreated()->json('data.nomor_transaksi');

        $this->assertSame("TRX-{$tgl}-0001", $n1);
        $this->assertSame("TRX-{$tgl}-0002", $n2);
        $this->assertSame("TRX-{$tgl}-0001", $n3); // urutan terpisah per toko
    }

    public function test_hutang_terbuat_dengan_uang_muka(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 10, 'harga_jual' => 10000]);

        $r = $this->masukSebagai($kasir)->bayar([
            'item' => [['barang_id' => $a->id, 'jumlah' => 3]], 'metode_bayar' => 'hutang',
            'uang_bayar' => 5000, 'nama_pelanggan' => '  Pak Budi ', 'kontak' => '0812',
        ])->assertCreated()->assertJsonPath('data.metode_bayar', 'hutang')->assertJsonPath('data.uang_bayar', 5000)
            ->assertJsonPath('data.kembalian', 0)->assertJsonPath('data.nama_pelanggan', 'Pak Budi');

        $h = Hutang::withoutGlobalScopes()->sole();
        $this->assertSame(25000, $h->jumlah);
        $this->assertSame('Pak Budi', $h->nama_pelanggan);
        $this->assertSame('0812', $h->kontak);
        $this->assertSame((int) $toko->id, (int) $h->toko_id);
        $this->assertSame('Dari transaksi '.$r->json('data.nomor_transaksi'), $h->catatan);
        $this->assertNotNull($h->transaksi_id);
    }

    public function test_validasi_hutang_dan_tunai(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 10, 'harga_jual' => 10000]);
        $item = [['barang_id' => $a->id, 'jumlah' => 1]];
        $this->masukSebagai($kasir);

        $this->bayar(['item' => $item, 'metode_bayar' => 'hutang'])->assertStatus(422)->assertJsonPath('pesan', 'Nama pelanggan wajib diisi.');
        $this->bayar(['item' => $item, 'metode_bayar' => 'hutang', 'nama_pelanggan' => 'X', 'uang_bayar' => 10000])
            ->assertStatus(422)->assertJsonPath('pesan', 'Uang muka sudah menutup total. Pilih metode Tunai.');
        $this->bayar(['item' => $item, 'metode_bayar' => 'tunai', 'uang_bayar' => 7500])
            ->assertStatus(422)->assertJsonPath('pesan', 'Uang pembayaran kurang Rp2.500.');
        $this->bayar(['item' => $item, 'metode_bayar' => 'tunai'])->assertStatus(422)->assertJsonPath('pesan', 'Uang bayar wajib diisi.');
        $this->bayar(['item' => [], 'metode_bayar' => 'tunai'])->assertStatus(422)->assertJsonPath('pesan', 'Keranjang belanja masih kosong.');
        $this->bayar(['item' => [['barang_id' => 'abc', 'jumlah' => 1]]])->assertStatus(422)->assertJsonPath('pesan', 'Ada barang yang tidak valid di keranjang.');
        $this->bayar(['item' => [['barang_id' => $a->id, 'jumlah' => 0]]])->assertStatus(422)->assertJsonPath('pesan', 'Jumlah barang minimal 1.');
        $this->bayar(['item' => [['barang_id' => $a->id, 'jumlah' => 1.5]]])->assertStatus(422)->assertJsonPath('pesan', 'Jumlah barang harus berupa bilangan bulat.');
        $this->call('POST', '/api/transaksi', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], 'bukan json')
            ->assertStatus(400)->assertJsonPath('pesan', 'Format data tidak valid.');

        $this->assertSame(10, $a->fresh()->stok);
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_metode_tak_dikenal_jadi_tunai(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $a = $this->buatBarang($toko, ['stok' => 10]);

        $this->masukSebagai($kasir)->bayar(['item' => [['barang_id' => $a->id, 'jumlah' => 1]], 'metode_bayar' => 'bitcoin', 'uang_bayar' => '5.000'])
            ->assertCreated()->assertJsonPath('data.metode_bayar', 'tunai')->assertJsonPath('data.uang_bayar', 5000);
    }

    public function test_riwayat_transaksi_terfilter_toko_tanggal_dan_kata_kunci(): void
    {
        $tokoA = $this->buatToko();
        $tokoB = $this->buatToko();
        $kasirA = $this->buatAkun($tokoA, 'kasir');
        $kasirB = $this->buatAkun($tokoB, 'kasir');
        $a = $this->buatBarang($tokoA, ['stok' => 10]);
        $b = $this->buatBarang($tokoB, ['stok' => 10]);

        $this->masukSebagai($kasirB)->bayar(['item' => [['barang_id' => $b->id, 'jumlah' => 1]], 'metode_bayar' => 'qris'])->assertCreated();
        $this->masukSebagai($kasirA)->bayar(['item' => [['barang_id' => $a->id, 'jumlah' => 2]], 'metode_bayar' => 'qris'])->assertCreated();
        $this->bayar(['item' => [['barang_id' => $a->id, 'jumlah' => 1]], 'metode_bayar' => 'hutang', 'nama_pelanggan' => 'Mbak Ani'])->assertCreated();

        $r = $this->getJson('/api/transaksi')->assertOk()
            ->assertJsonPath('data.total', 2)->assertJsonPath('data.nilai', 15000)
            ->assertJsonPath('data.halaman', 1)->assertJsonPath('data.per_halaman', 25)
            ->assertJsonPath('data.dari', now()->toDateString())->assertJsonPath('data.sampai', now()->toDateString());
        $this->assertSame('Mbak Ani', $r->json('data.transaksi.0.nama_pelanggan'));
        $this->assertSame(2, $r->json('data.transaksi.1.jumlah_item'));
        $this->assertSame($kasirA->nama, $r->json('data.transaksi.0.kasir'));

        $this->getJson('/api/transaksi?q=ani')->assertOk()->assertJsonPath('data.total', 1);
        $kemarin = now()->subDay()->toDateString();
        $this->getJson("/api/transaksi?dari={$kemarin}&sampai={$kemarin}")->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/transaksi?dari=2026-02-31')->assertOk()->assertJsonPath('data.dari', now()->toDateString());
    }

    private function tutupBuku(int $tokoId, int $penggunaId, string $tanggal): void
    {
        $t = new TutupBuku(['tanggal' => $tanggal, 'jumlah_transaksi' => 0, 'total_penjualan' => 0, 'total_modal' => 0, 'laba_kotor' => 0, 'ditutup_oleh' => $penggunaId, 'ditutup_pada' => now()]);
        $t->toko_id = $tokoId;
        $t->save();
    }
}
