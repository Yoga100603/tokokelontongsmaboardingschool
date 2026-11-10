<?php

namespace App\Models;

use App\Models\Concerns\MilikToko;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Dibuat hanya lewat TransaksiService (harga & total dihitung ulang di server). */
class Transaksi extends Model
{
    use MilikToko;

    protected $table = 'transaksi';

    public $timestamps = false;

    protected $guarded = ['id', 'toko_id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'waktu' => 'datetime',
            'total' => 'integer',
            'total_modal' => 'integer',
            'uang_bayar' => 'integer',
            'kembalian' => 'integer',
        ];
    }

    /** Simpan `tanggal` SELALU sebagai "Y-m-d" (juga di SQLite) agar whereBetween('tanggal', [dari, sampai]) inklusif & pakai index. */
    protected function tanggal(): Attribute
    {
        return Attribute::set(fn ($v) => $v === null ? null : Carbon::parse($v)->toDateString());
    }

    public function getRouteKeyName(): string
    {
        return 'nomor_transaksi';
    }

    public function item(): HasMany
    {
        return $this->hasMany(ItemTransaksi::class);
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    public function logStruk(): HasMany
    {
        return $this->hasMany(LogStruk::class);
    }

    public function scopeSelesai(Builder $q): Builder
    {
        return $q->where('status', 'selesai');
    }
}
