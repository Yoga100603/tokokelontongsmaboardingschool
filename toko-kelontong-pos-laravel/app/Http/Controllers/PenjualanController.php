<?php

namespace App\Http\Controllers;

use App\Services\RekapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Port app/(app)/penjualan/page.tsx + app/api/penjualan/route.ts. */
class PenjualanController extends Controller
{
    public function halaman(): View
    {
        return view('penjualan.index', ['pemilik' => $this->pengguna()->isPemilik()]);
    }

    /** Rekap penjualan harian: ringkasan, pembanding kemarin, status tutup buku, daftar transaksi. */
    public function data(Request $request, RekapService $svc): JsonResponse
    {
        $u = $this->pengguna();
        $tokoId = $this->tokoId();
        $q = $request->query('tanggal');
        $tanggal = RekapService::tanggalValid($q) ? $q : now()->toDateString();
        $kemarinTgl = Carbon::parse($tanggal)->subDay()->toDateString();
        $pemilik = $u->isPemilik();

        $r = $svc->rekap($tokoId, $tanggal, $tanggal);
        $kemarin = $svc->rekap($tokoId, $kemarinTgl, $kemarinTgl);

        $tb = DB::table('tutup_buku as tb')->leftJoin('pengguna as p', 'p.id', '=', 'tb.ditutup_oleh')
            ->where('tb.toko_id', $tokoId)->whereDate('tb.tanggal', $tanggal)
            ->select('tb.*', 'p.nama as ditutup_oleh_nama')
            ->first();
        $tutup = $tb ? [
            'id' => (int) $tb->id,
            'toko_id' => (int) $tb->toko_id,
            'tanggal' => substr((string) $tb->tanggal, 0, 10),
            'jumlah_transaksi' => (int) $tb->jumlah_transaksi,
            'total_penjualan' => (int) $tb->total_penjualan,
            'total_modal' => (int) $tb->total_modal,
            'laba_kotor' => (int) $tb->laba_kotor,
            'uang_tunai' => (int) $tb->uang_tunai,
            'catatan' => (string) ($tb->catatan ?? ''),
            'ditutup_oleh' => $tb->ditutup_oleh !== null ? (int) $tb->ditutup_oleh : null,
            'ditutup_pada' => (string) $tb->ditutup_pada,
            'ditutup_oleh_nama' => $tb->ditutup_oleh_nama,
        ] : null;

        $kolom = ['t.nomor_transaksi', 't.waktu', 't.total', 't.metode_bayar', 't.nama_pelanggan', 't.status', 'p.nama as kasir'];
        $transaksi = DB::table('transaksi as t')->leftJoin('pengguna as p', 'p.id', '=', 't.pengguna_id')
            ->where('t.toko_id', $tokoId)->where('t.tanggal', $tanggal)
            ->select($kolom)
            ->selectRaw('(SELECT COALESCE(SUM(jumlah),0) FROM item_transaksi i WHERE i.transaksi_id = t.id) AS jumlah_item')
            ->when($pemilik, fn ($q) => $q->selectRaw('t.total - t.total_modal AS laba'))
            ->orderByDesc('t.waktu')->orderByDesc('t.id')
            ->get()
            ->map(function ($t) use ($pemilik) {
                $x = [
                    'nomor_transaksi' => $t->nomor_transaksi,
                    'waktu' => (string) $t->waktu,
                    'total' => (int) $t->total,
                    'metode_bayar' => $t->metode_bayar,
                    'nama_pelanggan' => (string) $t->nama_pelanggan,
                    'status' => $t->status,
                    'kasir' => $t->kasir,
                    'jumlah_item' => (int) $t->jumlah_item,
                ];
                if ($pemilik) {
                    $x['laba'] = (int) $t->laba;
                }

                return $x;
            })->all();

        $terlaris = $svc->terlaris($tokoId, $tanggal, $tanggal, 5);
        if (! $pemilik) {
            unset($r['total_modal'], $r['laba_kotor'], $kemarin['total_modal'], $kemarin['laba_kotor']);
            if ($tutup) {
                unset($tutup['total_modal'], $tutup['laba_kotor']);
            }
            $terlaris = array_map(function ($x) {
                unset($x['laba']);

                return $x;
            }, $terlaris);
        }

        return $this->ok([
            'tanggal' => $tanggal,
            'hari_ini' => now()->toDateString(),
            'ringkasan' => $r,
            'kemarin' => $kemarin,
            'per_metode' => $svc->perMetode($tokoId, $tanggal, $tanggal),
            'terlaris' => $terlaris,
            'tutup_buku' => $tutup,
            'transaksi' => $transaksi,
        ]);
    }
}
