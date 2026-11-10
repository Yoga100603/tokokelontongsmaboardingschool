<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Toko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class StrukTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return array{0:Toko,1:Pengguna,2:string} */
    private function siapkan(): array
    {
        $toko = $this->buatToko(['nama' => 'Warung Maju', 'catatan_struk' => 'Terima kasih']);
        $kasir = $this->buatAkun($toko, 'kasir', ['nama' => 'Kasir Sari']);
        $b = $this->buatBarang($toko, ['stok' => 10, 'harga_jual' => 7000, 'nama_barang' => 'Mie Goreng', 'satuan' => 'bks']);
        $nomor = $this->masukSebagai($kasir)->postJson('/api/transaksi', [
            'item' => [['barang_id' => $b->id, 'jumlah' => 2]], 'metode_bayar' => 'tunai', 'uang_bayar' => 20000,
        ])->assertCreated()->json('data.nomor_transaksi');

        return [$toko, $kasir, $nomor];
    }

    public function test_halaman_riwayat_dan_detail_tampil(): void
    {
        [, $kasir, $nomor] = $this->siapkan();
        $this->get('/struk')->assertOk()->assertSee('Riwayat Struk')->assertSee('riwayatStruk');
        $this->get(route('struk.tampil', $nomor))->assertOk()->assertSee($nomor)->assertSee('aksiStruk');
    }

    public function test_detail_struk_json_beserta_teks(): void
    {
        [, , $nomor] = $this->siapkan();
        $r = $this->getJson("/api/transaksi/{$nomor}")->assertOk()
            ->assertJsonPath('data.nomor_transaksi', $nomor)->assertJsonPath('data.total', 14000)
            ->assertJsonPath('data.kembalian', 6000)->assertJsonPath('data.kasir', 'Kasir Sari')
            ->assertJsonPath('data.toko.nama', 'Warung Maju')->assertJsonPath('data.item.0.nama_barang', 'Mie Goreng')
            ->assertJsonPath('data.sudah_tutup_buku', false)->assertJsonMissingPath('data.total_modal');
        $teks = $r->json('data.teks');
        $this->assertStringContainsString('WARUNG MAJU', $teks);
        $this->assertStringContainsString('2 bks x Rp7.000 = Rp14.000', $teks);
        $this->assertStringContainsString('Terima kasih', $teks);
    }

    public function test_unduh_txt(): void
    {
        [, , $nomor] = $this->siapkan();
        $r = $this->get("/api/transaksi/{$nomor}?format=txt")->assertOk();
        $this->assertStringStartsWith('text/plain', $r->headers->get('Content-Type'));
        $this->assertStringContainsString("struk-{$nomor}.txt", $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('TOTAL    : Rp14.000', $r->getContent());
    }

    public function test_struk_tidak_ditemukan_dan_toko_lain_terisolasi(): void
    {
        [, , $nomor] = $this->siapkan();
        $this->getJson('/api/transaksi/TRX-TIDAK-ADA')->assertNotFound()->assertJsonPath('pesan', 'Struk tidak ditemukan.');

        $lain = $this->buatAkun($this->buatToko(), 'pemilik');
        $this->masukSebagai($lain);
        $this->getJson("/api/transaksi/{$nomor}")->assertNotFound();
        $this->postJson("/api/transaksi/{$nomor}/log", ['aksi' => 'cetak'])->assertNotFound();
        $this->getJson('/api/transaksi')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame(0, DB::table('log_struk')->count());
    }

    public function test_log_aksi_struk(): void
    {
        [$toko, $kasir, $nomor] = $this->siapkan();
        $this->postJson("/api/transaksi/{$nomor}/log", ['aksi' => 'cetak', 'tujuan' => '80mm'])
            ->assertOk()->assertJsonPath('pesan', 'Tercatat.')->assertJsonPath('data', null);
        $this->postJson("/api/transaksi/{$nomor}/log", ['aksi' => 'kirim', 'tujuan' => 'wa:628123456789'])->assertOk();
        $this->postJson("/api/transaksi/{$nomor}/log", ['aksi' => 'hapus'])
            ->assertStatus(422)->assertJsonPath('pesan', 'Aksi struk tidak dikenal.');
        $this->postJson("/api/transaksi/{$nomor}/log", ['aksi' => 'simpan', 'tujuan' => str_repeat('x', 61)])
            ->assertStatus(422)->assertJsonPath('pesan', 'Tujuan maksimal 60 karakter.');

        $log = DB::table('log_struk')->orderBy('id')->get();
        $this->assertCount(2, $log);
        $this->assertSame((int) $toko->id, (int) $log[0]->toko_id);
        $this->assertSame((int) $kasir->id, (int) $log[0]->pengguna_id);

        $this->getJson("/api/transaksi/{$nomor}")->assertJsonPath('data.log.0.aksi', 'kirim')
            ->assertJsonPath('data.log.1.tujuan', '80mm')->assertJsonPath('data.log.0.oleh', 'Kasir Sari');
        $this->getJson('/api/transaksi')->assertJsonPath('data.transaksi.0.jumlah_log', 2);
    }
}
