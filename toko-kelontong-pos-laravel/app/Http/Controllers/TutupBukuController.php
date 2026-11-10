<?php

namespace App\Http\Controllers;

use App\Models\Toko;
use App\Models\TutupBuku;
use App\Services\RekapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tutup Buku Harian (pemilik) — port app/api/penjualan/tutup-buku/route.ts.
 * Angka hari itu dibekukan ke tabel tutup_buku dan tanggal tersebut terkunci:
 * kasir tidak bisa lagi menyimpan transaksi pada tanggal yang sudah ditutup.
 */
class TutupBukuController extends Controller
{
    public function simpan(Request $request, RekapService $svc): JsonResponse
    {
        $u = $this->pengguna();
        $tokoId = $this->tokoId();
        $tanggal = $request->input('tanggal');
        if (! RekapService::tanggalValid($tanggal)) {
            $this->tolak(422, 'Tanggal tidak valid.');
        }
        if ($tanggal > now()->toDateString()) {
            $this->tolak(422, 'Tidak bisa menutup buku untuk tanggal yang belum terjadi.');
        }
        $c = $request->input('catatan');
        $catatan = is_scalar($c) ? trim((string) $c) : '';
        if (mb_strlen($catatan) > 300) {
            $this->tolak(422, 'Catatan maksimal 300 karakter.');
        }

        $hasil = DB::transaction(function () use ($svc, $u, $tokoId, $tanggal, $catatan) {
            // Antre dengan transaksi kasir: rekap yang dibekukan pasti memuat semua transaksi yang sudah commit.
            Toko::kunci($tokoId);
            if (DB::table('tutup_buku')->where('toko_id', $tokoId)->whereDate('tanggal', $tanggal)->lockForUpdate()->exists()) {
                $this->tolak(409, "Buku tanggal {$tanggal} sudah ditutup sebelumnya.");
            }
            $r = $svc->rekap($tokoId, $tanggal, $tanggal);
            if ($r['jumlah_transaksi'] === 0) {
                $this->tolak(422, 'Belum ada transaksi pada tanggal ini, tutup buku tidak diperlukan.');
            }
            $tb = new TutupBuku([
                'tanggal' => $tanggal,
                'jumlah_transaksi' => $r['jumlah_transaksi'],
                'total_penjualan' => $r['total_penjualan'],
                'total_modal' => $r['total_modal'],
                'laba_kotor' => $r['laba_kotor'],
                'uang_tunai' => $r['uang_tunai'],
                'catatan' => $catatan,
                'ditutup_oleh' => $u->id,
                'ditutup_pada' => now(),
            ]);
            $tb->toko_id = $tokoId;
            $tb->save();

            return $r;
        });

        return $this->ok($hasil, "Buku tanggal {$tanggal} berhasil ditutup.");
    }
}
