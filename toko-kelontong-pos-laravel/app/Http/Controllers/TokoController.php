<?php

namespace App\Http\Controllers;

use App\Models\Toko;
use App\Services\BerkasService;
use App\Support\GalatApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Port app/api/toko/route.ts + app/api/toko/logo/route.ts — profil toko milik pengguna yang login. */
class TokoController extends Controller
{
    public const KOLOM = ['id', 'nama', 'slug', 'alamat', 'telepon', 'email', 'deskripsi', 'logo_url', 'jam_buka', 'catatan_struk', 'katalog_aktif'];

    /** GET /api/toko */
    public function tampil()
    {
        return $this->ok($this->data($this->tokoId()));
    }

    /** PUT /api/toko — ubah profil toko (pemilik). */
    public function ubah(Request $request)
    {
        $b = $request->all();
        $nama = self::teks($b['nama'] ?? null, 'Nama toko', wajib: true, min: 3, maks: 80);
        $slug = self::buatSlug(self::teks($b['slug'] ?? null, 'Alamat katalog', wajib: true, maks: 50));
        $telepon = preg_replace('/[^\d+]/', '', self::teks($b['telepon'] ?? null, 'Nomor telepon', maks: 20));
        $email = ! empty($b['email']) ? self::emailValid($b['email']) : '';
        $alamat = self::teks($b['alamat'] ?? null, 'Alamat', maks: 200);
        $deskripsi = self::teks($b['deskripsi'] ?? null, 'Deskripsi', maks: 500);
        $jamBuka = self::teks($b['jam_buka'] ?? null, 'Jam buka', maks: 120);
        $catatan = self::teks($b['catatan_struk'] ?? null, 'Catatan struk', maks: 200);
        $tokoId = $this->tokoId();

        DB::transaction(function () use ($tokoId, $nama, $slug, $telepon, $email, $alamat, $deskripsi, $jamBuka, $catatan, $b) {
            if (Toko::where('slug', $slug)->where('id', '<>', $tokoId)->lockForUpdate()->exists()) {
                $this->tolak(409, 'Alamat katalog sudah dipakai toko lain. Pilih yang lain.');
            }
            $t = Toko::lockForUpdate()->findOrFail($tokoId);
            $t->fill([
                'nama' => $nama, 'slug' => $slug, 'alamat' => $alamat, 'telepon' => $telepon, 'email' => $email,
                'deskripsi' => $deskripsi, 'jam_buka' => $jamBuka, 'catatan_struk' => $catatan,
                'katalog_aktif' => ! empty($b['katalog_aktif']),
            ]);
            $t->diubah_pada = now();
            $t->save();
        });

        return $this->ok($this->data($tokoId), 'Profil toko disimpan.');
    }

    /** POST /api/toko/logo — unggah logo (pemilik), field form "berkas". */
    public function logo(Request $request, BerkasService $berkas)
    {
        $tokoId = $this->tokoId();
        $url = $berkas->simpan($request->file('berkas'), 'logo-'.$tokoId);

        $lama = DB::transaction(function () use ($tokoId, $url) {
            $t = Toko::lockForUpdate()->findOrFail($tokoId);
            $lama = $t->logo_url;
            $t->logo_url = $url;
            $t->diubah_pada = now();
            $t->save();

            return $lama;
        });
        if ($lama) {
            $berkas->hapusLama($lama);
        }

        return $this->ok(['logo_url' => $url], 'Logo toko diperbarui.');
    }

    /** Bentuk data toko sama dengan SELECT KOLOM di sumber (katalog_aktif berupa 0/1). */
    private function data(int $tokoId): ?array
    {
        $t = Toko::find($tokoId, self::KOLOM);
        if (! $t) {
            return null;
        }
        $d = $t->only(self::KOLOM);
        $d['deskripsi'] = (string) ($d['deskripsi'] ?? '');
        $d['katalog_aktif'] = $t->katalog_aktif ? 1 : 0;

        return $d;
    }

    // ── Validasi setara lib/api.ts ───────────────────────────────────

    /** Setara teks() di lib/api.ts: trim, wajib, min, maks (pesan galat sama). */
    public static function teks(mixed $v, string $label, bool $wajib = false, int $maks = 255, int $min = 0): string
    {
        $s = is_scalar($v) ? trim((string) (is_bool($v) ? ($v ? 'true' : 'false') : $v)) : '';
        if ($wajib && $s === '') {
            GalatApi::tolak(422, "$label wajib diisi.");
        }
        if ($s !== '' && mb_strlen($s) < $min) {
            GalatApi::tolak(422, "$label minimal $min karakter.");
        }
        if (mb_strlen($s) > $maks) {
            GalatApi::tolak(422, "$label maksimal $maks karakter.");
        }

        return $s;
    }

    /** Setara emailValid() di lib/api.ts. */
    public static function emailValid(mixed $v): string
    {
        $s = mb_strtolower(self::teks($v, 'Email', wajib: true, maks: 120));
        if (! preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/u', $s)) {
            GalatApi::tolak(422, 'Format email tidak valid.');
        }

        return $s;
    }

    /** Setara buatSlug() di lib/utils.ts. */
    public static function buatSlug(string $s): string
    {
        $s = strtolower(Str::ascii($s));
        $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');

        return substr($s, 0, 50) ?: 'toko';
    }
}
