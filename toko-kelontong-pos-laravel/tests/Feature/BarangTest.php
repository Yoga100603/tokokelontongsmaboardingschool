<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\MutasiStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class BarangTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    private function dataBarang(array $ubah = []): array
    {
        return array_merge([
            'kode_barang' => 'BRG001', 'nama_barang' => 'Indomie Goreng', 'satuan' => 'bks',
            'kategori_id' => '', 'harga_jual' => 3500, 'harga_modal' => 3000, 'stok' => 0,
            'stok_minimum' => 5, 'tampil_di_katalog' => true,
        ], $ubah);
    }

    public function test_halaman_barang_bisa_dibuka_pemilik_dan_kasir(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'))->get('/barang')
            ->assertOk()->assertSee('Barang &amp; Stok', false)->assertSee('Tambah Barang')->assertSee('Nilai stok (harga modal)');

        $this->post('/keluar');
        $this->masukSebagai($this->buatAkun($toko, 'kasir'))->get('/barang')
            ->assertOk()->assertDontSee('Tambah Barang')->assertDontSee('Nilai stok (harga modal)');
    }

    public function test_crud_barang_oleh_pemilik(): void
    {
        $toko = $this->buatToko();
        $p = $this->buatAkun($toko, 'pemilik');
        $k = $this->buatKategori($toko, 'Mi Instan');
        $this->masukSebagai($p);

        $id = $this->postJson('/api/barang', $this->dataBarang(['kategori_id' => (string) $k->id, 'harga_jual' => '3.500']))
            ->assertStatus(201)->assertJson(['sukses' => true, 'pesan' => 'Barang baru ditambahkan.'])
            ->json('data.id');

        $b = Barang::find($id);
        $this->assertSame($toko->id, $b->toko_id);
        $this->assertSame(3500, $b->harga_jual);
        $this->assertSame($k->id, $b->kategori_id);
        $this->assertSame(0, MutasiStok::where('barang_id', $id)->count()); // stok awal 0 → tanpa mutasi

        $this->getJson("/api/barang/$id")->assertOk()
            ->assertJsonPath('data.kategori', 'Mi Instan')
            ->assertJsonPath('data.harga_modal', 3000)
            ->assertJsonPath('data.tampil_di_katalog', 1);

        $this->getJson('/api/barang?q=indomie')->assertOk()
            ->assertJsonCount(1, 'data.barang')
            ->assertJsonPath('data.ringkasan.total', 1);

        $this->putJson("/api/barang/$id", $this->dataBarang(['nama_barang' => 'Indomie Soto', 'satuan' => '', 'tampil_di_katalog' => false, 'stok' => 999]))
            ->assertOk()->assertJson(['pesan' => 'Data barang diperbarui.']);
        $b->refresh();
        $this->assertSame('Indomie Soto', $b->nama_barang);
        $this->assertSame('pcs', $b->satuan);
        $this->assertFalse($b->tampil_di_katalog);
        $this->assertNull($b->kategori_id);
        $this->assertSame(0, $b->stok); // PUT tidak mengubah stok

        $this->deleteJson("/api/barang/$id")->assertOk()->assertJson(['pesan' => 'Barang dihapus.']);
        $this->assertNull(Barang::find($id));
    }

    public function test_validasi_barang(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'));

        $this->postJson('/api/barang', $this->dataBarang(['nama_barang' => '  ']))
            ->assertStatus(422)->assertJson(['sukses' => false, 'pesan' => 'Nama barang wajib diisi.']);
        $this->postJson('/api/barang', $this->dataBarang(['harga_jual' => '']))
            ->assertStatus(422)->assertJson(['pesan' => 'Harga jual wajib diisi.']);
        $this->postJson('/api/barang', $this->dataBarang(['harga_jual' => -5]))
            ->assertStatus(422)->assertJson(['pesan' => 'Harga jual minimal 0.']);
        $this->postJson('/api/barang', $this->dataBarang(['stok' => 1.5]))
            ->assertStatus(422)->assertJson(['pesan' => 'Stok awal harus berupa bilangan bulat.']);
    }

    public function test_barang_yang_pernah_terjual_hanya_dinonaktifkan(): void
    {
        $toko = $this->buatToko();
        $p = $this->buatAkun($toko, 'pemilik');
        $b = $this->buatBarang($toko, ['kode_barang' => 'X1']);
        $t = DB::table('transaksi')->insertGetId([
            'toko_id' => $toko->id, 'nomor_transaksi' => 'TRX-1', 'pengguna_id' => $p->id,
            'total' => 5000, 'uang_bayar' => 5000, 'kembalian' => 0, 'metode_bayar' => 'tunai',
            'tanggal' => now()->toDateString(), 'waktu' => now(),
        ]);
        DB::table('item_transaksi')->insert([
            'transaksi_id' => $t, 'barang_id' => $b->id, 'nama_barang' => $b->nama_barang,
            'harga_jual' => 5000, 'harga_modal' => 4000, 'jumlah' => 1, 'subtotal' => 5000,
        ]);

        $this->masukSebagai($p)->deleteJson("/api/barang/{$b->id}")
            ->assertOk()->assertJson(['pesan' => 'Barang dinonaktifkan (riwayat penjualannya tetap tersimpan).']);
        $b->refresh();
        $this->assertFalse($b->aktif);
        $this->assertSame("X1~{$b->id}", $b->kode_barang);

        // tidak muncul di daftar default, muncul dengan semua=1; kode bebas dipakai ulang
        $this->getJson('/api/barang')->assertJsonCount(0, 'data.barang');
        $this->getJson('/api/barang?semua=1')->assertJsonCount(1, 'data.barang');
        $this->postJson('/api/barang', $this->dataBarang(['kode_barang' => 'X1']))->assertStatus(201);
        $this->deleteJson("/api/barang/{$b->id}")->assertStatus(404);
    }

    public function test_kasir_tidak_boleh_kelola_barang_dan_kategori(): void
    {
        $toko = $this->buatToko();
        $b = $this->buatBarang($toko);
        $k = $this->buatKategori($toko);
        $this->masukSebagai($this->buatAkun($toko, 'kasir'));

        $this->postJson('/api/barang', $this->dataBarang())->assertStatus(403)->assertJson(['sukses' => false]);
        $this->putJson("/api/barang/{$b->id}", $this->dataBarang())->assertStatus(403);
        $this->deleteJson("/api/barang/{$b->id}")->assertStatus(403);
        $this->post("/api/barang/{$b->id}/foto", [], ['Accept' => 'application/json'])->assertStatus(403);
        $this->postJson('/api/kategori', ['nama' => 'Baru'])->assertStatus(403);
        $this->deleteJson("/api/kategori/{$k->id}")->assertStatus(403);

        // tetap boleh lihat barang, kategori, dan catat stok
        $this->getJson('/api/barang')->assertOk();
        $this->getJson('/api/kategori')->assertOk()->assertJsonPath('data.0.nama', 'Umum');
        $this->postJson("/api/barang/{$b->id}/stok", ['jenis' => 'masuk', 'jumlah' => 3])->assertOk();
    }

    public function test_tamu_ditolak_401(): void
    {
        $this->getJson('/api/barang')->assertStatus(401);
    }

    public function test_kasir_tidak_menerima_harga_modal(): void
    {
        $toko = $this->buatToko();
        $b = $this->buatBarang($toko, ['stok' => 4, 'harga_modal' => 4000]);
        $this->masukSebagai($this->buatAkun($toko, 'kasir'));

        $daftar = $this->getJson('/api/barang')->assertOk()->json('data');
        $this->assertArrayNotHasKey('harga_modal', $daftar['barang'][0]);
        $this->assertArrayHasKey('harga_jual', $daftar['barang'][0]);
        $this->assertArrayNotHasKey('nilai_modal', $daftar['ringkasan']);

        $satu = $this->getJson("/api/barang/{$b->id}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('harga_modal', $satu);

        // pemilik menerima harga_modal & nilai_modal
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'));
        $pemilik = $this->getJson('/api/barang')->assertOk()->json('data');
        $this->assertSame(4000, $pemilik['barang'][0]['harga_modal']);
        $this->assertSame(16000, $pemilik['ringkasan']['nilai_modal']);
    }

    public function test_kode_barang_unik_per_toko(): void
    {
        $toko = $this->buatToko();
        $lain = $this->buatToko();
        $this->buatBarang($lain, ['kode_barang' => 'SAMA']);
        $b2 = $this->buatBarang($toko, ['kode_barang' => 'B2']);
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'));

        // kode sama di toko lain → boleh
        $this->postJson('/api/barang', $this->dataBarang(['kode_barang' => 'SAMA']))->assertStatus(201);
        // kode sama di toko sendiri → 409
        $this->postJson('/api/barang', $this->dataBarang(['kode_barang' => 'SAMA']))
            ->assertStatus(409)->assertJson(['sukses' => false, 'pesan' => 'Kode barang SAMA sudah dipakai barang lain.']);
        $this->putJson("/api/barang/{$b2->id}", $this->dataBarang(['kode_barang' => 'SAMA']))->assertStatus(409);
        // simpan ulang dengan kodenya sendiri → boleh
        $this->putJson("/api/barang/{$b2->id}", $this->dataBarang(['kode_barang' => 'B2']))->assertOk();
    }

    public function test_kategori_milik_toko_lain_ditolak(): void
    {
        $toko = $this->buatToko();
        $lain = $this->buatToko();
        $kLain = $this->buatKategori($lain, 'Asing');
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'));

        $this->postJson('/api/barang', $this->dataBarang(['kategori_id' => $kLain->id]))
            ->assertStatus(422)->assertJson(['pesan' => 'Kategori tidak ditemukan.']);
        $this->getJson('/api/kategori')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/kategori/{$kLain->id}")->assertStatus(404)->assertJson(['pesan' => 'Kategori tidak ditemukan.']);
        $this->assertNotNull(KategoriBarang::withoutGlobalScope('toko')->find($kLain->id));
    }

    public function test_kelola_kategori(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'));

        $id = $this->postJson('/api/kategori', ['nama' => ' Minuman '])->assertStatus(201)
            ->assertJson(['pesan' => 'Kategori ditambahkan.', 'data' => ['nama' => 'Minuman']])->json('data.id');
        $this->postJson('/api/kategori', ['nama' => 'Minuman'])->assertStatus(409);
        $this->postJson('/api/kategori', ['nama' => ''])->assertStatus(422)->assertJson(['pesan' => 'Nama kategori wajib diisi.']);

        $b = $this->buatBarang($toko, ['kategori_id' => $id]);
        $this->buatBarang($toko, ['kategori_id' => $id, 'aktif' => false]);
        $this->getJson('/api/kategori')->assertJsonPath('data.0.jumlah_barang', 1);
        $this->getJson("/api/barang?kategori_id=$id")->assertJsonCount(1, 'data.barang');

        $this->deleteJson("/api/kategori/$id")->assertOk()->assertJson(['pesan' => 'Kategori dihapus.']);
        $this->assertNull($b->fresh()->kategori_id);
    }

    public function test_stok_awal_tercatat_sebagai_mutasi_awal(): void
    {
        $toko = $this->buatToko();
        $p = $this->buatAkun($toko, 'pemilik');
        $this->masukSebagai($p);

        $id = $this->postJson('/api/barang', $this->dataBarang(['stok' => '24']))->assertStatus(201)->json('data.id');
        $this->assertSame(24, Barang::find($id)->stok);
        $m = MutasiStok::where('barang_id', $id)->sole();
        $this->assertSame('awal', $m->jenis);
        $this->assertSame(24, $m->jumlah);
        $this->assertSame(0, $m->stok_sebelum);
        $this->assertSame(24, $m->stok_sesudah);
        $this->assertSame('Stok awal barang baru', $m->keterangan);
        $this->assertSame($p->id, $m->pengguna_id);
        $this->assertSame($toko->id, $m->toko_id);
    }

    public function test_stok_masuk_dan_keluar_lewat_mutasi(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $b = $this->buatBarang($toko, ['stok' => 10]);
        $this->masukSebagai($kasir);

        $this->postJson("/api/barang/{$b->id}/stok", ['jenis' => 'masuk', 'jumlah' => '5', 'keterangan' => ''])
            ->assertOk()->assertJson(['pesan' => 'Stok masuk 5 dicatat.', 'data' => ['stok_sebelum' => 10, 'stok_sesudah' => 15]]);
        $this->postJson("/api/barang/{$b->id}/stok", ['jenis' => 'keluar', 'jumlah' => 3, 'keterangan' => 'Rusak'])
            ->assertOk()->assertJson(['pesan' => 'Stok keluar 3 dicatat.', 'data' => ['stok_sesudah' => 12]]);
        $this->assertSame(12, $b->fresh()->stok);

        // keluar melebihi stok → 422, stok & mutasi tidak berubah
        $this->postJson("/api/barang/{$b->id}/stok", ['jenis' => 'keluar', 'jumlah' => 13, 'keterangan' => 'Hilang'])
            ->assertStatus(422)->assertJson(['pesan' => 'Stok Barang Uji tidak cukup. Sisa 12 pcs.']);
        $this->assertSame(12, $b->fresh()->stok);

        // validasi
        $this->postJson("/api/barang/{$b->id}/stok", ['jenis' => 'keluar', 'jumlah' => 1])
            ->assertStatus(422)->assertJson(['pesan' => 'Keterangan wajib diisi.']);
        $this->postJson("/api/barang/{$b->id}/stok", ['jenis' => 'opname', 'jumlah' => 1])
            ->assertStatus(422)->assertJson(['pesan' => 'Jenis mutasi harus masuk atau keluar.']);
        $this->postJson("/api/barang/{$b->id}/stok", ['jenis' => 'masuk', 'jumlah' => 0])
            ->assertStatus(422)->assertJson(['pesan' => 'Jumlah minimal 1.']);

        $riwayat = $this->getJson("/api/barang/{$b->id}/stok")->assertOk()->json('data');
        $this->assertCount(2, $riwayat);
        $this->assertSame('keluar', $riwayat[0]['jenis']);
        $this->assertSame(-3, $riwayat[0]['jumlah']);
        $this->assertSame('Rusak', $riwayat[0]['keterangan']);
        $this->assertSame($kasir->nama, $riwayat[0]['oleh']);
        $this->assertSame('Barang masuk', $riwayat[1]['keterangan']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $riwayat[0]['dibuat_pada']);
    }

    public function test_filter_stok_menipis_dan_habis(): void
    {
        $toko = $this->buatToko();
        $this->buatBarang($toko, ['nama_barang' => 'A', 'stok' => 0, 'stok_minimum' => 2]);
        $this->buatBarang($toko, ['nama_barang' => 'B', 'stok' => 2, 'stok_minimum' => 2]);
        $this->buatBarang($toko, ['nama_barang' => 'C', 'stok' => 9, 'stok_minimum' => 2]);
        $this->buatBarang($toko, ['nama_barang' => '50%_off', 'kode_barang' => 'D1', 'stok' => 9]);
        $this->masukSebagai($this->buatAkun($toko, 'kasir'));

        $this->getJson('/api/barang?filter=habis')->assertJsonCount(1, 'data.barang')->assertJsonPath('data.barang.0.nama_barang', 'A');
        $this->getJson('/api/barang?filter=menipis')->assertJsonCount(1, 'data.barang')->assertJsonPath('data.barang.0.nama_barang', 'B');
        $this->getJson('/api/barang')->assertJsonPath('data.ringkasan', ['total' => 4, 'habis' => 1, 'menipis' => 1])
            ->assertJsonPath('data.barang.0.nama_barang', '50%_off'); // urut nama
        // wildcard LIKE di-escape
        $this->getJson('/api/barang?q=%25')->assertJsonCount(1, 'data.barang');
        $this->getJson('/api/barang?q=_')->assertJsonCount(1, 'data.barang');
    }

    public function test_isolasi_data_antar_toko(): void
    {
        $toko = $this->buatToko();
        $lain = $this->buatToko();
        $bLain = $this->buatBarang($lain, ['stok' => 5]);
        $this->buatBarang($toko);
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'));

        $this->getJson('/api/barang')->assertJsonCount(1, 'data.barang')->assertJsonPath('data.ringkasan.total', 1);
        $this->getJson("/api/barang/{$bLain->id}")->assertStatus(404)->assertJson(['pesan' => 'Barang tidak ditemukan.']);
        $this->putJson("/api/barang/{$bLain->id}", $this->dataBarang())->assertStatus(404);
        $this->deleteJson("/api/barang/{$bLain->id}")->assertStatus(404);
        $this->getJson("/api/barang/{$bLain->id}/stok")->assertStatus(404);
        $this->postJson("/api/barang/{$bLain->id}/stok", ['jenis' => 'masuk', 'jumlah' => 1])->assertStatus(404);
        $this->post("/api/barang/{$bLain->id}/foto", ['berkas' => UploadedFile::fake()->createWithContent('a.png', $this->png())], ['Accept' => 'application/json'])
            ->assertStatus(404);
        $this->getJson('/api/barang/abc')->assertStatus(404);

        $this->assertSame(5, $bLain->fresh()->stok);
        $this->assertSame(0, MutasiStok::withoutGlobalScope('toko')->where('barang_id', $bLain->id)->count());
    }

    private function png(): string
    {
        // PNG 1x1 asli
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }

    public function test_upload_foto_valid_dan_invalid(): void
    {
        Storage::fake('local');
        $toko = $this->buatToko();
        $b = $this->buatBarang($toko);
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'));
        $hdr = ['Accept' => 'application/json'];

        $url = $this->post("/api/barang/{$b->id}/foto", ['berkas' => UploadedFile::fake()->createWithContent('foto.png', $this->png())], $hdr)
            ->assertOk()->assertJson(['pesan' => 'Foto barang diperbarui.'])->json('data.foto_url');
        $this->assertMatchesRegularExpression("/^\/api\/berkas\/barang-{$toko->id}-[a-f0-9]{16}\.png$/", $url);
        $this->assertSame($url, $b->fresh()->foto_url);
        Storage::disk('local')->assertExists('uploads/'.basename($url));

        // teks yang menyamar sebagai .png → ditolak (magic bytes)
        $this->post("/api/barang/{$b->id}/foto", ['berkas' => UploadedFile::fake()->createWithContent('palsu.png', 'ini bukan gambar <?php echo 1;')], $hdr)
            ->assertStatus(422)->assertJson(['pesan' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.']);
        $this->post("/api/barang/{$b->id}/foto", [], $hdr)
            ->assertStatus(422)->assertJson(['pesan' => 'Pilih file gambar terlebih dahulu.']);
        $this->assertSame($url, $b->fresh()->foto_url);

        // ganti foto → file lama dihapus
        $baru = $this->post("/api/barang/{$b->id}/foto", ['berkas' => UploadedFile::fake()->createWithContent('lagi.png', $this->png())], $hdr)
            ->assertOk()->json('data.foto_url');
        Storage::disk('local')->assertExists('uploads/'.basename($baru));
        Storage::disk('local')->assertMissing('uploads/'.basename($url));
    }
}
