<?php

namespace App\Models;

use App\Models\Concerns\MilikToko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opname extends Model
{
    use MilikToko;

    protected $table = 'opname';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = null;

    protected $guarded = ['id', 'toko_id'];

    protected function casts(): array
    {
        return [
            'dibuat_pada' => 'datetime',
            'diajukan_pada' => 'datetime',
            'diputuskan_pada' => 'datetime',
        ];
    }

    public function item(): HasMany
    {
        return $this->hasMany(OpnameItem::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diputuskan_oleh');
    }
}
