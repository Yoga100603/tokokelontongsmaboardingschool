<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Pengguna;
use App\Models\Toko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function buatAkun(string $peran = 'pemilik', array $ubah = []): Pengguna
    {
        $toko = Toko::create(['nama' => 'Toko Uji', 'slug' => 'toko-uji-'.uniqid()]);
        $p = new Pengguna(array_merge(['nama' => 'Uji', 'email' => uniqid().'@uji.id', 'kata_sandi' => 'rahasia123', 'peran' => $peran], $ubah));
        $p->toko_id = $toko->id;
        $p->save();

        return $p->fresh();
    }

    private function masuk(Pengguna $p, string $sandi = 'rahasia123')
    {
        return $this->post('/masuk', ['email' => $p->email, 'kata_sandi' => $sandi]);
    }

    public function test_halaman_internal_wajib_login(): void
    {
        $this->get('/kasir')->assertRedirect('/masuk');
        $this->get('/masuk')->assertOk()->assertSee('Masuk ke toko Anda');
    }

    public function test_pemilik_masuk_diarahkan_ke_dasbor_kasir_ke_kasir(): void
    {
        $this->masuk($this->buatAkun('pemilik'))->assertRedirect('/dasbor');
        $this->post('/keluar');
        $this->masuk($this->buatAkun('kasir'))->assertRedirect('/kasir');
    }

    public function test_sandi_salah_dan_email_tak_dikenal_pesannya_sama(): void
    {
        $p = $this->buatAkun();
        $this->masuk($p, 'salah-sandi')->assertSessionHasErrors(['umum' => 'Email atau kata sandi salah.']);
        $this->post('/masuk', ['email' => 'tidak@ada.id', 'kata_sandi' => 'x'])
            ->assertSessionHasErrors(['umum' => 'Email atau kata sandi salah.']);
        $this->assertGuest();
    }

    public function test_email_tidak_peka_huruf_besar(): void
    {
        $p = $this->buatAkun();
        $this->post('/masuk', ['email' => strtoupper($p->email), 'kata_sandi' => 'rahasia123'])->assertRedirect('/dasbor');
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $p = $this->buatAkun('kasir', ['aktif' => false]);
        $this->masuk($p)->assertSessionHasErrors(['umum' => 'Akun ini sudah dinonaktifkan oleh pemilik toko.']);
        $this->assertGuest();
    }

    public function test_rate_limit_masuk_setelah_5_kali_gagal(): void
    {
        $p = $this->buatAkun();
        for ($i = 0; $i < 5; $i++) {
            $this->masuk($p, 'salah');
        }
        // sandi benar pun ditolak saat terkunci
        $this->masuk($p)->assertSessionHasErrors(['umum' => 'Terlalu banyak percobaan masuk. Tunggu 15 menit lalu coba lagi.']);
        $this->assertGuest();
    }

    public function test_kasir_tidak_bisa_buka_halaman_pemilik(): void
    {
        $this->actingAsDenganSesi($this->buatAkun('kasir'));
        $this->get('/laporan')->assertRedirect('/kasir?akses=ditolak');
        $this->get('/dasbor')->assertRedirect('/kasir?akses=ditolak');
        $this->get('/kasir')->assertOk();
    }

    public function test_sesi_lama_ditolak_setelah_versi_sesi_naik(): void
    {
        $p = $this->buatAkun();
        $this->masuk($p)->assertRedirect('/dasbor');
        $this->get('/kasir')->assertOk();

        $p->cabutSesi(); // mis. ganti sandi di perangkat lain
        $this->get('/kasir')->assertRedirect('/masuk?sesi=1');
        $this->assertGuest();
    }

    public function test_sesi_ditolak_setelah_dinonaktifkan(): void
    {
        $p = $this->buatAkun('kasir');
        $this->masuk($p);
        $p->forceFill(['aktif' => false])->save();
        $this->get('/kasir')->assertRedirect('/masuk?sesi=1');
    }

    public function test_daftar_toko_baru_membuat_toko_pemilik_kategori_dan_login(): void
    {
        $this->post('/daftar', [
            'nama_toko' => 'Toko Maju Jaya', 'nama' => 'Bu Ani', 'email' => 'ANI@Maju.id',
            'kata_sandi' => 'rahasia123', 'kata_sandi_confirmation' => 'rahasia123',
        ])->assertRedirect('/pengaturan?baru=1');

        $toko = Toko::where('slug', 'toko-maju-jaya')->firstOrFail();
        $p = Pengguna::where('email', 'ani@maju.id')->firstOrFail();
        $this->assertSame('pemilik', $p->peran);
        $this->assertSame($toko->id, $p->toko_id);
        $this->assertSame(4, KategoriBarang::where('toko_id', $toko->id)->count());
        $this->assertAuthenticatedAs($p);
        $this->get('/pengaturan?baru=1')->assertOk();
    }

    public function test_daftar_slug_bentrok_diberi_akhiran_dan_email_duplikat_ditolak(): void
    {
        Toko::create(['nama' => 'Toko Maju', 'slug' => 'toko-maju']);
        $data = ['nama_toko' => 'Toko Maju', 'nama' => 'A', 'email' => 'a@a.id', 'kata_sandi' => 'rahasia123', 'kata_sandi_confirmation' => 'rahasia123'];
        $this->post('/daftar', $data);
        $this->assertTrue(Toko::where('slug', 'toko-maju-2')->exists());

        $this->post('/keluar');
        $this->post('/daftar', $data)->assertSessionHasErrors(['email']);
    }

    public function test_daftar_konfirmasi_sandi_harus_sama(): void
    {
        $this->post('/daftar', ['nama_toko' => 'Toko X', 'nama' => 'A', 'email' => 'x@x.id', 'kata_sandi' => 'rahasia123', 'kata_sandi_confirmation' => 'beda12345'])
            ->assertSessionHasErrors(['kata_sandi']);
        $this->assertGuest();
    }

    public function test_alur_lupa_dan_reset_sandi(): void
    {
        $p = $this->buatAkun();
        $tautan = null;
        Log::listen(function ($pesan) use (&$tautan) {
            if (preg_match('/token=([0-9a-f]{64})/', $pesan->message, $m)) {
                $tautan = $m[1];
            }
        });

        // email tak terdaftar → respons tetap sama
        $this->post('/lupa-sandi', ['email' => 'tidak@ada.id'])->assertSessionHas('sukses');
        $this->post('/lupa-sandi', ['email' => $p->email])->assertSessionHas('sukses');
        $this->assertNotNull($tautan, 'Tautan reset harus tertulis di log');
        $this->assertNotSame($tautan, $p->fresh()->token_reset, 'Token di DB harus berupa hash');

        $versiLama = $p->fresh()->versi_sesi;
        $this->post('/reset-sandi', ['token' => $tautan, 'kata_sandi' => 'sandibaru99', 'kata_sandi_confirmation' => 'sandibaru99'])
            ->assertRedirect('/masuk?reset=1');

        $p->refresh();
        $this->assertNull($p->token_reset);
        $this->assertSame($versiLama + 1, $p->versi_sesi);
        $this->masuk($p, 'sandibaru99')->assertRedirect('/dasbor');

        // token sekali pakai
        $this->post('/keluar');
        $this->post('/reset-sandi', ['token' => $tautan, 'kata_sandi' => 'lagi12345', 'kata_sandi_confirmation' => 'lagi12345'])
            ->assertSessionHasErrors('umum');
    }

    public function test_token_reset_kedaluwarsa_ditolak(): void
    {
        $p = $this->buatAkun();
        $p->forceFill(['token_reset' => hash('sha256', 'abc'), 'token_reset_kedaluwarsa' => now()->subMinute()])->save();
        $this->post('/reset-sandi', ['token' => 'abc', 'kata_sandi' => 'sandibaru99', 'kata_sandi_confirmation' => 'sandibaru99'])
            ->assertSessionHasErrors('umum');
    }

    public function test_isolasi_toko_global_scope(): void
    {
        $a = $this->buatAkun();
        $b = $this->buatAkun();
        foreach ([$a, $b] as $p) {
            $barang = new Barang(['kode_barang' => 'K1', 'nama_barang' => 'Barang '.$p->id, 'harga_jual' => 1000]);
            $barang->toko_id = $p->toko_id;
            $barang->save();
        }

        $this->actingAs($a);
        $this->assertSame(['Barang '.$a->id], Barang::pluck('nama_barang')->all());

        // toko_id dari request diabaikan: selalu diisi dari sesi
        $baru = new Barang(['kode_barang' => 'K2', 'nama_barang' => 'Baru', 'harga_jual' => 1]);
        $baru->toko_id = $b->toko_id;
        $baru->save();
        $this->assertSame($a->toko_id, $baru->fresh()->toko_id);
    }

    public function test_tamu_yang_sudah_login_diarahkan_dari_halaman_masuk(): void
    {
        $this->actingAsDenganSesi($this->buatAkun('kasir'));
        $this->get('/masuk')->assertRedirect('/kasir');
    }

    private function actingAsDenganSesi(Pengguna $p): void
    {
        $this->actingAs($p)->withSession(['versi_sesi' => $p->versi_sesi]);
    }
}
