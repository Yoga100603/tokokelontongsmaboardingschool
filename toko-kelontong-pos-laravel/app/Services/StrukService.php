<?php

namespace App\Services;

use App\Models\Toko;
use Illuminate\Support\Facades\DB;

/** Data & teks struk (setara lib/struk.ts). Bentuk array sama dengan DataStruk versi Next.js. */
class StrukService
{
    public function ambil(int $tokoId, string $nomor, bool $sertakanModal = false): ?array
    {
        $trx = DB::table('transaksi as t')->leftJoin('pengguna as p', 'p.id', '=', 't.pengguna_id')
            ->where('t.toko_id', $tokoId)->where('t.nomor_transaksi', $nomor)
            ->select('t.id', 't.nomor_transaksi', 't.tanggal', 't.waktu', 't.total', 't.total_modal', 't.uang_bayar',
                't.kembalian', 't.metode_bayar', 't.nama_pelanggan', 't.status', 'p.nama as kasir')
            ->first();
        if (! $trx) {
            return null;
        }

        $data = (array) $trx;
        foreach (['id', 'total', 'total_modal', 'uang_bayar', 'kembalian'] as $k) {
            $data[$k] = (int) $data[$k];
        }
        $data['tanggal'] = substr((string) $data['tanggal'], 0, 10);
        $data['waktu'] = substr((string) $data['waktu'], 0, 19);
        $data['kasir'] = (string) ($data['kasir'] ?? '');
        if (! $sertakanModal) {
            unset($data['total_modal']);
        }

        $data['toko'] = Toko::query()->whereKey($tokoId)
            ->first(['nama', 'alamat', 'telepon', 'logo_url', 'catatan_struk', 'slug'])
            ->only(['nama', 'alamat', 'telepon', 'logo_url', 'catatan_struk', 'slug']);

        $data['item'] = DB::table('item_transaksi')->where('transaksi_id', $trx->id)->orderBy('id')
            ->get(['nama_barang', 'satuan', 'jumlah', 'harga_jual', 'subtotal'])
            ->map(fn ($i) => [
                'nama_barang' => $i->nama_barang, 'satuan' => $i->satuan, 'jumlah' => (int) $i->jumlah,
                'harga_jual' => (int) $i->harga_jual, 'subtotal' => (int) $i->subtotal,
            ])->all();

        $data['log'] = DB::table('log_struk as l')->leftJoin('pengguna as p', 'p.id', '=', 'l.pengguna_id')
            ->where('l.transaksi_id', $trx->id)->orderByDesc('l.id')
            ->get(['l.aksi', 'l.tujuan', 'l.dibuat_pada', 'p.nama as oleh'])
            ->map(fn ($l) => ['aksi' => $l->aksi, 'tujuan' => $l->tujuan, 'dibuat_pada' => substr((string) $l->dibuat_pada, 0, 19), 'oleh' => (string) $l->oleh])
            ->all();

        $data['sudah_tutup_buku'] = DB::table('tutup_buku')->where('toko_id', $tokoId)->whereDate('tanggal', $data['tanggal'])->exists();

        return $data;
    }

    /** Teks struk polos (untuk disimpan sebagai .txt atau dikirim via WhatsApp). */
    public static function teks(array $s): string
    {
        $rp = fn ($n) => 'Rp'.number_format((int) $n, 0, ',', '.');
        $garis = str_repeat('-', 32);
        $baris = [
            mb_strtoupper($s['toko']['nama']),
            $s['toko']['alamat'] ?? '',
            ! empty($s['toko']['telepon']) ? 'Telp '.$s['toko']['telepon'] : '',
            $garis,
            'No   : '.$s['nomor_transaksi'],
            'Waktu: '.$s['waktu'],
            'Kasir: '.$s['kasir'],
            ! empty($s['nama_pelanggan']) ? 'Plgn : '.$s['nama_pelanggan'] : '',
            $garis,
        ];
        foreach ($s['item'] as $i) {
            $baris[] = $i['nama_barang'];
            $baris[] = "  {$i['jumlah']} {$i['satuan']} x {$rp($i['harga_jual'])} = {$rp($i['subtotal'])}";
        }
        array_push($baris,
            $garis,
            'TOTAL    : '.$rp($s['total']),
            'BAYAR    : '.$rp($s['uang_bayar'])." ({$s['metode_bayar']})",
            'KEMBALI  : '.$rp($s['kembalian']),
            $garis,
            $s['toko']['catatan_struk'] ?? '',
        );

        return implode("\n", array_values(array_filter($baris, fn ($x) => $x !== '' && $x !== null)));
    }
}
