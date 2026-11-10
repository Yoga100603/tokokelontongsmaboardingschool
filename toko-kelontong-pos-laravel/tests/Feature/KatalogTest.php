<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class KatalogTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    public function test_landing_tampil_untuk_tamu_dan_pengguna_login(): void
    {
        $this->buatToko(['nama' => 'Warung Publik', 'slug' => 'warung-publik', 'alamat' => 'Jl. Melati']);
        $this->buatToko(['nama' => 'Warung Rahasia', 'slug' => 'warung-rahasia', 'katalog_aktif' => false]);

        $this->get('/')->assertOk()
            ->assertSee('Kasir, stok, dan laporan toko dalam satu aplikasi.')
            ->assertSee('Daftar Gratis')
            ->assertSee('Warung Publik')->assertSee('/toko/warung-publik', false)
            ->assertDontSee('Warung Rahasia');

        $toko = $this->buatToko(['nama' => 'Toko Saya']);
        $this->masukSebagai($this->buatAkun($toko))->get('/')->assertOk()->assertSee('Buka Toko Saya')->assertDontSee('Daftar Gratis');
    }

    public function test_katalog_publik_hanya_barang_aktif_dan_tampil_tanpa_harga_modal(): void
    {
        $toko = $this->buatToko(['nama' => 'Warung Bu Siti', 'slug' => 'warung-bu-siti', 'telepon' => '081234567890', 'jam_buka' => 'Senin–Sabtu 07.00–21.00']);
        $kat = $this->buatKategori($toko, 'Sembako');
        $this->buatBarang($toko, ['nama_barang' => 'Beras Pandan', 'harga_jual' => 65000, 'harga_modal' => 58123, 'kategori_id' => $kat->id]);
        $this->buatBarang($toko, ['nama_barang' => 'Gula Habis', 'stok' => 0]);
        $this->buatBarang($toko, ['nama_barang' => 'Barang Nonaktif', 'aktif' => false]);
        $this->buatBarang($toko, ['nama_barang' => 'Barang Tersembunyi', 'tampil_di_katalog' => false]);
        $lain = $this->buatToko();
        $this->buatBarang($lain, ['nama_barang' => 'Barang Toko Lain']);

        $r = $this->get('/toko/warung-bu-siti')->assertOk()
            ->assertSee('Warung Bu Siti')->assertSee('Senin–Sabtu 07.00–21.00')
            ->assertSee('Beras Pandan')->assertSee('Rp65.000')->assertSee('Gula Habis')->assertSee('Stok habis')
            ->assertSee('Sembako')->assertSee('2 barang')
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertDontSee('Barang Nonaktif')->assertDontSee('Barang Tersembunyi')->assertDontSee('Barang Toko Lain')
            ->assertDontSee('58123')->assertDontSee('58.123');
        $this->assertGuest();

        $this->get('/toko/warung-bu-siti?q=beras')->assertOk()->assertSee('Beras Pandan')->assertDontSee('Gula Habis')->assertSee('1 barang untuk');
        $this->get('/toko/warung-bu-siti?kategori='.$kat->id)->assertOk()->assertSee('Beras Pandan')->assertDontSee('Gula Habis');
    }

    public function test_katalog_tetap_milik_toko_itu_walau_pengunjung_login_di_toko_lain(): void
    {
        $toko = $this->buatToko(['slug' => 'toko-target']);
        $this->buatBarang($toko, ['nama_barang' => 'Kopi Target']);
        $lain = $this->buatToko();
        $this->buatBarang($lain, ['nama_barang' => 'Kopi Sendiri']);

        $this->masukSebagai($this->buatAkun($lain))->get('/toko/toko-target')->assertOk()
            ->assertSee('Kopi Target')->assertDontSee('Kopi Sendiri');
    }

    public function test_slug_tidak_ada_atau_katalog_nonaktif_404(): void
    {
        $this->get('/toko/tidak-ada')->assertNotFound();
        $this->buatToko(['slug' => 'tutup', 'katalog_aktif' => false]);
        $this->get('/toko/tutup')->assertNotFound();
    }

    public function test_logo_dari_luar_pola_berkas_tidak_dipakai(): void
    {
        $this->buatToko(['nama' => 'Toko Aneh', 'slug' => 'toko-aneh', 'logo_url' => 'javascript:alert(1)']);
        $this->get('/toko/toko-aneh')->assertOk()->assertDontSee('javascript:alert(1)', false);
    }
}
