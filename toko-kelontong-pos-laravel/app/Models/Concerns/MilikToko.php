<?php

namespace App\Models\Concerns;

use App\Models\Toko;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Isolasi data antar toko.
 *  - Saat ada pengguna login: semua query otomatis difilter `toko_id` milik pengguna.
 *  - Saat membuat record: `toko_id` diisi dari sesi (input request tidak dipercaya).
 * Konsol/seeder (tanpa login) tidak terkena filter. Pakai `withoutGlobalScope('toko')`
 * hanya untuk halaman publik (katalog) yang memfilter toko sendiri secara eksplisit.
 */
trait MilikToko
{
    public static function bootMilikToko(): void
    {
        static::addGlobalScope('toko', function (Builder $q) {
            if ($id = self::tokoIdAktif()) {
                $q->where($q->getModel()->getTable().'.toko_id', $id);
            }
        });

        static::creating(function ($model) {
            if ($id = self::tokoIdAktif()) {
                $model->toko_id = $id;
            }
        });
    }

    protected static function tokoIdAktif(): ?int
    {
        // guard()->hasUser() agar tidak memicu query user berulang / rekursi
        return Auth::guard()->hasUser() ? (int) Auth::user()->toko_id : null;
    }

    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }
}
