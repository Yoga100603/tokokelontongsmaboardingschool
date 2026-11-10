<?php

namespace App\Http\Controllers;

use App\Services\RekapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Port app/(app)/laporan/page.tsx + app/api/laporan/route.ts + app/api/laporan/unduh/route.ts (pemilik). */
class LaporanController extends Controller
{
    public function halaman(): View
    {
        return view('laporan.index');
    }

    /** Laporan penjualan per rentang tanggal (pemilik). */
    public function data(Request $request, RekapService $svc): JsonResponse
    {
        $tokoId = $this->tokoId();
        ['dari' => $dari, 'sampai' => $sampai] = $svc->rentang($request);

        $peta = DB::table('transaksi')
            ->where('toko_id', $tokoId)->whereBetween('tanggal', [$dari, $sampai])->where('status', 'selesai')
            ->groupBy('tanggal')->orderBy('tanggal')
            ->selectRaw('tanggal, COUNT(*) AS jumlah_transaksi, SUM(total) AS penjualan, SUM(total - total_modal) AS laba')
            ->get()
            ->keyBy(fn ($h) => substr((string) $h->tanggal, 0, 10));

        // isi tanggal kosong supaya grafik berkesinambungan
        $harian = [];
        for ($t = Carbon::parse($dari); $t->toDateString() <= $sampai; $t->addDay()) {
            $k = $t->toDateString();
            $h = $peta->get($k);
            $harian[] = [
                'tanggal' => $k,
                'jumlah_transaksi' => (int) ($h->jumlah_transaksi ?? 0),
                'penjualan' => (int) ($h->penjualan ?? 0),
                'laba' => (int) ($h->laba ?? 0),
            ];
        }

        $r = $svc->rekap($tokoId, $dari, $sampai);
        $panjang = count($harian);
        $awal = Carbon::parse($dari);
        $sebelum = $svc->rekap($tokoId, $awal->copy()->subDays($panjang)->toDateString(), $awal->copy()->subDay()->toDateString());

        return $this->ok([
            'dari' => $dari,
            'sampai' => $sampai,
            'ringkasan' => $r + ['rata_rata_transaksi' => $r['jumlah_transaksi'] ? (int) round($r['total_penjualan'] / $r['jumlah_transaksi']) : 0],
            'periode_sebelumnya' => $sebelum,
            'harian' => $harian,
            'terlaris' => $svc->terlaris($tokoId, $dari, $sampai, 10),
            'per_metode' => $svc->perMetode($tokoId, $dari, $sampai),
        ]);
    }

    /** Unduh laporan penjualan sebagai CSV (dibuka di Excel; pemisah titik koma). */
    public function unduh(Request $request, RekapService $svc): Response
    {
        $u = $this->pengguna();
        $tokoId = $this->tokoId();
        ['dari' => $dari, 'sampai' => $sampai] = $svc->rentang($request);
        $r = $svc->rekap($tokoId, $dari, $sampai);
        $trx = DB::table('transaksi as t')->leftJoin('pengguna as p', 'p.id', '=', 't.pengguna_id')
            ->where('t.toko_id', $tokoId)->whereBetween('t.tanggal', [$dari, $sampai])->where('t.status', 'selesai')
            ->select('t.nomor_transaksi', 't.waktu', 'p.nama as kasir', 't.metode_bayar', 't.nama_pelanggan', 't.total', 't.total_modal')
            ->selectRaw('(SELECT SUM(jumlah) FROM item_transaksi i WHERE i.transaksi_id = t.id) AS jumlah_item')
            ->selectRaw('t.total - t.total_modal AS laba')
            ->orderBy('t.waktu')
            ->get();
        $top = $svc->terlaris($tokoId, $dari, $sampai, 50);

        $isi = [
            self::baris('LAPORAN PENJUALAN', $u->toko->nama),
            self::baris('Periode', "{$dari} s/d {$sampai}"),
            '',
            self::baris('Jumlah transaksi', $r['jumlah_transaksi']),
            self::baris('Barang terjual', $r['item_terjual']),
            self::baris('Total penjualan', $r['total_penjualan']),
            self::baris('Total modal', $r['total_modal']),
            self::baris('Laba kotor', $r['laba_kotor']),
            '',
            self::baris('PRODUK TERLARIS'),
            self::baris('No', 'Barang', 'Satuan', 'Jumlah terjual', 'Penjualan', 'Laba kotor'),
        ];
        foreach ($top as $i => $x) {
            $isi[] = self::baris($i + 1, $x['nama_barang'], $x['satuan'], $x['jumlah'], $x['penjualan'], $x['laba']);
        }
        $isi[] = '';
        $isi[] = self::baris('RINCIAN TRANSAKSI');
        $isi[] = self::baris('No. Struk', 'Waktu', 'Kasir', 'Metode', 'Pelanggan', 'Jumlah item', 'Total', 'Modal', 'Laba kotor');
        foreach ($trx as $x) {
            $isi[] = self::baris(
                $x->nomor_transaksi, $x->waktu, $x->kasir, $x->metode_bayar, $x->nama_pelanggan,
                $x->jumlah_item === null ? null : (int) $x->jumlah_item, (int) $x->total, (int) $x->total_modal, (int) $x->laba
            );
        }

        return response("\u{FEFF}".implode("\r\n", $isi), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"laporan-penjualan-{$dari}_{$sampai}.csv\"",
        ]);
    }

    /** Sel CSV aman: kutip ganda + cegah formula injection saat dibuka di Excel. */
    public static function sel(mixed $v): string
    {
        $s = $v === null ? '' : (string) $v;
        if (preg_match('/^[=+\-@\t\r]/', $s) && ! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            $s = "'".$s;
        }

        return '"'.str_replace('"', '""', $s).'"';
    }

    private static function baris(mixed ...$v): string
    {
        return implode(';', array_map([self::class, 'sel'], $v));
    }
}
