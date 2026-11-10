<?php

use App\Http\Middleware\CekSesiPengguna;
use App\Http\Middleware\WajibPeran;
use App\Support\GalatApi;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'peran' => WajibPeran::class,
            'cek.sesi' => CekSesiPengguna::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('masuk'));
        $middleware->redirectUsersTo(fn () => auth()->user()?->isPemilik() ? route('dasbor') : route('kasir'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Format galat JSON untuk /api/* sama dengan versi Next.js: {sukses:false, pesan, detail}
        $json = fn (Request $r) => $r->is('api/*') || $r->expectsJson();
        $balas = fn (int $status, string $pesan, $detail = null) => response()->json(
            array_filter(['sukses' => false, 'pesan' => $pesan, 'detail' => $detail], fn ($v) => $v !== null), $status
        );

        $exceptions->render(function (GalatApi $e, Request $r) use ($json, $balas) {
            return $json($r)
                ? $balas($e->status, $e->getMessage(), $e->detail)
                : back()->withInput()->withErrors(['umum' => $e->getMessage()]);
        });
        $exceptions->render(function (ValidationException $e, Request $r) use ($json, $balas) {
            return $json($r) ? $balas(422, $e->validator->errors()->first(), $e->errors()) : null;
        });
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $r) use ($json, $balas) {
            return $json($r) ? $balas(409, 'Data yang sama sudah ada.') : null;
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $r) use ($json, $balas) {
            return $json($r) ? $balas(404, 'Data tidak ditemukan.') : null;
        });
        $exceptions->render(function (AuthenticationException $e, Request $r) use ($json, $balas) {
            return $json($r) ? $balas(401, 'Sesi berakhir. Silakan masuk kembali.') : null;
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $r) use ($json, $balas) {
            return $json($r) ? $balas(429, 'Terlalu banyak permintaan. Coba lagi nanti.') : null;
        });
        $exceptions->dontReport([GalatApi::class]);
    })->create();
