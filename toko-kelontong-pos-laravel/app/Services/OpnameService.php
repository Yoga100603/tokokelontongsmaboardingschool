<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Opname;
use App\Support\GalatApi;
use Illuminate\Support\Facades\DB;

/**
 * Logika alur stok opname (setara app/api/opname/** versi Next.js).
 * Semua method dipanggil saat pengguna login: model Opname/Barang otomatis terfilter toko (MilikToko).
 */
class OpnameService
{
    public function __construct(private MutasiStokService $stok) {}

    /** Setara teks() di lib/api.ts. */
    public static function teks(mixed $v, string $label, bool $wajib = false, int $maks = 255, int $min = 0): string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';
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

    /** Mulai sesi draft: potret stok sistem semua barang aktif (atau satu kategori). */
    public function mulai(int $tokoId, int $penggunaId, string $catatan, ?int $kategoriId): int
    {
        return DB::transaction(function () use ($tokoId, $penggunaId, $catatan, $kategoriId) {
            \App\Models\Toko::kunci($tokoId); // antre per toko (cegah 2 sesi opname bersamaan)
            $aktif = Opname::whereIn('status', ['draft', 'diajukan'])->lockForUpdate()->exists();
            if ($aktif) {
                GalatApi::tolak(409, 'Masih ada sesi opname yang belum selesai. Selesaikan atau tunggu keputusan pemilik dulu.');
            }

            $tanggal = now()->format('Ymd'); // zona waktu aplikasi = Asia/Jakarta (WIB)
            $n = Opname::where('kode', 'like', "OPN-{$tanggal}-%")->count() + 1;
            $kode = fn (int $n) => sprintf('OPN-%s-%02d', $tanggal, $n);
            while (Opname::where('kode', $kode($n))->exists()) {
                $n++;
            }

            $o = Opname::create(['kode' => $kode($n), 'catatan' => $catatan, 'dibuat_oleh' => $penggunaId]);

            $isi = DB::table('opname_item')->insertUsing(
                ['opname_id', 'barang_id', 'stok_sistem'],
                DB::table('barang')
                    ->selectRaw('? as opname_id, id, stok', [$o->id])
                    ->where('toko_id', $tokoId)
                    ->where('aktif', true)
                    ->when($kategoriId, fn ($q) => $q->where('kategori_id', $kategoriId))
            );
            if ($isi === 0) {
                GalatApi::tolak(422, 'Tidak ada barang untuk dihitung.');
            }

            return $o->id;
        });
    }

    private function kunci(int $id): Opname
    {
        $o = Opname::whereKey($id)->lockForUpdate()->first();
        if (! $o) {
            GalatApi::tolak(404, 'Sesi opname tidak ditemukan.');
        }

        return $o;
    }

    /** Simpan hasil hitung (hanya draft). stok_fisik null/"" = belum dihitung. */
    public function simpanHitungan(int $id, array $item): void
    {
        DB::transaction(function () use ($id, $item) {
            $o = $this->kunci($id);
            if ($o->status !== 'draft') {
                GalatApi::tolak(409, 'Sesi ini sudah diajukan, hitungan tidak bisa diubah.');
            }
            foreach ($item as $it) {
                $it = is_array($it) ? $it : [];
                $barangId = (int) ($it['barang_id'] ?? 0);
                $v = $it['stok_fisik'] ?? null;
                $fisik = null;
                if ($v !== null && $v !== '') {
                    if (is_int($v)) {
                        $fisik = $v;
                    } elseif (is_float($v) && floor($v) === $v) {
                        $fisik = (int) $v;
                    } elseif (is_string($v) && preg_match('/^\s*\d+\s*$/', $v)) {
                        $fisik = (int) trim($v);
                    } else {
                        $fisik = -1; // tidak valid
                    }
                    if ($fisik < 0 || $fisik > 10_000_000) {
                        GalatApi::tolak(422, 'Stok fisik harus bilangan bulat 0 atau lebih.');
                    }
                }
                $alasan = self::teks($it['alasan'] ?? '', 'Alasan selisih', maks: 200);

                DB::table('opname_item')->where('opname_id', $o->id)->where('barang_id', $barangId)->update([
                    'stok_fisik' => $fisik,
                    'selisih' => $fisik === null ? null : DB::raw(((int) $fisik).' - stok_sistem'), // $fisik sudah int tervalidasi
                    'alasan' => $alasan,
                ]);
            }
        });
    }

