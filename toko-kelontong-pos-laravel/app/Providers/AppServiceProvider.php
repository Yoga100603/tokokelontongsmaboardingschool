<?php

namespace App\Providers;

use App\Support\Format;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Blade: {{ Format::rupiah($n) }}
        AliasLoader::getInstance()->alias('Format', Format::class);

        $kelebihan = fn (string $pesan) => fn () => back()->withInput()->withErrors(['umum' => $pesan]);

        RateLimiter::for('daftar', fn (Request $r) => Limit::perHour(10)->by('daftar:'.$r->ip())
            ->response($kelebihan('Terlalu banyak pendaftaran. Coba lagi nanti.')));

        RateLimiter::for('lupa-sandi', fn (Request $r) => Limit::perMinutes(15, 5)->by('lupa:'.$r->ip())
            ->response($kelebihan('Terlalu banyak permintaan. Coba lagi nanti.')));
        // Login dibatasi manual di MasukController (kunci ip+email, reset saat berhasil).
    }
}
