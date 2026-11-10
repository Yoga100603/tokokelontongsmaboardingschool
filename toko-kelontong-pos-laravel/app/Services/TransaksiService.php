<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Hutang;
use App\Models\ItemTransaksi;
use App\Models\Pengguna;
use App\Models\Toko;
use App\Models\Transaksi;
use App\Support\GalatApi;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan transaksi penjualan (setara POST /api/transaksi versi Next.js).
 * Harga & total dihitung ulang di server dari data barang (angka dari browser
 * diabaikan). Transaksi, rincian, pengurangan stok, mutasi, dan hutang terjadi
 * dalam SATU transaksi database: gagal satu → batal semua.
 */
class TransaksiService
{
    public const METODE = ['tunai', 'transfer', 'qris', 'hutang'];

    public function __construct(private MutasiStokService $stok) {}

    /** @return string nomor transaksi yang dibuat */
    public function buat(Pengguna $u, array $b): string
    {
        $item = $b['item'] ?? null;
        if (! is_array($item) || ! array_is_list($item) || count($item) === 0) {
            GalatApi::tolak(422, 'Keranjang belanja masih kosong.');
        }
        if (count($item) > 200) {
            GalatApi::tolak(422, 'Jumlah baris keranjang terlalu banyak.');
        }

        /** @var array<int,int> $diminta barang_id => jumlah (baris kembar digabung) */
        $diminta = [];
        foreach ($item as $it) {
            $id = is_array($it) ? self::bulat($it['barang_id'] ?? null) : null;
            if ($id === null || $id <= 0) {
                GalatApi::tolak(422, 'Ada barang yang tidak valid di keranjang.');
            }
            $qty = self::angka($it['jumlah'] ?? null, 'Jumlah barang', min: 1, maks: 100_000);
            $diminta[$id] = ($diminta[$id] ?? 0) + $qty;
        }

        $metode = in_array($b['metode_bayar'] ?? null, self::METODE, true) ? $b['metode_bayar'] : 'tunai';
        $namaPelanggan = self::teks($b['nama_pelanggan'] ?? null, 'Nama pelanggan', maks: 80, wajib: $metode === 'hutang');
        $kontak = self::teks($b['kontak'] ?? null, 'Kontak pelanggan', maks: 30);
        $tokoId = (int) $u->toko_id;

        // Urutan id tetap → baris barang selalu dikunci berurutan (tidak deadlock antar kasir).
        ksort($diminta);

        return DB::transaction(function () use ($u, $b, $tokoId, $diminta, $metode, $namaPelanggan, $kontak) {
            // Antre per toko: nomor urut, cek tutup buku, dan stok konsisten saat banyak kasir bayar bersamaan.
            Toko::kunci($tokoId);
            $kini = now();
            $tanggal = $kini->toDateString();
            $waktu = $kini->format('Y-m-d H:i:s');

            if (DB::table('tutup_buku')->where('toko_id', $tokoId)->whereDate('tanggal', $tanggal)->exists()) {
                GalatApi::tolak(409, 'Buku hari ini sudah ditutup. Transaksi baru tidak bisa disimpan.');
            }

            $baris = [];
            $total = 0;
            $totalModal = 0;
            foreach ($diminta as $id => $jumlah) {
                $br = Barang::query()->where('barang.toko_id', $tokoId)->where('aktif', true)->whereKey($id)->first();
                if (! $br) {
                    GalatApi::tolak(422, 'Ada barang yang tidak ditemukan atau sudah tidak dijual. Muat ulang daftar barang.');
                }
                if ($jumlah > $br->stok) {
                    GalatApi::tolak(422, "Stok {$br->nama_barang} tidak cukup. Sisa {$br->stok} {$br->satuan}.");
                }
                $baris[] = ['id' => $id, 'nama' => $br->nama_barang, 'satuan' => $br->satuan, 'jumlah' => $jumlah,
                    'jual' => (int) $br->harga_jual, 'modal' => (int) $br->harga_modal];
                $total += $br->harga_jual * $jumlah;
                $totalModal += $br->harga_modal * $jumlah;
            }

            $kembalian = 0;
            if ($metode === 'tunai') {
                $uangBayar = self::angka($b['uang_bayar'] ?? null, 'Uang bayar', min: 0);
                if ($uangBayar < $total) {
                    GalatApi::tolak(422, 'Uang pembayaran kurang Rp'.number_format($total - $uangBayar, 0, ',', '.').'.');
                }
                $kembalian = $uangBayar - $total;
            } elseif ($metode === 'hutang') {
                $uangBayar = self::angka($b['uang_bayar'] ?? null, 'Uang muka', min: 0, wajib: false);
                if ($uangBayar >= $total) {
                    GalatApi::tolak(422, 'Uang muka sudah menutup total. Pilih metode Tunai.');
                }
            } else {
                $uangBayar = $total;
            }

            $nomor = $this->nomorBerikut($tokoId, $tanggal);

            $trx = new Transaksi([
                'pengguna_id' => $u->id,
                'nomor_transaksi' => $nomor,
                'tanggal' => $tanggal,
                'waktu' => $waktu,
                'total' => $total,
                'total_modal' => $totalModal,
                'uang_bayar' => $uangBayar,
                'kembalian' => $kembalian,
                'metode_bayar' => $metode,
                'nama_pelanggan' => $namaPelanggan,
            ]);
            $trx->toko_id = $tokoId;
            $trx->save();

            foreach ($baris as $x) {
                ItemTransaksi::create([
                    'transaksi_id' => $trx->id,
                    'barang_id' => $x['id'],
                    'nama_barang' => $x['nama'],
                    'satuan' => $x['satuan'],
                    'jumlah' => $x['jumlah'],
                    'harga_jual' => $x['jual'],
                    'harga_modal' => $x['modal'],
                    'subtotal' => $x['jual'] * $x['jumlah'],
                ]);
                $this->stok->ubah($tokoId, $x['id'], $u->id, 'penjualan', -$x['jumlah'], 'Penjualan', $nomor);
            }

            if ($metode === 'hutang') {
                $h = new Hutang([
                    'transaksi_id' => $trx->id,
                    'nama_pelanggan' => $namaPelanggan,
                    'kontak' => $kontak,
                    'jumlah' => $total - $uangBayar,
                    'catatan' => "Dari transaksi {$nomor}",
                ]);
                $h->toko_id = $tokoId;
                $h->save();
            }

            return $nomor;
        });
    }