    /** Hapus sesi yang masih draft. */
    public function batalkan(int $id): void
    {
        DB::transaction(function () use ($id) {
            $o = Opname::whereKey($id)->where('status', 'draft')->lockForUpdate()->first();
            if (! $o) {
                GalatApi::tolak(409, 'Hanya sesi berstatus draft yang bisa dibatalkan.');
            }
            DB::table('opname_item')->where('opname_id', $o->id)->delete();
            $o->delete();
        });
    }

    /** draft → diajukan. Semua barang wajib dihitung & selisih wajib beralasan. */
    public function ajukan(int $id): string
    {
        return DB::transaction(function () use ($id) {
            $o = $this->kunci($id);
            if ($o->status !== 'draft') {
                GalatApi::tolak(409, "Sesi {$o->kode} berstatus {$o->status}, tidak bisa diajukan.");
            }
            $belum = DB::table('opname_item')->where('opname_id', $o->id)->whereNull('stok_fisik')->count();
            if ($belum > 0) {
                GalatApi::tolak(422, "Masih ada {$belum} barang yang belum dihitung.");
            }
            $tanpaAlasan = DB::table('opname_item')->where('opname_id', $o->id)
                ->where('selisih', '<>', 0)->whereRaw("TRIM(COALESCE(alasan, '')) = ''")->count();
            if ($tanpaAlasan > 0) {
                GalatApi::tolak(422, "Isi alasan untuk {$tanpaAlasan} barang yang stoknya selisih.");
            }
            $o->forceFill(['status' => 'diajukan', 'diajukan_pada' => now()])->save();

            return "Sesi {$o->kode} diajukan ke pemilik untuk disetujui.";
        });
    }

    private function wajibDiajukan(Opname $o): void
    {
        if ($o->status !== 'diajukan') {
            GalatApi::tolak(409, "Sesi {$o->kode} berstatus {$o->status}. Hanya sesi yang diajukan yang bisa diputuskan.");
        }
    }

    /** diajukan → ditolak (wajib alasan). Stok tidak berubah. */
    public function tolakSesi(int $id, int $penggunaId, mixed $alasan): string
    {
        return DB::transaction(function () use ($id, $penggunaId, $alasan) {
            $o = $this->kunci($id);
            $this->wajibDiajukan($o);
            $alasan = self::teks($alasan, 'Alasan penolakan', wajib: true, maks: 200);
            $o->forceFill([
                'status' => 'ditolak', 'alasan_tolak' => $alasan,
                'diputuskan_oleh' => $penggunaId, 'diputuskan_pada' => now(),
            ])->save();

            return "Sesi {$o->kode} ditolak. Stok tidak berubah.";
        });
    }

    /**
     * diajukan → disetujui. Terapkan SELISIH (fisik − sistem saat dihitung) ke stok SEKARANG,
     * sehingga penjualan setelah penghitungan tidak ikut terhapus. Stok tidak boleh minus
     * (perubahan dibatasi −stok sekarang), sama dengan sumber.
     */
    public function setujui(int $id, int $tokoId, int $penggunaId): string
    {
        return DB::transaction(function () use ($id, $tokoId, $penggunaId) {
            \App\Models\Toko::kunci($tokoId); // antre dengan transaksi kasir yang juga mengubah stok
            $o = $this->kunci($id);
            $this->wajibDiajukan($o);

            $item = DB::table('opname_item')->where('opname_id', $o->id)->where('selisih', '<>', 0)
                ->orderBy('id')->get(['barang_id', 'selisih', 'alasan']);
            foreach ($item as $x) {
                $stok = (int) Barang::whereKey($x->barang_id)->lockForUpdate()->value('stok');
                $perubahan = max((int) $x->selisih, -$stok);
                $this->stok->ubah(
                    $tokoId, (int) $x->barang_id, $penggunaId, 'penyesuaian', $perubahan,
                    "Opname {$o->kode}".($x->alasan ? ': '.$x->alasan : ''), $o->kode,
                );
            }
            $o->forceFill(['status' => 'disetujui', 'diputuskan_oleh' => $penggunaId, 'diputuskan_pada' => now()])->save();

            return "Sesi {$o->kode} disetujui. {$item->count()} barang disesuaikan stoknya.";
        });
    }
}
