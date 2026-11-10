<?php

namespace Tests\Feature;

use App\Models\Transaksi;
use App\Models\TutupBuku;
use App\Services\RekapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PunyaToko;
use Tests\TestCase;

/** Regresi: `tanggal` diisi Carbon/datetime tetap tersimpan "Y-m-d" sehingga rekap rentang inklusif hari terakhir. */
class TanggalRekapTest extends TestCase
{
    use PunyaToko, RefreshDatabase;

    public function test_tanggal_carbon_disimpan_sebagai_y_m_d_dan_masuk_rekap_hari_terakhir(): void
    {
        $toko = $this->buatToko();
        $kasir = $this->buatAkun($toko, 'kasir');

        $t = new Transaksi([
            'pengguna_id' => $kasir->id, 'nomor_transaksi' => 'TRX-1', 'tanggal' => now(), 'waktu' => now(),
            'total' => 10000, 'total_modal' => 7000, 'uang_bayar' => 10000, 'kembalian' => 0,
        ]);
        $t->toko_id = $toko->id;
        $t->save();

        $tb = new TutupBuku(['tanggal' => now()->subDay(), 'jumlah_transaksi' => 0, 'total_penjualan' => 0,
            'total_modal' => 0, 'laba_kotor' => 0, 'ditutup_pada' => now()]);
        $tb->toko_id = $toko->id;
        $tb->save();

        $this->assertSame(now()->toDateString(), DB::table('transaksi')->value('tanggal'));
        $this->assertSame(now()->subDay()->toDateString(), DB::table('tutup_buku')->value('tanggal'));

        $hari = now()->toDateString();
        $r = app(RekapService::class)->rekap($toko->id, $hari, $hari);
        $this->assertSame(1, $r['jumlah_transaksi']);
        $this->assertSame(3000, $r['laba_kotor']);
    }
}
