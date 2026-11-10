<?php

namespace App\Support;

use RuntimeException;

/**
 * Galat yang aman ditampilkan ke pengguna, beserta kode HTTP-nya
 * (setara GalatApi / tolak() di versi Next.js). Dirender jadi JSON
 * {sukses:false, pesan, detail} oleh handler di bootstrap/app.php.
 */
class GalatApi extends RuntimeException
{
    public function __construct(public int $status, string $pesan, public mixed $detail = null)
    {
        parent::__construct($pesan);
    }

    /** tolak(422, 'Pesan') — langsung lempar. */
    public static function tolak(int $status, string $pesan, mixed $detail = null): never
    {
        throw new self($status, $pesan, $detail);
    }
}
