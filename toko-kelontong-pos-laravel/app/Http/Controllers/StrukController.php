<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Halaman riwayat & detail struk (port app/(app)/struk/**). Data dimuat via /api/transaksi seperti sumber. */
class StrukController extends Controller
{
    public function index(): View
    {
        return view('struk.index');
    }

    public function show(string $nomor): View
    {
        return view('struk.tampil', ['nomor' => $nomor]);
    }
}
