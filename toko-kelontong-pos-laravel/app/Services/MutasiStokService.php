<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\MutasiStok;
use App\Support\GalatApi;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA pintu perubahan stok (setara lib/stok.ts).
 * Wajib dipanggil di dalam DB::transaction(): baris barang dikunci (lockForUpdate)
 * sehingga dua transaksi kasir bersamaan tidak saling menimpa stok, dan
 * update stok + catatan mutasi_stok selalu terjadi bersamaan.
 */
class MutasiStokService
{
    public const JENIS = ['awal', 'masuk', 'keluar', 'penjualan', 'penyesuaian'];

    /**
     * @param  int  $perubahan  bertanda: positif menambah, negatif mengurangi
     * @return array{stok_sebelum:int, stok_sesudah:int, barang:Barang}
     */
    public function ubah(
        int $tokoId,
        int $barangId,
        ?int $penggunaId,
        string $jenis,
        int $perubahan,
        string $keterangan = '',
        string $referensi = '',
    ): array {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('MutasiStokService::ubah wajib dipanggil di dalam DB::transaction().');
        }
        if (! in_array($jenis, self::JENIS, true)) {
            throw new \InvalidArgumentException("Jenis mutasi tidak dikenal: $jenis");
        }

        $b = Barang::withoutGlobalScope('toko')
            ->where('toko_id', $tokoId)
            ->whereKey($barangId)
            ->lockForUpdate()
            ->first();
        if (! $b) {
            GalatApi::tolak(404, 'Barang tidak ditemukan.');
        }

        $sebelum = (int) $b->stok;
        if ($perubahan === 0) {
            return ['stok_sebelum' => $sebelum, 'stok_sesudah' => $sebelum, 'barang' => $b];
        }

        $sesudah = $sebelum + $perubahan;
        if ($sesudah < 0) {
            GalatApi::tolak(422, "Stok {$b->nama_barang} tidak cukup. Sisa {$sebelum} {$b->satuan}.");
        }

        $b->stok = $sesudah;
        $b->save(); // ikut memperbarui diubah_pada

        $m = new MutasiStok([
            'barang_id' => $b->id,
            'pengguna_id' => $penggunaId,
            'jenis' => $jenis,
            'jumlah' => $perubahan,
            'stok_sebelum' => $sebelum,
            'stok_sesudah' => $sesudah,
            'keterangan' => mb_substr($keterangan, 0, 255),
            'referensi' => mb_substr($referensi, 0, 64),
        ]);
        $m->toko_id = $tokoId;
        $m->save();

        return ['stok_sebelum' => $sebelum, 'stok_sesudah' => $sesudah, 'barang' => $b];
    }
}
