<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Toko extends Model
{
    protected $table = 'toko';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = 'diubah_pada';

    protected $fillable = [
        'nama', 'slug', 'alamat', 'telepon', 'email', 'deskripsi',
        'logo_url', 'jam_buka', 'catatan_struk', 'katalog_aktif',
    ];

    protected function casts(): array
    {
        return ['katalog_aktif' => 'boolean'];
    }

    /**
     * Kunci baris toko (SELECT ... FOR UPDATE) — panggil sebagai statement PERTAMA di dalam
     * DB::transaction untuk penulisan yang harus antre per toko (transaksi kasir, tutup buku,
     * mulai/setujui opname). Setara kunci tulis global `tulis()` versi Next.js, tapi per toko:
     * mencegah nomor transaksi kembar, transaksi lolos ke tanggal yang sedang ditutup, dan deadlock.
     */
    public static function kunci(int $tokoId): void
    {
        static::query()->whereKey($tokoId)->lockForUpdate()->value('id');
    }

    public function pengguna(): HasMany
    {
        return $this->hasMany(Pengguna::class);
    }

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class);
    }

    public function kategori(): HasMany
    {
        return $this->hasMany(KategoriBarang::class);
    }
}
