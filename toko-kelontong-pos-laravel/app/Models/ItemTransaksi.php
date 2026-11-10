<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tidak punya toko_id: selalu diakses lewat Transaksi (yang sudah terisolasi). */
class ItemTransaksi extends Model
{
    protected $table = 'item_transaksi';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'harga_jual' => 'integer',
            'harga_modal' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }
}