    /** TRX-YYYYMMDD-NNNN, urut per toko per hari (unik per toko — dijaga juga oleh unique index). */
    private function nomorBerikut(int $tokoId, string $tanggal): string
    {
        $n = DB::table('transaksi')->where('toko_id', $tokoId)->whereDate('tanggal', $tanggal)->count();
        $awalan = 'TRX-'.str_replace('-', '', $tanggal).'-';
        do {
            $n++;
            $nomor = $awalan.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        } while (DB::table('transaksi')->where('toko_id', $tokoId)->where('nomor_transaksi', $nomor)->exists());

        return $nomor;
    }

    // ── Validasi setara teks()/angka() di lib/api.ts ──────────────────

    public static function teks(mixed $v, string $label, int $maks = 255, bool $wajib = false, int $min = 0): string
    {
        $s = is_string($v) ? trim($v) : ($v === null || is_array($v) ? '' : trim((string) (is_bool($v) ? ($v ? 'true' : 'false') : $v)));
        if ($wajib && $s === '') {
            GalatApi::tolak(422, "{$label} wajib diisi.");
        }
        if ($s !== '' && mb_strlen($s) < $min) {
            GalatApi::tolak(422, "{$label} minimal {$min} karakter.");
        }
        if (mb_strlen($s) > $maks) {
            GalatApi::tolak(422, "{$label} maksimal {$maks} karakter.");
        }

        return $s;
    }

    public static function angka(mixed $v, string $label, int $min = 0, int $maks = 1_000_000_000_000, bool $wajib = true, int $bawaan = 0): int
    {
        if ($v === null || $v === '') {
            if ($wajib) {
                GalatApi::tolak(422, "{$label} wajib diisi.");
            }

            return $bawaan;
        }
        if (is_int($v)) {
            $n = $v;
        } elseif (is_float($v) && is_finite($v) && floor($v) == $v) {
            $n = (int) $v;
        } elseif (is_string($v) && preg_match('/^-?\d+$/', $d = preg_replace('/[^\d-]/', '', $v))) {
            $n = (int) $d;
        } else {
            GalatApi::tolak(422, "{$label} harus berupa bilangan bulat.");
        }
        if ($n < $min) {
            GalatApi::tolak(422, "{$label} minimal {$min}.");
        }
        if ($n > $maks) {
            GalatApi::tolak(422, "{$label} terlalu besar.");
        }

        return $n;
    }

    /** Number(x) + Number.isInteger: hanya bilangan bulat (int, float bulat, atau string angka). */
    private static function bulat(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v) && is_finite($v) && floor($v) == $v) {
            return (int) $v;
        }
        if (is_string($v) && preg_match('/^\s*\d+\s*$/', $v)) {
            return (int) $v;
        }

        return null;
    }
}
