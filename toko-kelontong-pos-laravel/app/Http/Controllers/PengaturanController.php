<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Port app/(app)/pengaturan/page.tsx — halaman Akun & Pengaturan (semua peran). */
class PengaturanController extends Controller
{
    public function index(Request $request)
    {
        $u = $this->pengguna();

        return view('pengaturan.index', [
            'pengguna' => ['id' => $u->id, 'nama' => $u->nama, 'email' => $u->email, 'peran' => $u->peran],
            'baru' => $request->query('baru') === '1',
        ]);
    }
}
