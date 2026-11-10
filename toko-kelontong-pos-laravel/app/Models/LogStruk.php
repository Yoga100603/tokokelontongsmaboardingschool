<?php

namespace App\Models;

use App\Models\Concerns\MilikToko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogStruk extends Model
{
    use MilikToko;

    protected $table = 'log_struk';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = null;

    protected $guarded = ['id', 'toko_id'];

    protected function casts(): array
    {
        return ['dibuat_pada' => 'datetime'];
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }
}
