<?php

namespace App\Http\Controllers;

use App\Services\RekapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Port app/(app)/dasbor/page.tsx + app/api/dasbor/route.ts — satu halaman kondisi toko untuk pemilik. */
class DasborController extends Controller
{
    public function halaman(): View
    {
        return view('dasbor.index', ['nama' => $this->pengguna()->nama]);
    }

    public function data(RekapService $svc): JsonResponse
    {
        $tokoId = $this->tokoId();
        $hari = now()->toDateString();
        $awal = Carbon::parse($hari)->subDays(6)->toDateString();
        $kemarin = Carbon::parse($hari)->subDay()->toDateString();

        $peta = DB::table('transaksi')
            ->where('toko_id', $tokoId)->whereBetween('tanggal', [$awal, $hari])->where('status', 'selesai')
            ->groupBy('tanggal')
            ->selectRaw('tanggal, SUM(total) AS penjualan, SUM(total - total_modal) AS laba, COUNT(*) AS jumlah')
            ->get()
            ->keyBy(fn ($x) => substr((string) $x->tanggal, 0, 10));
        $grafik = [];
        for ($i = 6; $i >= 0; $i--) {
            $t = Carbon::parse($hari)->subDays($i)->toDateString();
            $x = $peta->get($t);
            $grafik[] = [
                'tanggal' => $t,
                'penjualan' => (int) ($x->penjualan ?? 0),
                'laba' => (int) ($x->laba ?? 0),
                'jumlah' => (int) ($x->jumlah ?? 0),
            ];
        }

        $menipis = DB::table('barang')
            ->where('toko_id', $tokoId)->where('aktif', true)->whereColumn('stok', '<=', 'stok_minimum')
            ->orderBy('stok')->orderBy('nama_barang')->limit(8)
            ->get(['id', 'nama_barang', 'stok', 'stok_minimum', 'satuan'])
            ->map(fn ($b) => [
                'id' => (int) $b->id, 'nama_barang' => $b->nama_barang, 'stok' => (int) $b->stok,
                'stok_minimum' => (int) $b->stok_minimum, 'satuan' => $b->satuan,
            ])->all();

        $opname = DB::table('opname')->where('toko_id', $tokoId)->where('status', 'diajukan')->orderBy('id')
            ->get(['id', 'kode', 'diajukan_pada'])
            ->map(fn ($o) => ['id' => (int) $o->id, 'kode' => $o->kode, 'diajukan_pada' => $o->diajukan_pada])->all();

        $belumTutup = DB::table('transaksi as t')
            ->where('t.toko_id', $tokoId)->where('t.status', 'selesai')->where('t.tanggal', '<', $hari)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('tutup_buku as b')
                ->whereColumn('b.toko_id', 't.toko_id')->whereColumn('b.tanggal', 't.tanggal'))
            ->groupBy('t.tanggal')->orderByDesc('t.tanggal')->limit(5)
            ->selectRaw('t.tanggal, COUNT(*) AS jumlah')
            ->get()
            ->map(fn ($b) => ['tanggal' => substr((string) $b->tanggal, 0, 10), 'jumlah' => (int) $b->jumlah])->all();

        $ditutup = DB::table('tutup_buku')->where('toko_id', $tokoId)->whereDate('tanggal', $hari)->exists();
        $h = DB::table('hutang')->where('toko_id', $tokoId)->where('status', 'aktif')
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(jumlah),0) AS total')->first();

        return $this->ok([
            'tanggal' => $hari,
            'hari_ini' => $svc->rekap($tokoId, $hari, $hari),
            'kemarin' => $svc->rekap($tokoId, $kemarin, $kemarin),
            'grafik' => $grafik,
            'terlaris' => $svc->terlaris($tokoId, $awal, $hari, 5),
            'stok_menipis' => $menipis,
            'opname_menunggu' => $opname,
            'belum_tutup_buku' => $belumTutup,
            'hari_ini_ditutup' => $ditutup,
            'hutang' => ['jumlah' => (int) $h->jumlah, 'total' => (int) $h->total],
        ]);
    }
}
