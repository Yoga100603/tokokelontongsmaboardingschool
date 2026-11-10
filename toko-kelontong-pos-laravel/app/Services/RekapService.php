<?php

namespace App\Services;

use App\Support\GalatApi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Rekap penjualan (setara lib/rekap.ts). Semua fungsi menerima tokoId eksplisit. */
class RekapService
{
    public static function tanggalValid(mixed $s): bool
    {
        if (! is_string($s) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $s));

        return checkdate($m, $d, $y);
    }

    /** Ambil & validasi ?dari=&sampai= (default 7 hari terakhir). @return array{dari:string, sampai:string} */
    public function rentang(Request $r): array
    {
        $sampai = self::tanggalValid($r->query('sampai')) ? $r->query('sampai') : now()->toDateString();
        $dari = self::tanggalValid($r->query('dari')) ? $r->query('dari') : Carbon::parse($sampai)->subDays(6)->toDateString();
        if ($dari > $sampai) {
            GalatApi::tolak(422, 'Tanggal awal harus sebelum tanggal akhir.');
        }
        if (Carbon::parse($dari)->diffInDays(Carbon::parse($sampai)) > 366) {
            GalatApi::tolak(422, 'Rentang laporan maksimal 1 tahun.');
        }

        return ['dari' => $dari, 'sampai' => $sampai];
    }

    /** Rekap transaksi selesai dalam rentang tanggal (inklusif). */
    public function rekap(int $tokoId, string $dari, string $sampai): array
    {
        $r = DB::table('transaksi')
            ->where('toko_id', $tokoId)->whereBetween('tanggal', [$dari, $sampai])->where('status', 'selesai')
            ->selectRaw('COUNT(*) AS jumlah_transaksi,
                COALESCE(SUM(total),0) AS total_penjualan,
                COALESCE(SUM(total_modal),0) AS total_modal,
                COALESCE(SUM(total - total_modal),0) AS laba_kotor,
                COALESCE(SUM(uang_bayar - kembalian),0) AS uang_tunai')
            ->first();

        $item = DB::table('item_transaksi as i')->join('transaksi as x', 'x.id', '=', 'i.transaksi_id')
            ->where('x.toko_id', $tokoId)->whereBetween('x.tanggal', [$dari, $sampai])->where('x.status', 'selesai')
            ->sum('i.jumlah');

        return [
            'jumlah_transaksi' => (int) $r->jumlah_transaksi,
            'total_penjualan' => (int) $r->total_penjualan,
            'total_modal' => (int) $r->total_modal,
            'laba_kotor' => (int) $r->laba_kotor,
            'item_terjual' => (int) $item,
            'uang_tunai' => (int) $r->uang_tunai,
        ];
    }

    public function perMetode(int $tokoId, string $dari, string $sampai): array
    {
        return DB::table('transaksi')
            ->where('toko_id', $tokoId)->whereBetween('tanggal', [$dari, $sampai])->where('status', 'selesai')
            ->groupBy('metode_bayar')
            ->selectRaw('metode_bayar, COUNT(*) AS jumlah, SUM(total) AS total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($x) => ['metode_bayar' => $x->metode_bayar, 'jumlah' => (int) $x->jumlah, 'total' => (int) $x->total])
            ->all();
    }

    public function terlaris(int $tokoId, string $dari, string $sampai, int $batas = 10): array
    {
        return DB::table('item_transaksi as i')->join('transaksi as t', 't.id', '=', 'i.transaksi_id')
            ->where('t.toko_id', $tokoId)->whereBetween('t.tanggal', [$dari, $sampai])->where('t.status', 'selesai')
            ->groupBy('i.barang_id', 'i.nama_barang', 'i.satuan')
            ->selectRaw('i.barang_id, i.nama_barang, i.satuan, SUM(i.jumlah) AS jumlah, SUM(i.subtotal) AS penjualan,
                SUM(i.subtotal - i.harga_modal * i.jumlah) AS laba')
            ->orderByDesc('jumlah')->orderByDesc('penjualan')
            ->limit($batas)
            ->get()
            ->map(fn ($x) => [
                'barang_id' => $x->barang_id, 'nama_barang' => $x->nama_barang, 'satuan' => $x->satuan,
                'jumlah' => (int) $x->jumlah, 'penjualan' => (int) $x->penjualan, 'laba' => (int) $x->laba,
            ])
            ->all();
    }
}
