<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\MutasiStok;
use App\Models\Opname;
use App\Models\Toko;
use App\Services\MutasiStokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class OpnameTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    private Toko $toko;

    private $pemilik;

    private $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->toko = $this->buatToko();
        $this->pemilik = $this->buatAkun($this->toko, 'pemilik');
        $this->kasir = $this->buatAkun($this->toko, 'kasir');
    }

    private function mulai(array $body = []): int
    {
        return $this->postJson('/api/opname', $body)->assertCreated()->json('data.id');
    }

    private function hitung(int $id, array $item)
    {
        return $this->putJson("/api/opname/$id", ['item' => $item]);
    }

    public function test_halaman_bisa_dibuka_kasir_dan_pemilik(): void
    {
        $this->buatBarang($this->toko);
        $this->masukSebagai($this->kasir);
        $this->get(route('opname'))->assertOk()->assertSee('Stok Opname')->assertSee('ajukan ke pemilik');
        $id = $this->mulai();
        $this->get(route('opname.tampil', $id))->assertOk()->assertSee('Lembar Opname', false);
        $this->masukSebagai($this->pemilik);
        $this->get(route('opname'))->assertOk()->assertSee('setujui atau tolak');
    }

    public function test_mulai_memotret_stok_barang_aktif_dan_kode_unik(): void
    {
        $k = $this->buatKategori($this->toko, 'Minuman');
        $this->buatBarang($this->toko, ['stok' => 5, 'kategori_id' => $k->id]);
        $this->buatBarang($this->toko, ['stok' => 7]);
        $this->buatBarang($this->toko, ['stok' => 9, 'aktif' => false]);
        $this->masukSebagai($this->kasir);

        $id = $this->mulai(['catatan' => '  Rak depan ']);
        $d = $this->getJson("/api/opname/$id")->assertOk()->json('data');
        $tgl = now()->format('Ymd');
        $this->assertSame("OPN-$tgl-01", $d['kode']);
        $this->assertSame('draft', $d['status']);
        $this->assertSame('Rak depan', $d['catatan']);
        $this->assertCount(2, $d['item']);
        $this->assertNull($d['item'][0]['stok_fisik']);

        // tidak boleh ada dua sesi aktif
        $this->postJson('/api/opname', [])->assertStatus(409);

        // setelah dibatalkan, sesi baru per kategori
        $this->deleteJson("/api/opname/$id")->assertOk();
        $id2 = $this->mulai(['kategori_id' => $k->id]);
        $this->assertCount(1, $this->getJson("/api/opname/$id2")->json('data.item'));

        // kode berikut di toko yang sama berurutan; di toko lain mulai dari 01 lagi
        $this->hitung($id2, [['barang_id' => Barang::where('kategori_id', $k->id)->value('id'), 'stok_fisik' => 5, 'alasan' => '']]);
        $this->postJson("/api/opname/$id2/ajukan")->assertOk();
        $this->masukSebagai($this->pemilik);
        $this->postJson("/api/opname/$id2/setujui")->assertOk();
        $this->masukSebagai($this->kasir);
        $id3 = $this->mulai();
        $this->assertSame("OPN-$tgl-02", Opname::find($id3)->kode);

        Auth::logout(); // fixture toko lain dibuat tanpa sesi (MilikToko mengisi toko_id dari sesi)
        $tokoB = $this->buatToko();
        $this->buatBarang($tokoB);
        $this->masukSebagai($this->buatAkun($tokoB, 'kasir'));
        $idB = $this->mulai();
        $this->assertSame("OPN-$tgl-01", $this->getJson("/api/opname/$idB")->json('data.kode'));
    }

    public function test_mulai_tanpa_barang_ditolak(): void
    {
        $this->masukSebagai($this->kasir);
        $this->postJson('/api/opname', [])->assertStatus(422)->assertJson(['pesan' => 'Tidak ada barang untuk dihitung.']);
        $this->assertSame(0, Opname::count());
    }

    public function test_simpan_hitungan_validasi_dan_hitung_selisih(): void
    {
        $b = $this->buatBarang($this->toko, ['stok' => 10]);
        $this->masukSebagai($this->kasir);
        $id = $this->mulai();

        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => -1]])->assertStatus(422)
            ->assertJson(['pesan' => 'Stok fisik harus bilangan bulat 0 atau lebih.']);
        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => '1.5']])->assertStatus(422);
        $this->putJson("/api/opname/$id", ['item' => 'x'])->assertStatus(422)->assertJson(['pesan' => 'Data hitungan tidak valid.']);

        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => '8', 'alasan' => 'rusak']])->assertOk();
        $i = $this->getJson("/api/opname/$id")->json('data.item.0');
        $this->assertSame(8, $i['stok_fisik']);
        $this->assertSame(-2, $i['selisih']);
        $this->assertSame('rusak', $i['alasan']);

        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => '', 'alasan' => '']])->assertOk();
        $this->assertNull($this->getJson("/api/opname/$id")->json('data.item.0.selisih'));
    }

    public function test_ajukan_menolak_belum_dihitung_dan_selisih_tanpa_alasan(): void
    {
        $a = $this->buatBarang($this->toko, ['stok' => 10]);
        $b = $this->buatBarang($this->toko, ['stok' => 4]);
        $this->masukSebagai($this->kasir);
        $id = $this->mulai();

        $this->hitung($id, [['barang_id' => $a->id, 'stok_fisik' => 10]]);
        $this->postJson("/api/opname/$id/ajukan")->assertStatus(422)->assertJson(['pesan' => 'Masih ada 1 barang yang belum dihitung.']);

        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => 3, 'alasan' => '   ']]);
        $this->postJson("/api/opname/$id/ajukan")->assertStatus(422)
            ->assertJson(['pesan' => 'Isi alasan untuk 1 barang yang stoknya selisih.']);

        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => 3, 'alasan' => 'hilang']]);
        $this->postJson("/api/opname/$id/ajukan")->assertOk();
        $o = Opname::find($id);
        $this->assertSame('diajukan', $o->status);
        $this->assertNotNull($o->diajukan_pada);
    }

    public function test_transisi_status_tidak_sah_ditolak(): void
    {
        $b = $this->buatBarang($this->toko, ['stok' => 10]);
        $this->masukSebagai($this->pemilik);
        $id = $this->mulai();

        // tidak bisa memutuskan draft
        $this->postJson("/api/opname/$id/setujui")->assertStatus(409);
        $this->postJson("/api/opname/$id/tolak", ['alasan' => 'x'])->assertStatus(409);

        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => 10]]);
        $this->postJson("/api/opname/$id/ajukan")->assertOk();

        // setelah diajukan: hitungan terkunci, tidak bisa diajukan/dibatalkan lagi
        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => 1]])->assertStatus(409)
            ->assertJson(['pesan' => 'Sesi ini sudah diajukan, hitungan tidak bisa diubah.']);
        $this->postJson("/api/opname/$id/ajukan")->assertStatus(409);
        $this->deleteJson("/api/opname/$id")->assertStatus(409);
        $this->assertSame(10, (int) DB::table('opname_item')->where('opname_id', $id)->value('stok_fisik'));

        $this->postJson("/api/opname/$id/setujui")->assertOk();
        // sudah diputuskan: tidak bisa diputuskan lagi
        $this->postJson("/api/opname/$id/setujui")->assertStatus(409);
        $this->postJson("/api/opname/$id/tolak", ['alasan' => 'x'])->assertStatus(409);
        $this->postJson("/api/opname/$id/lainnya")->assertNotFound();
    }

    public function test_hanya_pemilik_boleh_setujui_dan_tolak(): void
    {
        $b = $this->buatBarang($this->toko, ['stok' => 10]);
        $this->masukSebagai($this->kasir);
        $id = $this->mulai();
        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => 7, 'alasan' => 'rusak']]);
        $this->postJson("/api/opname/$id/ajukan")->assertOk();

        $this->postJson("/api/opname/$id/setujui")->assertForbidden();
        $this->postJson("/api/opname/$id/tolak", ['alasan' => 'salah'])->assertForbidden();
        $this->assertSame('diajukan', Opname::find($id)->status);
        $this->assertSame(10, Barang::find($b->id)->stok);
    }

    public function test_tolak_wajib_alasan_dan_stok_tidak_berubah(): void
    {
        $b = $this->buatBarang($this->toko, ['stok' => 10]);
        $this->masukSebagai($this->kasir);
        $id = $this->mulai();
        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => 7, 'alasan' => 'rusak']]);
        $this->postJson("/api/opname/$id/ajukan");

        $this->masukSebagai($this->pemilik);
        $this->postJson("/api/opname/$id/tolak", ['alasan' => '  '])->assertStatus(422)
            ->assertJson(['pesan' => 'Alasan penolakan wajib diisi.']);
        $this->postJson("/api/opname/$id/tolak", ['alasan' => 'Hitung ulang'])->assertOk();

        $o = Opname::find($id);
        $this->assertSame('ditolak', $o->status);
        $this->assertSame('Hitung ulang', $o->alasan_tolak);
        $this->assertSame($this->pemilik->id, (int) $o->diputuskan_oleh);
        $this->assertSame(10, Barang::find($b->id)->stok);
        $this->assertSame(0, MutasiStok::count());

        // setelah ditolak, sesi baru boleh dimulai
        $this->masukSebagai($this->kasir);
        $this->mulai();
    }

    public function test_setujui_menyesuaikan_stok_dan_mencatat_mutasi_penyesuaian(): void
    {
        $a = $this->buatBarang($this->toko, ['stok' => 10]);
        $b = $this->buatBarang($this->toko, ['stok' => 5]);
        $c = $this->buatBarang($this->toko, ['stok' => 3]);
        $this->masukSebagai($this->kasir);
        $id = $this->mulai();
        $this->hitung($id, [
            ['barang_id' => $a->id, 'stok_fisik' => 8, 'alasan' => 'rusak'],
            ['barang_id' => $b->id, 'stok_fisik' => 6, 'alasan' => 'salah catat'],
            ['barang_id' => $c->id, 'stok_fisik' => 3],
        ]);
        $this->postJson("/api/opname/$id/ajukan")->assertOk();

        $this->masukSebagai($this->pemilik);
        $kode = Opname::find($id)->kode;
        $this->postJson("/api/opname/$id/setujui")->assertOk()
            ->assertJson(['pesan' => "Sesi $kode disetujui. 2 barang disesuaikan stoknya."]);

        $this->assertSame(8, Barang::find($a->id)->stok);
        $this->assertSame(6, Barang::find($b->id)->stok);
        $this->assertSame(3, Barang::find($c->id)->stok);

        $m = MutasiStok::where('barang_id', $a->id)->first();
        $this->assertSame('penyesuaian', $m->jenis);
        $this->assertSame(-2, (int) $m->jumlah);
        $this->assertSame($kode, $m->referensi);
        $this->assertSame("Opname $kode: rusak", $m->keterangan);
        $this->assertSame(2, MutasiStok::where('jenis', 'penyesuaian')->count());

        $o = Opname::find($id);
        $this->assertSame('disetujui', $o->status);
        $this->assertNotNull($o->diputuskan_pada);
    }

    public function test_setujui_menerapkan_selisih_ke_stok_terkini_bila_stok_berubah(): void
    {
        $a = $this->buatBarang($this->toko, ['stok' => 10]);
        $b = $this->buatBarang($this->toko, ['stok' => 5]);
        $this->masukSebagai($this->kasir);
        $id = $this->mulai();
        // fisik a = 8 (selisih −2), fisik b = 1 (selisih −4)
        $this->hitung($id, [
            ['barang_id' => $a->id, 'stok_fisik' => 8, 'alasan' => 'rusak'],
            ['barang_id' => $b->id, 'stok_fisik' => 1, 'alasan' => 'hilang'],
        ]);
        $this->postJson("/api/opname/$id/ajukan")->assertOk();

        // penjualan setelah penghitungan: a −3 (10→7), b −3 (5→2)
        DB::transaction(function () use ($a, $b) {
            $s = app(MutasiStokService::class);
            $s->ubah($this->toko->id, $a->id, $this->kasir->id, 'penjualan', -3);
            $s->ubah($this->toko->id, $b->id, $this->kasir->id, 'penjualan', -3);
        });

        $this->masukSebagai($this->pemilik);
        $this->postJson("/api/opname/$id/setujui")->assertOk();
        // a: 7 + (−2) = 5 — penjualan tidak ikut terhapus
        $this->assertSame(5, Barang::find($a->id)->stok);
        // b: 2 + max(−4, −2) = 0 — tidak boleh minus
        $this->assertSame(0, Barang::find($b->id)->stok);
        $this->assertSame(-2, (int) MutasiStok::where('barang_id', $b->id)->where('jenis', 'penyesuaian')->value('jumlah'));
    }

    public function test_daftar_dan_ringkasan_status(): void
    {
        $b = $this->buatBarang($this->toko, ['stok' => 10]);
        $this->masukSebagai($this->kasir);
        $id = $this->mulai();
        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => 9, 'alasan' => 'x']]);
        $this->postJson("/api/opname/$id/ajukan");

        $d = $this->getJson('/api/opname')->assertOk()->json('data');
        $this->assertSame(['diajukan' => 1], $d['ringkasan']);
        $this->assertSame(1, $d['opname'][0]['jumlah_barang']);
        $this->assertSame(1, $d['opname'][0]['sudah_dihitung']);
        $this->assertSame(1, $d['opname'][0]['barang_selisih']);
        $this->assertSame($this->kasir->nama, $d['opname'][0]['dibuat_oleh']);
        $this->assertCount(0, $this->getJson('/api/opname?status=draft')->json('data.opname'));
        $this->assertCount(1, $this->getJson('/api/opname?status=diajukan')->json('data.opname'));
    }

    public function test_isolasi_antar_toko(): void
    {
        $b = $this->buatBarang($this->toko, ['stok' => 10]);
        $this->masukSebagai($this->kasir);
        $id = $this->mulai();

        $tokoB = $this->buatToko();
        $pemilikB = $this->buatAkun($tokoB, 'pemilik');
        Auth::logout();
        $this->masukSebagai($pemilikB);
        $this->getJson("/api/opname/$id")->assertNotFound();
        $this->hitung($id, [['barang_id' => $b->id, 'stok_fisik' => 1]])->assertNotFound();
        $this->postJson("/api/opname/$id/ajukan")->assertNotFound();
        $this->postJson("/api/opname/$id/setujui")->assertNotFound();
        $this->deleteJson("/api/opname/$id")->assertStatus(409);
        $this->assertCount(0, $this->getJson('/api/opname')->json('data.opname'));
        $this->assertNotNull(Opname::withoutGlobalScope('toko')->find($id));
    }
}
