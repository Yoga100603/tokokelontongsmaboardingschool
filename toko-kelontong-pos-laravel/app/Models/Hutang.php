<?php

namespace App\Models;

use App\Models\Concerns\MilikToko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hutang extends Model
{
    use MilikToko;

    protected $table = 'hutang';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = null;

    protected $fillable = ['transaksi_id', 'nama_pelanggan', 'kontak', 'jumlah', 'catatan'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'dibuat_pada' => 'datetime',
            'lunas_pada' => 'datetime',
        ];
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }
}
