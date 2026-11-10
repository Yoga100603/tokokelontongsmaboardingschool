<?php

namespace App\Http\Controllers;

use App\Services\BerkasService;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Port app/toko/[slug]/page.tsx — katalog publik toko (tanpa login, hanya baca).
 * Pakai DB::table (bukan model ber-scope) dengan filter toko_id eksplisit, karena
 * pengunjung yang sedang login di toko lain pun harus melihat katalog toko INI.
 * Harga modal & jumlah stok persis TIDAK pernah diambil.
 */
class KatalogController extends Controller
{
    public function tampil(Request $request, string $slug)
    {
        $toko = DB::table('toko')
            ->where('slug', $slug)->where('katalog_aktif', true)
            ->first(['id', 'nama', 'slug', 'alamat', 'telepon', 'deskripsi', 'logo_url', 'jam_buka']);
        abort_unless($toko, 404);

        $q = mb_substr(trim((string) (is_string($request->query('q')) ? $request->query('q') : '')), 0, 60);
        $kat = is_scalar($request->query('kategori')) ? max(0, (int) $request->query('kategori')) : 0;

        $barang = DB::table('barang as b')
            ->leftJoin('kategori_barang as k', 'k.id', '=', 'b.kategori_id')
            ->where('b.toko_id', $toko->id)->where('b.aktif', true)->where('b.tampil_di_katalog', true)
            ->when($q !== '', fn ($w) => $w->where('b.nama_barang', 'like', '%'.$q.'%'))
            ->when($kat > 0, fn ($w) => $w->where('b.kategori_id', $kat))
            ->select(['b.id', 'b.nama_barang', 'b.satuan', 'b.harga_jual', 'b.foto_url', 'k.nama as kategori'])
            ->selectRaw('CASE WHEN b.stok > 0 THEN 1 ELSE 0 END AS tersedia')
            ->orderByRaw('CASE WHEN b.stok = 0 THEN 1 ELSE 0 END')->orderBy('b.nama_barang')
            ->limit(500)->get();

        $kategori = DB::table('kategori_barang as k')
            ->join('barang as b', 'b.kategori_id', '=', 'k.id')
            ->where('k.toko_id', $toko->id)->where('b.toko_id', $toko->id)
            ->where('b.aktif', true)->where('b.tampil_di_katalog', true)
            ->distinct()->orderBy('k.nama')->get(['k.id', 'k.nama']);

        $wa = $toko->telepon ? Format::nomorWa($toko->telepon) : '';
        $amanGambar = fn (?string $url) => preg_match(BerkasService::POLA_URL, (string) $url) ? $url : '';

        return view('publik.katalog', [
            'toko' => $toko,
            'logo' => $amanGambar($toko->logo_url),
            'barang' => $barang->map(function ($b) use ($amanGambar) {
                $b->foto_url = $amanGambar($b->foto_url);

                return $b;
            }),
            'kategori' => $kategori,
            'q' => $q,
            'kat' => $kat,
            'wa' => $wa,
        ]);
    }
}
