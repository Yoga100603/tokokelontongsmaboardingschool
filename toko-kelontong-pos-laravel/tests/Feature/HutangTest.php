<?php

namespace Tests\Feature;

use App\Models\Hutang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

class HutangTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    public function test_halaman_bisa_dibuka_kasir(): void
    {
        $this->masukSebagai($this->buatAkun($this->buatToko(), 'kasir'));
        $this->get(route('hutang'))->assertOk()->assertSee('Buku Hutang')->assertSee('Catat Hutang Manual');
    }

    public function test_tambah_hutang_dan_daftar(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'kasir'));

        $this->postJson('/api/hutang', ['nama_pelanggan' => ' Bu Siti ', 'kontak' => '0812', 'jumlah' => 25000, 'catatan' => 'beras'])
            ->assertCreated()->assertJson(['sukses' => true, 'pesan' => 'Catatan hutang disimpan.']);
        $this->postJson('/api/hutang', ['nama_pelanggan' => 'Pak Budi', 'jumlah' => '10.000', 'toko_id' => 999])->assertCreated();

        $h = Hutang::where('nama_pelanggan', 'Bu Siti')->first();
        $this->assertSame($toko->id, (int) $h->toko_id);
        $this->assertSame('aktif', $h->status);
        $this->assertSame($toko->id, (int) Hutang::where('nama_pelanggan', 'Pak Budi')->value('toko_id'));

        $d = $this->getJson('/api/hutang')->assertOk()->json('data');
        $this->assertSame(['jumlah' => 2, 'total' => 35000], $d['aktif']);
        $this->assertSame('Pak Budi', $d['hutang'][0]['nama_pelanggan']);
        $this->assertSame(10000, $d['hutang'][0]['jumlah']);
        $this->assertNull($d['hutang'][0]['nomor_transaksi']);
    }

    public function test_validasi_tambah(): void
    {
        $this->masukSebagai($this->buatAkun($this->buatToko(), 'kasir'));
        $this->postJson('/api/hutang', ['nama_pelanggan' => 'A', 'jumlah' => 0])->assertStatus(422)
            ->assertJson(['sukses' => false, 'pesan' => 'Nominal hutang minimal 1.']);
        $this->postJson('/api/hutang', ['nama_pelanggan' => 'A', 'jumlah' => -500])->assertStatus(422);
        $this->postJson('/api/hutang', ['nama_pelanggan' => 'A'])->assertStatus(422)->assertJson(['pesan' => 'Nominal hutang wajib diisi.']);
        $this->postJson('/api/hutang', ['nama_pelanggan' => '  ', 'jumlah' => 100])->assertStatus(422)->assertJson(['pesan' => 'Nama pelanggan wajib diisi.']);
        $this->postJson('/api/hutang', ['nama_pelanggan' => str_repeat('x', 81), 'jumlah' => 100])->assertStatus(422)
            ->assertJson(['pesan' => 'Nama pelanggan maksimal 80 karakter.']);
        $this->assertSame(0, Hutang::count());
    }

    public function test_tandai_lunas_sekali_saja(): void
    {
        $toko = $this->buatToko();
        $this->masukSebagai($this->buatAkun($toko, 'kasir'));
        $this->postJson('/api/hutang', ['nama_pelanggan' => 'Bu Siti', 'jumlah' => 5000]);
        $id = Hutang::value('id');

        $this->patchJson('/api/hutang', ['id' => $id])->assertOk()->assertJson(['pesan' => 'Hutang ditandai lunas.']);
        $h = Hutang::find($id);
        $this->assertSame('lunas', $h->status);
        $this->assertNotNull($h->lunas_pada);

        $this->patchJson('/api/hutang', ['id' => $id])->assertNotFound()
            ->assertJson(['pesan' => 'Hutang tidak ditemukan atau sudah lunas.']);
        $this->patchJson('/api/hutang', ['id' => 'abc'])->assertNotFound();

        $d = $this->getJson('/api/hutang')->json('data');
        $this->assertSame(['jumlah' => 0, 'total' => 0], $d['aktif']);
        $this->assertNotNull($d['hutang'][0]['lunas_pada']);
    }

    public function test_isolasi_antar_toko(): void
    {
        $tokoA = $this->buatToko();
        $this->masukSebagai($this->buatAkun($tokoA, 'kasir'));
        $this->postJson('/api/hutang', ['nama_pelanggan' => 'Bu Siti', 'jumlah' => 5000]);
        $id = Hutang::value('id');

        $this->masukSebagai($this->buatAkun($this->buatToko(), 'pemilik'));
        $this->getJson('/api/hutang')->assertOk()->assertJson(['data' => ['hutang' => [], 'aktif' => ['jumlah' => 0, 'total' => 0]]]);
        $this->patchJson('/api/hutang', ['id' => $id])->assertNotFound();
        $this->assertSame('aktif', Hutang::withoutGlobalScope('toko')->find($id)->status);
    }
}
