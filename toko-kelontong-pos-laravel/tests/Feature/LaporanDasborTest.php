<?php

namespace Tests\Feature;

use App\Http\Controllers\LaporanController;
use App\Models\Hutang;
use App\Models\ItemTransaksi;
use App\Models\Pengguna;
use App\Models\Toko;
use App\Models\Transaksi;
use App\Models\TutupBuku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class LaporanDasborTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    /** $item = [[nama, jumlah, harga_jual, harga_modal], ...] */
    private function trx(Toko $toko, Pengguna $p, string $tanggal, array $item, array $ubah = []): Transaksi
    {
        $total = array_sum(array_map(fn ($i) => $i[1] * $i[2], $item));
        $modal = array_sum(array_map(fn ($i) => $i[1] * $i[3], $item));
        $t = new Transaksi(array_merge([
            'pengguna_id' => $p->id, 'nomor_transaksi' => 'TRX-'.uniqid(), 'tanggal' => $tanggal,
            'waktu' => $tanggal.' 09:30:00', 'total' => $total, 'total_modal' => $modal,
            'uang_bayar' => $total, 'kembalian' => 0, 'metode_bayar' => 'tunai', 'nama_pelanggan' => '', 'status' => 'selesai',
        ], $ubah));
        $t->toko_id = $toko->id;
        $t->save();
        foreach ($item as [$nama, $jml, $jual, $mdl]) {
            ItemTransaksi::create([
                'transaksi_id' => $t->id, 'nama_barang' => $nama, 'satuan' => 'pcs',
                'jumlah' => $jml, 'harga_jual' => $jual, 'harga_modal' => $mdl, 'subtotal' => $jml * $jual,
            ]);
        }

        return $t;
    }

    private function siapkan(): array
    {
        $toko = $this->buatToko(['nama' => 'Warung Bu Sri']);
        $pemilik = $this->buatAkun($toko, 'pemilik');
        $hari = now()->toDateString();
        $this->trx($toko, $pemilik, $hari, [['Gula', 2, 15000, 12000], ['Teh', 1, 5000, 3000]]);
        $this->trx($toko, $pemilik, now()->subDays(2)->toDateString(), [['Gula', 1, 15000, 12000]], ['metode_bayar' => 'transfer']);
        $this->trx($toko, $pemilik, $hari, [['Rokok', 10, 30000, 1000]], ['status' => 'dibatalkan']);
        // periode sebelumnya (8 hari lalu, di luar 7 hari)
        $this->trx($toko, $pemilik, now()->subDays(8)->toDateString(), [['Teh', 4, 5000, 3000]]);
        $lain = $this->buatToko();
        $this->trx($lain, $this->buatAkun($lain), $hari, [['Beras', 3, 60000, 50000]]);

        return [$toko, $pemilik];
    }

    public function test_laporan_rentang_default_7_hari(): void
    {
        [, $pemilik] = $this->siapkan();
        $d = $this->masukSebagai($pemilik)->getJson('/api/laporan')->assertOk()->json('data');

        $this->assertSame(now()->toDateString(), $d['sampai']);
        $this->assertSame(now()->subDays(6)->toDateString(), $d['dari']);
        $this->assertSame([
            'jumlah_transaksi' => 2, 'total_penjualan' => 50000, 'total_modal' => 39000,
            'laba_kotor' => 11000, 'item_terjual' => 4, 'uang_tunai' => 50000, 'rata_rata_transaksi' => 25000,
        ], $d['ringkasan']);
        $this->assertSame(20000, $d['periode_sebelumnya']['total_penjualan']);
        $this->assertCount(7, $d['harian']);
        $this->assertSame(['tanggal' => now()->toDateString(), 'jumlah_transaksi' => 1, 'penjualan' => 35000, 'laba' => 8000], $d['harian'][6]);
        $this->assertSame(0, $d['harian'][0]['penjualan']);
        $this->assertSame('Gula', $d['terlaris'][0]['nama_barang']);
        $this->assertSame(3, $d['terlaris'][0]['jumlah']);
        $this->assertSame(9000, $d['terlaris'][0]['laba']);
        $this->assertNotContains('Beras', array_column($d['terlaris'], 'nama_barang'));
        $this->assertNotContains('Rokok', array_column($d['terlaris'], 'nama_barang'));
        $this->assertSame('tunai', $d['per_metode'][0]['metode_bayar']);
    }

    public function test_validasi_rentang_tanggal(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko));

        $this->getJson('/api/laporan?dari=2026-05-10&sampai=2026-05-01')
            ->assertStatus(422)->assertJsonPath('pesan', 'Tanggal awal harus sebelum tanggal akhir.');
        $this->getJson('/api/laporan?dari=2025-01-01&sampai=2026-01-05')
            ->assertStatus(422)->assertJsonPath('pesan', 'Rentang laporan maksimal 1 tahun.');
        $this->getJson('/api/laporan/unduh?dari=2026-05-10&sampai=2026-05-01')->assertStatus(422);
        $this->getJson('/api/laporan?dari=2025-01-01&sampai=2026-01-01')->assertOk()->assertJsonCount(366, 'data.harian');
    }

    public function test_unduh_csv(): void
    {
        [$toko, $pemilik] = $this->siapkan();
        $this->trx($toko, $pemilik, now()->toDateString(), [['=HYPERLINK("http://jahat")', 1, 1000, 500]], [
            'nama_pelanggan' => '@SUM(A1)', 'nomor_transaksi' => 'TRX-CSV',
        ]);
        $dari = now()->subDays(6)->toDateString();
        $sampai = now()->toDateString();

        $res = $this->masukSebagai($pemilik)->get("/api/laporan/unduh?dari={$dari}&sampai={$sampai}")->assertOk();
        $this->assertSame('text/csv; charset=utf-8', $res->headers->get('Content-Type'));
        $this->assertSame("attachment; filename=\"laporan-penjualan-{$dari}_{$sampai}.csv\"", $res->headers->get('Content-Disposition'));

        $isi = $res->getContent();
        $this->assertStringStartsWith("\u{FEFF}", $isi);
        $baris = explode("\r\n", substr($isi, 3));
        $this->assertSame('"LAPORAN PENJUALAN";"Warung Bu Sri"', $baris[0]);
        $this->assertSame("\"Periode\";\"{$dari} s/d {$sampai}\"", $baris[1]);
        $this->assertSame('', $baris[2]);
        $this->assertSame('"Jumlah transaksi";"3"', $baris[3]);
        $this->assertSame('"Total penjualan";"51000"', $baris[5]);
        $this->assertSame('"Laba kotor";"11500"', $baris[7]);
        // sel berawalan = / @ dinetralkan dengan kutip tunggal, kutip ganda di-escape
        $this->assertStringContainsString('"\'=HYPERLINK(""http://jahat"")"', $isi);
        $this->assertStringContainsString('"TRX-CSV";"'.now()->toDateString().' 09:30:00";"Pemilik Uji";"tunai";"\'@SUM(A1)";"1";"1000";"500";"500"', $isi);
        $this->assertStringNotContainsString('Beras', $isi);
        $this->assertStringNotContainsString('Rokok', $isi);
    }

    public function test_sel_csv_menetralkan_formula(): void
    {
        $this->assertSame('"\'=1+1"', LaporanController::sel('=1+1'));
        $this->assertSame('"\'+62812"', LaporanController::sel('+62812'));
        $this->assertSame('"\'-A1"', LaporanController::sel('-A1'));
        $this->assertSame('"\'@x"', LaporanController::sel('@x'));
        $this->assertSame("\"'\tx\"", LaporanController::sel("\tx"));
        $this->assertSame("\"'\rx\"", LaporanController::sel("\rx"));
        $this->assertSame('"-1500"', LaporanController::sel(-1500));
        $this->assertSame('"-12.5"', LaporanController::sel('-12.5'));
        $this->assertSame('""', LaporanController::sel(null));
        $this->assertSame('"a""b"', LaporanController::sel('a"b'));
    }

    public function test_dasbor(): void
    {
        [$toko, $pemilik] = $this->siapkan();
        $this->buatBarang($toko, ['nama_barang' => 'Kopi', 'stok' => 0, 'stok_minimum' => 2]);
        $this->buatBarang($toko, ['nama_barang' => 'Susu', 'stok' => 2, 'stok_minimum' => 2]);
        $this->buatBarang($toko, ['nama_barang' => 'Aman', 'stok' => 50, 'stok_minimum' => 2]);
        $this->buatBarang($toko, ['nama_barang' => 'Nonaktif', 'stok' => 0, 'aktif' => false]);
        $h = new Hutang(['nama_pelanggan' => 'Pak Budi', 'jumlah' => 25000]);
        $h->toko_id = $toko->id;
        $h->save();
        // tanggal 8 hari lalu sudah ditutup → tidak muncul di belum_tutup_buku
        $tb = new TutupBuku(['tanggal' => now()->subDays(8)->toDateString(), 'jumlah_transaksi' => 1, 'total_penjualan' => 20000,
            'total_modal' => 12000, 'laba_kotor' => 8000, 'uang_tunai' => 20000, 'catatan' => '', 'ditutup_oleh' => $pemilik->id, 'ditutup_pada' => now()]);
        $tb->toko_id = $toko->id;
        $tb->save();

        $d = $this->masukSebagai($pemilik)->getJson('/api/dasbor')->assertOk()->json('data');
        $this->assertSame(now()->toDateString(), $d['tanggal']);
        $this->assertSame(35000, $d['hari_ini']['total_penjualan']);
        $this->assertSame(8000, $d['hari_ini']['laba_kotor']);
        $this->assertSame(0, $d['kemarin']['jumlah_transaksi']);
        $this->assertCount(7, $d['grafik']);
        $this->assertSame(35000, $d['grafik'][6]['penjualan']);
        $this->assertSame(1, $d['grafik'][6]['jumlah']);
        $this->assertSame(15000, $d['grafik'][4]['penjualan']);
        $this->assertSame(['Kopi', 'Susu'], array_column($d['stok_menipis'], 'nama_barang'));
        $this->assertSame([['tanggal' => now()->subDays(2)->toDateString(), 'jumlah' => 1]], $d['belum_tutup_buku']);
        $this->assertFalse($d['hari_ini_ditutup']);
        $this->assertSame(['jumlah' => 1, 'total' => 25000], $d['hutang']);
        $this->assertSame([], $d['opname_menunggu']);
        $this->assertSame('Gula', $d['terlaris'][0]['nama_barang']);
    }

    public function test_kasir_ditolak_endpoint_pemilik(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'kasir'));
        foreach (['/api/laporan', '/api/laporan/unduh', '/api/dasbor'] as $url) {
            $this->getJson($url)->assertStatus(403)->assertJsonPath('sukses', false);
        }
        $this->get('/laporan')->assertRedirect();
        $this->get('/dasbor')->assertRedirect();
    }

    public function test_halaman_laporan_dan_dasbor_pemilik(): void
    {
        $this->withoutVite();
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'pemilik', ['nama' => 'Sri Wahyuni']));
        $this->get('/laporan')->assertOk()->assertSee('Laporan Penjualan')->assertSee('Unduh (Excel/CSV)');
        $this->get('/dasbor')->assertOk()->assertSee('Halo, Sri');
    }
}
