<?php

namespace App\Models;

use App\Models\Concerns\MilikToko;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TutupBuku extends Model
{
    use MilikToko;

    protected $table = 'tutup_buku';

    public $timestamps = false;

    protected $guarded = ['id', 'toko_id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'ditutup_pada' => 'datetime',
            'jumlah_transaksi' => 'integer',
            'total_penjualan' => 'integer',
            'total_modal' => 'integer',
            'laba_kotor' => 'integer',
            'uang_tunai' => 'integer',
        ];
    }

    /** Simpan `tanggal` SELALU sebagai "Y-m-d" (juga di SQLite). */
    protected function tanggal(): Attribute
    {
        return Attribute::set(fn ($v) => $v === null ? null : Carbon::parse($v)->toDateString());
    }

    public function penutup(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'ditutup_oleh');
    }

    /** Apakah tanggal (Y-m-d) sudah dikunci untuk toko yang sedang login. */
    public static function terkunci(string $tanggal): bool
    {
        return static::whereDate('tanggal', $tanggal)->exists();
    }
}
