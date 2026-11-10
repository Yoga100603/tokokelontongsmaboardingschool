<?php

namespace App\Models;

use App\Models\Concerns\MilikToko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiStok extends Model
{
    use MilikToko;

    protected $table = 'mutasi_stok';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = null;

    protected $guarded = ['id', 'toko_id'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'stok_sebelum' => 'integer',
            'stok_sesudah' => 'integer',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
