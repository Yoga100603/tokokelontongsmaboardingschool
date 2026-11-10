<?php

namespace App\Http\Controllers;

use App\Models\TutupBuku;
use Illuminate\View\View;

/** Halaman kasir (port app/(app)/kasir/page.tsx). */
class KasirController extends Controller
{
    public function index(): View
    {
        return view('kasir.index', [
            'hariIniDitutup' => TutupBuku::terkunci(now()->toDateString()),
        ]);
    }
}
