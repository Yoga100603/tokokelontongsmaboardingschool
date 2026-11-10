<?php

namespace App\Models;

use App\Models\Concerns\MilikToko;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    use MilikToko;

    protected $table = 'barang';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = 'diubah_pada';

    /** `stok` sengaja tidak fillable: hanya berubah lewat MutasiStokService. */
    protected $fillable = [
        'kategori_id', 'kode_barang', 'nama_barang', 'satuan', 'harga_modal',
        'harga_jual', 'stok_minimum', 'foto_url', 'tampil_di_katalog', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'harga_modal' => 'integer',
            'harga_jual' => 'integer',
            'stok' => 'integer',
            'stok_minimum' => 'integer',
            'tampil_di_katalog' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class, 'kategori_id');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiStok::class);
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    public function scopeStokMenipis(Builder $q): Builder
    {
        return $q->whereColumn('stok', '<=', 'stok_minimum');
    }

    public function getMenipisAttribute(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }
}
