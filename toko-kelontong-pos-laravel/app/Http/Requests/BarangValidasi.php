<?php

namespace App\Http\Requests;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Support\GalatApi;
use Illuminate\Http\Request;

/**
 * Port validasi sederhana `teks()` / `angka()` (lib/api.ts) dan `validasiBarang()` (lib/barang.ts).
 * Pesan galat sama persis dengan versi Next.js. Dipakai modul Barang/Kategori/Stok.
 */
class BarangValidasi
{
    /** Setara bacaJson(): body wajib objek JSON. */
    public static function body(Request $r): array
    {
        $isi = $r->isJson() ? $r->json()->all() : $r->all();
        if (! is_array($isi) || array_is_list($isi) && $isi !== []) {
            GalatApi::tolak(400, 'Format data tidak valid.');
        }

        return $isi;
    }

    public static function teks(mixed $v, string $label, bool $wajib = false, int $maks = 255, int $min = 0): string
    {
        $s = is_string($v) ? trim($v) : ($v === null ? '' : trim(is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) ? (string) $v : '')));
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
        } elseif (is_float($v)) {
            if (! is_finite($v) || floor($v) !== $v) {
                GalatApi::tolak(422, "{$label} harus berupa bilangan bulat.");
            }
            $n = (int) $v;
        } else {
            // Number(String(v).replace(/[^\d-]/g, "")) — "10.000" → 10000, "" → 0
            $s = preg_replace('/[^\d-]/', '', is_scalar($v) ? (string) $v : '');
            if ($s === '') {
                $n = 0;
            } elseif (! preg_match('/^-?\d+$/', $s) || strlen(ltrim($s, '-')) > 18) {
                GalatApi::tolak(422, "{$label} harus berupa bilangan bulat.");
            } else {
                $n = (int) $s;
            }
        }
        if ($n < $min) {
            GalatApi::tolak(422, "{$label} minimal {$min}.");
        }
        if ($n > $maks) {
            GalatApi::tolak(422, "{$label} terlalu besar.");
        }

        return $n;
    }

    /** Setara validasiBarang(): kategori harus milik toko, kode unik per toko (409). */
    public static function barang(int $tokoId, array $b, ?int $idSekarang = null): array
    {
        $data = [
            'kode_barang' => self::teks($b['kode_barang'] ?? null, 'Kode barang', wajib: true, maks: 50),
            'nama_barang' => self::teks($b['nama_barang'] ?? null, 'Nama barang', wajib: true, maks: 150),
            'satuan' => self::teks($b['satuan'] ?? null, 'Satuan', maks: 20) ?: 'pcs',
            'harga_jual' => self::angka($b['harga_jual'] ?? null, 'Harga jual'),
            'harga_modal' => self::angka($b['harga_modal'] ?? null, 'Harga modal', wajib: false),
            'stok_minimum' => self::angka($b['stok_minimum'] ?? null, 'Batas stok menipis', wajib: false, bawaan: 5),
            'tampil_di_katalog' => ($b['tampil_di_katalog'] ?? null) === false || ($b['tampil_di_katalog'] ?? null) === 0 ? 0 : 1,
            'kategori_id' => null,
        ];

        $k = $b['kategori_id'] ?? null;
        if ($k !== null && $k !== '' && $k !== 0 && $k !== false) {
            $ada = is_numeric($k) && KategoriBarang::withoutGlobalScope('toko')
                ->where('toko_id', $tokoId)->whereKey((int) $k)->exists();
            if (! $ada) {
                GalatApi::tolak(422, 'Kategori tidak ditemukan.');
            }
            $data['kategori_id'] = (int) $k;
        }

        $kembar = Barang::withoutGlobalScope('toko')
            ->where('toko_id', $tokoId)
            ->where('kode_barang', $data['kode_barang'])
            ->where('id', '<>', $idSekarang ?? 0)
            ->exists();
        if ($kembar) {
            GalatApi::tolak(409, "Kode barang {$data['kode_barang']} sudah dipakai barang lain.");
        }

        return $data;
    }
}
