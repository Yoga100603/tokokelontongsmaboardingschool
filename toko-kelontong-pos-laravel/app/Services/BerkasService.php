<?php

namespace App\Services;

use App\Support\GalatApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Upload gambar (foto barang / logo toko) — setara lib/berkas.ts.
 * Disimpan di disk `local` (storage/app/private/uploads), disajikan lewat route
 * GET /api/berkas/{nama}. Jenis file dicek dari magic bytes, bukan dari nama/mime klien.
 */
class BerkasService
{
    public const FOLDER = 'uploads';

    public const MAKS_BYTE = 2 * 1024 * 1024;

    public const MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    public const POLA_URL = '/^\/api\/berkas\/([a-z0-9-]+\.(jpg|png|webp|gif))$/';

    public static function deteksi(string $b): ?string
    {
        return match (true) {
            str_starts_with($b, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($b, "\x89PNG\r\n\x1A\n") => 'png',
            substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WEBP' => 'webp',
            str_starts_with($b, 'GIF8') => 'gif',
            default => null,
        };
    }

    /** Simpan gambar dari field form "berkas". @return string URL publik "/api/berkas/<nama>" */
    public function simpan(?UploadedFile $f, string $awalan): string
    {
        if (! $f || ! $f->isValid()) {
            GalatApi::tolak(422, 'Pilih file gambar terlebih dahulu.');
        }
        if ($f->getSize() > self::MAKS_BYTE) {
            GalatApi::tolak(422, 'Ukuran gambar maksimal 2 MB.');
        }
        $isi = file_get_contents($f->getRealPath());
        $ext = self::deteksi($isi);
        if (! $ext) {
            GalatApi::tolak(422, 'Format gambar harus JPG, PNG, WEBP, atau GIF.');
        }
        $awalan = preg_replace('/[^a-z0-9-]/', '', strtolower($awalan)) ?: 'berkas';
        $nama = $awalan.'-'.bin2hex(random_bytes(8)).'.'.$ext;
        Storage::disk('local')->put(self::FOLDER.'/'.$nama, $isi);

        return '/api/berkas/'.$nama;
    }

    public function hapusLama(?string $url): void
    {
        if (preg_match(self::POLA_URL, (string) $url, $m)) {
            Storage::disk('local')->delete(self::FOLDER.'/'.$m[1]);
        }
    }

    /** Path absolut file bila nama valid & ada; null bila tidak. */
    public function path(string $nama): ?string
    {
        if (! preg_match('/^[a-z0-9-]+\.(jpg|png|webp|gif)$/', $nama)) {
            return null;
        }
        $rel = self::FOLDER.'/'.$nama;

        return Storage::disk('local')->exists($rel) ? Storage::disk('local')->path($rel) : null;
    }
}
