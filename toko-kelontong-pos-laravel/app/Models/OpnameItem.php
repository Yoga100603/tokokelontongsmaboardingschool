<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tidak punya toko_id: selalu diakses lewat Opname (yang sudah terisolasi). */
class OpnameItem extends Model
{
    protected $table = 'opname_item';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stok_sistem' => 'integer',
            'stok_fisik' => 'integer',
            'selisih' => 'integer',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(Opname::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }
}
