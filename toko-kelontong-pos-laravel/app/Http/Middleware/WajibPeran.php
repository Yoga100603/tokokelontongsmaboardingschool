<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pemakaian di route: ->middleware('peran:pemilik') */
class WajibPeran
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        if (! in_array($request->user()?->peran, $peran, true)) {
            return $request->expectsJson()
                ? response()->json(['sukses' => false, 'pesan' => 'Fitur ini hanya untuk pemilik toko.'], 403)
                : redirect()->route('kasir', ['akses' => 'ditolak']);
        }

        return $next($request);
    }
}
