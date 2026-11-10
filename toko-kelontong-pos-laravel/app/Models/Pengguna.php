<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Akun login (pemilik / kasir). Pengganti model User bawaan.
 * Kolom sandi = `kata_sandi`. `versi_sesi` dinaikkan saat ganti sandi /
 * dinonaktifkan → sesi lama ditolak oleh middleware CekSesiPengguna.
 */
class Pengguna extends Authenticatable
{
    protected $table = 'pengguna';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = null;

    protected $authPasswordName = 'kata_sandi';

    protected $fillable = ['nama', 'email', 'kata_sandi', 'peran', 'aktif'];

    protected $hidden = ['kata_sandi', 'remember_token', 'token_reset'];

    protected function casts(): array
    {
        return [
            'kata_sandi' => 'hashed',
            'aktif' => 'boolean',
            'versi_sesi' => 'integer',
            'terakhir_masuk' => 'datetime',
            'token_reset_kedaluwarsa' => 'datetime',
        ];
    }

    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    public function isPemilik(): bool
    {
        return $this->peran === 'pemilik';
    }

    /** Batalkan semua sesi yang sedang berjalan untuk akun ini. */
    public function cabutSesi(): void
    {
        $this->increment('versi_sesi');
    }
}
