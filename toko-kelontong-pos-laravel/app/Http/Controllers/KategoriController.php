<?php

namespace App\Http\Controllers;

use App\Http\Requests\BarangValidasi as V;
use App\Models\Barang;
use App\Models\KategoriBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Port app/api/kategori/route.ts + [id]/route.ts. */
class KategoriController extends Controller
{
    /** GET /api/kategori — daftar kategori + jumlah barang aktif. */
    public function index()
    {
        $rows = KategoriBarang::query()
            ->withCount(['barang as jumlah_barang' => fn ($q) => $q->where('aktif', true)])
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn ($k) => ['id' => (int) $k->id, 'nama' => $k->nama, 'jumlah_barang' => (int) $k->jumlah_barang])
            ->values();

        return $this->ok($rows);
    }

    /** POST /api/kategori (pemilik). Nama kembar dalam satu toko → 409 (unique index). */
    public function store(Request $request)
    {
        $b = V::body($request);
        $nama = V::teks($b['nama'] ?? null, 'Nama kategori', wajib: true, maks: 40);
        $k = KategoriBarang::create(['nama' => $nama]); // toko_id dari sesi

        return $this->ok(['id' => $k->id, 'nama' => $nama], 'Kategori ditambahkan.', 201);
    }

    /** DELETE /api/kategori/{id} (pemilik). Barang di dalamnya menjadi tanpa kategori. */
    public function destroy(int $id)
    {
        DB::transaction(function () use ($id) {
            $k = KategoriBarang::whereKey($id)->first();
            if (! $k) {
                $this->tolak(404, 'Kategori tidak ditemukan.');
            }
            Barang::where('kategori_id', $k->id)->update(['kategori_id' => null]);
            $k->delete();
        });

        return $this->ok(null, 'Kategori dihapus.');
    }
}
