<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Toko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class PengaturanTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1A\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x02\0\0\0\x90wS\xDE\0\0\0\x0cIDAT\x08\xd7c\xf8\xcf\xc0\0\0\x03\x01\x01\0\x18\xdd\x8d\xb0\0\0\0\0IEND\xaeB`\x82";

    public function test_halaman_pengaturan_tampil_untuk_pemilik_dan_kasir(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'pemilik'))
            ->get('/pengaturan?baru=1')->assertOk()->assertSee('Akun & Pengaturan')->assertSee('Toko berhasil didaftarkan!')->assertSee('Akun Kasir');

        $this->masukSebagai($this->buatAkun($toko, 'kasir'))
            ->get('/pengaturan')->assertOk()->assertSee('Profil Saya')->assertDontSee('Akun Kasir')->assertDontSee('Toko berhasil didaftarkan!');
    }

    public function test_get_profil_toko_dan_pemilik_bisa_ubah(): void
    {
        $toko = $this->buatToko(['nama' => 'Toko Lama']);
        $this->masukSebagai($this->buatAkun($toko));

        $this->getJson('/api/toko')->assertOk()->assertJsonPath('data.nama', 'Toko Lama')->assertJsonPath('data.katalog_aktif', 1);

        $this->putJson('/api/toko', [
            'nama' => 'Toko Baru', 'slug' => 'Toko Baru Jaya!', 'telepon' => '0812-3456 789', 'email' => 'TOKO@Baru.id',
            'alamat' => 'Jl. Mawar', 'deskripsi' => 'Lengkap', 'jam_buka' => '07.00–21.00', 'catatan_struk' => 'Makasih', 'katalog_aktif' => 0,
        ])->assertOk()
            ->assertJsonPath('pesan', 'Profil toko disimpan.')
            ->assertJsonPath('data.slug', 'toko-baru-jaya')
            ->assertJsonPath('data.telepon', '08123456789')
            ->assertJsonPath('data.email', 'toko@baru.id')
            ->assertJsonPath('data.katalog_aktif', 0);
        $this->assertSame('Toko Baru', $toko->fresh()->nama);
    }

    public function test_ubah_toko_validasi_dan_slug_bentrok(): void
    {
        $this->buatToko(['slug' => 'dipakai']);
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko));

        $this->putJson('/api/toko', ['nama' => 'ab', 'slug' => 'x'])->assertStatus(422)->assertJsonPath('pesan', 'Nama toko minimal 3 karakter.');
        $this->putJson('/api/toko', ['nama' => 'Toko A', 'slug' => 'x', 'email' => 'bukan-email'])->assertStatus(422)->assertJsonPath('pesan', 'Format email tidak valid.');
        $this->putJson('/api/toko', ['nama' => 'Toko A', 'slug' => 'dipakai'])->assertStatus(409)
            ->assertJsonPath('pesan', 'Alamat katalog sudah dipakai toko lain. Pilih yang lain.');
    }

    public function test_kasir_ditolak_ubah_toko_dan_kelola_akun(): void
    {
        $toko = $this->buatToko(['nama' => 'Tetap']);
        $kasir = $this->buatAkun($toko, 'kasir');
        $this->masukSebagai($kasir);

        $this->getJson('/api/toko')->assertOk(); // baca boleh
        $this->putJson('/api/toko', ['nama' => 'Diretas', 'slug' => 'diretas'])->assertStatus(403);
        $this->postJson('/api/toko/logo', ['berkas' => UploadedFile::fake()->createWithContent('a.png', self::PNG)])->assertStatus(403);
        $this->getJson('/api/pengguna')->assertStatus(403);
        $this->postJson('/api/pengguna', ['nama' => 'X', 'email' => 'x@x.id', 'kata_sandi' => 'rahasia123'])->assertStatus(403);
        $this->putJson("/api/pengguna/{$kasir->id}", ['nama' => 'X', 'aktif' => true])->assertStatus(403);
        $this->assertSame('Tetap', $toko->fresh()->nama);
    }

    public function test_pemilik_tambah_kasir_terikat_toko_pemilik(): void
    {
        $toko = $this->buatToko();
        $lain = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko));

        $r = $this->postJson('/api/pengguna', [
            'nama' => 'Budi', 'email' => 'BUDI@Kasir.id', 'kata_sandi' => 'rahasia123',
            'toko_id' => $lain->id, 'peran' => 'pemilik',
        ])->assertStatus(201)->assertJsonPath('pesan', 'Akun kasir Budi berhasil dibuat.');

        $k = Pengguna::findOrFail($r->json('data.id'));
        $this->assertSame($toko->id, $k->toko_id);
        $this->assertSame('kasir', $k->peran);
        $this->assertSame('budi@kasir.id', $k->email);
        $this->assertTrue(Hash::check('rahasia123', $k->kata_sandi));

        // email unik global
        $this->postJson('/api/pengguna', ['nama' => 'B2', 'email' => 'budi@kasir.id', 'kata_sandi' => 'rahasia123'])
            ->assertStatus(409)->assertJsonPath('pesan', 'Email sudah dipakai akun lain.');
        $this->postJson('/api/pengguna', ['nama' => 'B3', 'email' => 'b3@kasir.id', 'kata_sandi' => 'pendek'])
            ->assertStatus(422)->assertJsonPath('pesan', 'Kata sandi minimal 8 karakter.');

        $daftar = $this->getJson('/api/pengguna')->assertOk()->json('data');
        $this->assertCount(2, $daftar);
        $this->assertSame('pemilik', $daftar[0]['peran']);
        $this->assertArrayNotHasKey('kata_sandi', $daftar[0]);
    }

    public function test_pemilik_tidak_bisa_kelola_akun_toko_lain_atau_pemilik(): void
    {
        $toko = $this->buatToko();
        $pemilik = $this->buatAkun($toko);
        $kasirLain = $this->buatAkun($this->buatToko(), 'kasir');
        $this->masukSebagai($pemilik);

        $this->putJson("/api/pengguna/{$kasirLain->id}", ['nama' => 'Diambil', 'aktif' => false])
            ->assertStatus(404)->assertJsonPath('pesan', 'Akun tidak ditemukan.');
        $this->assertSame($kasirLain->nama, $kasirLain->fresh()->nama);
        $this->assertTrue($kasirLain->fresh()->aktif);

        $this->putJson("/api/pengguna/{$pemilik->id}", ['nama' => 'X', 'aktif' => true])
            ->assertStatus(422)->assertJsonPath('pesan', 'Akun pemilik diubah lewat bagian Profil Saya.');
        $this->putJson('/api/pengguna/abc', ['nama' => 'X'])->assertStatus(404);

        $this->assertCount(1, $this->getJson('/api/pengguna')->json('data'));
    }

    public function test_nonaktifkan_kasir_mencabut_sesi(): void
    {
        $toko = $this->buatToko();
        $pemilik = $this->buatAkun($toko);
        $kasir = $this->buatAkun($toko, 'kasir');
        $versi = $kasir->versi_sesi;

        // ubah nama saja → sesi tidak dicabut
        $this->masukSebagai($pemilik)->putJson("/api/pengguna/{$kasir->id}", ['nama' => 'Kasir Baru', 'aktif' => true, 'kata_sandi' => ''])
            ->assertOk()->assertJsonPath('pesan', 'Akun kasir diperbarui.');
        $this->assertSame($versi, $kasir->fresh()->versi_sesi);

        $this->putJson("/api/pengguna/{$kasir->id}", ['nama' => 'Kasir Baru', 'aktif' => false])->assertOk();
        $k = $kasir->fresh();
        $this->assertFalse($k->aktif);
        $this->assertSame($versi + 1, $k->versi_sesi);

        // sesi lama kasir ditolak
        $this->actingAs($kasir)->withSession(['versi_sesi' => $versi])->getJson('/api/pengguna/saya')->assertStatus(401);
    }

    public function test_reset_sandi_kasir_mencabut_sesi(): void
    {
        $toko = $this->buatToko();
        $pemilik = $this->buatAkun($toko);
        $kasir = $this->buatAkun($toko, 'kasir');
        $versi = $kasir->versi_sesi;

        $this->masukSebagai($pemilik)->putJson("/api/pengguna/{$kasir->id}", ['nama' => $kasir->nama, 'aktif' => true, 'kata_sandi' => 'pendek'])
            ->assertStatus(422)->assertJsonPath('pesan', 'Kata sandi baru minimal 8 karakter.');

        $this->putJson("/api/pengguna/{$kasir->id}", ['nama' => $kasir->nama, 'aktif' => true, 'kata_sandi' => 'sandibaru99'])->assertOk();
        $k = $kasir->fresh();
        $this->assertTrue(Hash::check('sandibaru99', $k->kata_sandi));
        $this->assertSame($versi + 1, $k->versi_sesi);

        $this->actingAs($kasir)->withSession(['versi_sesi' => $versi])->get('/pengaturan')->assertRedirect('/masuk?sesi=1');
    }

    public function test_ganti_sandi_sendiri_wajib_sandi_lama_dan_tetap_login(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');
        $versi = $kasir->versi_sesi;
        $this->masukSebagai($kasir);

        $this->putJson('/api/pengguna/saya', ['nama' => 'Nama Baru', 'sandi_lama' => 'salah-sandi', 'sandi_baru' => 'sandibaru99'])
            ->assertStatus(422)->assertJsonPath('pesan', 'Kata sandi lama salah.');
        $this->assertSame($versi, $kasir->fresh()->versi_sesi);

        $this->putJson('/api/pengguna/saya', ['nama' => 'Nama Baru', 'sandi_lama' => 'rahasia123', 'sandi_baru' => 'sandibaru99'])
            ->assertOk()->assertJsonPath('pesan', 'Profil dan kata sandi diperbarui.')
            ->assertSessionHas('versi_sesi', $versi + 1);
        $k = $kasir->fresh();
        $this->assertSame('Nama Baru', $k->nama);
        $this->assertSame($versi + 1, $k->versi_sesi);
        $this->assertTrue(Hash::check('sandibaru99', $k->kata_sandi));

        // perangkat ini tetap masuk (versi_sesi di session sudah diperbarui → lolos cek.sesi)
        $this->getJson('/api/pengguna/saya')->assertOk()->assertJsonPath('data.email', $kasir->email);
        $this->get('/pengaturan')->assertOk();

        // perangkat lain (versi lama) keluar
        $this->actingAs($kasir)->withSession(['versi_sesi' => $versi])->getJson('/api/pengguna/saya')->assertStatus(401);
    }

    public function test_ubah_nama_saja_tanpa_cabut_sesi(): void
    {
        $p = $this->buatAkun($this->buatToko());
        $this->masukSebagai($p)->putJson('/api/pengguna/saya', ['nama' => 'Bu Ani', 'sandi_lama' => '', 'sandi_baru' => ''])
            ->assertOk()->assertJsonPath('pesan', 'Profil diperbarui.');
        $this->assertSame('Bu Ani', $p->fresh()->nama);
        $this->assertSame($p->versi_sesi, $p->fresh()->versi_sesi);
        $this->putJson('/api/pengguna/saya', ['nama' => ''])->assertStatus(422)->assertJsonPath('pesan', 'Nama wajib diisi.');
    }

    public function test_upload_logo_magic_bytes(): void
    {
        Storage::fake('local');
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko));

        // isi bukan gambar walau bernama .png → ditolak
        $this->post('/api/toko/logo', ['berkas' => UploadedFile::fake()->createWithContent('logo.png', '<?php echo 1;')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('pesan', 'Format gambar harus JPG, PNG, WEBP, atau GIF.');
        $this->assertSame('', (string) $toko->fresh()->logo_url);

        $r = $this->post('/api/toko/logo', ['berkas' => UploadedFile::fake()->createWithContent('logo.jpg', self::PNG)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('pesan', 'Logo toko diperbarui.');
        $url = $r->json('data.logo_url');
        $this->assertMatchesRegularExpression('/^\/api\/berkas\/logo-'.$toko->id.'-[a-f0-9]+\.png$/', $url);
        $this->assertSame($url, $toko->fresh()->logo_url);
        Storage::disk('local')->assertExists('uploads/'.basename($url));

        // logo lama dihapus saat diganti
        $url2 = $this->post('/api/toko/logo', ['berkas' => UploadedFile::fake()->createWithContent('b.png', self::PNG)], ['Accept' => 'application/json'])
            ->assertOk()->json('data.logo_url');
        Storage::disk('local')->assertMissing('uploads/'.basename($url));
        Storage::disk('local')->assertExists('uploads/'.basename($url2));
    }
}
