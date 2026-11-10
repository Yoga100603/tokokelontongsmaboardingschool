<?php

namespace App\Http\Controllers;

use App\Http\Requests\BarangValidasi as V;
use App\Models\Barang;
use App\Services\BerkasService;
use App\Services\MutasiStokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Port app/(app)/barang/page.tsx + app/api/barang/route.ts + [id]/route.ts + [id]/foto/route.ts.
 * GET /api/barang juga dipakai layar Kasir — URL, query param & bentuk respons WAJIB tetap.
 */
class BarangController extends Controller
{
    /** Kolom setara KOLOM_BARANG (lib/barang.ts). */
    private const KOLOM = [
        'b.id', 'b.kode_barang', 'b.nama_barang', 'b.satuan', 'b.harga_modal', 'b.harga_jual', 'b.stok', 'b.stok_minimum',
        'b.foto_url', 'b.tampil_di_katalog', 'b.aktif', 'b.kategori_id', 'k.nama as kategori', 'b.diubah_pada',
    ];

    /** Halaman Barang & Stok (semua peran; fitur kelola khusus pemilik). */
    public function halaman()
    {
        return view('barang.index', ['pemilik' => $this->pengguna()->isPemilik()]);
    }

    /** Query builder barang + kategori, sudah difilter toko (DB::table tidak kena global scope). */
    private function queryBarang()
    {
        return DB::table('barang as b')
            ->leftJoin('kategori_barang as k', 'k.id', '=', 'b.kategori_id')
            ->where('b.toko_id', $this->tokoId())
            ->select(self::KOLOM);
    }

    /** Normalisasi tipe (MySQL bisa mengembalikan string) + untukPeran(): kasir tanpa harga_modal. */
    private function bentuk(object $r): array
    {
        $x = [
            'id' => (int) $r->id,
            'kode_barang' => $r->kode_barang,
            'nama_barang' => $r->nama_barang,
            'satuan' => $r->satuan,
            'harga_modal' => (int) $r->harga_modal,
            'harga_jual' => (int) $r->harga_jual,
            'stok' => (int) $r->stok,
            'stok_minimum' => (int) $r->stok_minimum,
            'foto_url' => (string) $r->foto_url,
            'tampil_di_katalog' => (int) $r->tampil_di_katalog,
            'aktif' => (int) $r->aktif,
            'kategori_id' => $r->kategori_id === null ? null : (int) $r->kategori_id,
            'kategori' => $r->kategori,
            'diubah_pada' => $r->diubah_pada ? substr((string) $r->diubah_pada, 0, 19) : null,
        ];
        if (! $this->pengguna()->isPemilik()) {
            unset($x['harga_modal']);
        }

        return $x;
    }

    /** GET /api/barang?q=&kategori_id=&filter=menipis|habis&semua=1 — default hanya barang aktif. */
    public function index(Request $request)
    {
        $q = $this->queryBarang();
        if ($request->query('semua') !== '1') {
            $q->where('b.aktif', 1);
        }
        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        if ($cari !== '') {
            $pola = '%'.preg_replace('/[!%_]/', '!$0', $cari).'%';
            $q->where(fn ($w) => $w->whereRaw("b.nama_barang LIKE ? ESCAPE '!'", [$pola])
                ->orWhereRaw("b.kode_barang LIKE ? ESCAPE '!'", [$pola]));
        }
        $kat = $request->query('kategori_id');
        if (is_string($kat) && preg_match('/^\d+$/', $kat) && (int) $kat > 0) {
            $q->where('b.kategori_id', (int) $kat);
        }
        $filter = $request->query('filter');
        if ($filter === 'menipis') {
            $q->whereColumn('b.stok', '<=', 'b.stok_minimum')->where('b.stok', '>', 0);
        }
        if ($filter === 'habis') {
            $q->where('b.stok', 0);
        }
        $rows = $q->orderBy('b.nama_barang')->limit(1000)->get()->map(fn ($r) => $this->bentuk($r))->values();

        $r = DB::table('barang')->where('toko_id', $this->tokoId())->where('aktif', 1)->selectRaw(
            'COUNT(*) AS total,
             SUM(CASE WHEN stok = 0 THEN 1 ELSE 0 END) AS habis,
             SUM(CASE WHEN stok > 0 AND stok <= stok_minimum THEN 1 ELSE 0 END) AS menipis,
             SUM(stok * harga_modal) AS nilai_modal'
        )->first();
        $ringkasan = [
            'total' => (int) $r->total,
            'habis' => (int) $r->habis,
            'menipis' => (int) $r->menipis,
            'nilai_modal' => (int) $r->nilai_modal,
        ];
        if (! $this->pengguna()->isPemilik()) {
            unset($ringkasan['nilai_modal']);
        }

        return $this->ok(['barang' => $rows, 'ringkasan' => $ringkasan]);
    }

    /** POST /api/barang — tambah barang baru (pemilik) beserta stok awal (mutasi 'awal'). */
    public function store(Request $request, MutasiStokService $stok)
    {
        $b = V::body($request);
        $stokAwal = V::angka($b['stok'] ?? null, 'Stok awal', wajib: false);
        $u = $this->pengguna();

        $id = DB::transaction(function () use ($b, $stokAwal, $u, $stok) {
            $data = V::barang($this->tokoId(), $b);
            $barang = Barang::create($data); // toko_id otomatis dari sesi (MilikToko), stok default 0
            if ($stokAwal > 0) {
                $stok->ubah(
                    $this->tokoId(), $barang->id, $u->id, 'awal', $stokAwal,
                    V::teks($b['keterangan'] ?? null, 'Keterangan', maks: 200) ?: 'Stok awal barang baru',
                );
            }

            return $barang->id;
        });

        return $this->ok(['id' => $id], 'Barang baru ditambahkan.', 201);
    }

    /** GET /api/barang/{id} */
    public function show(int $id)
    {
        $r = $this->queryBarang()->where('b.id', $id)->first();
        if (! $r) {
            $this->tolak(404, 'Barang tidak ditemukan.');
        }

        return $this->ok($this->bentuk($r));
    }

    /** PUT /api/barang/{id} — ubah data barang (pemilik). Stok TIDAK diubah di sini. */
    public function update(Request $request, int $id)
    {
        $body = V::body($request);
        DB::transaction(function () use ($body, $id) {
            $b = Barang::whereKey($id)->lockForUpdate()->first();
            if (! $b) {
                $this->tolak(404, 'Barang tidak ditemukan.');
            }
            $b->fill(V::barang($this->tokoId(), $body, $id))->save();
        });

        return $this->ok(null, 'Data barang diperbarui.');
    }

    /**
     * DELETE /api/barang/{id} (pemilik). Barang yang sudah pernah terjual/diopname hanya
     * dinonaktifkan agar riwayat tetap utuh; kodenya dibebaskan untuk dipakai ulang.
     */
    public function destroy(int $id)
    {
        $pesan = DB::transaction(function () use ($id) {
            $b = Barang::whereKey($id)->where('aktif', true)->lockForUpdate()->first();
            if (! $b) {
                $this->tolak(404, 'Barang tidak ditemukan.');
            }
            // item_transaksi / opname_item tidak punya toko_id — barang sudah terverifikasi milik toko.
            $terpakai = DB::table('item_transaksi')->where('barang_id', $id)->exists()
                || DB::table('opname_item')->where('barang_id', $id)->exists();
            if ($terpakai) {
                $b->forceFill([
                    'aktif' => false,
                    'tampil_di_katalog' => false,
                    'kode_barang' => mb_substr($b->kode_barang.'~'.$b->id, 0, 64),
                ])->save();

                return 'Barang dinonaktifkan (riwayat penjualannya tetap tersimpan).';
            }
            DB::table('mutasi_stok')->where('toko_id', $this->tokoId())->where('barang_id', $id)->delete();
            $b->delete();

            return 'Barang dihapus.';
        });

        return $this->ok(null, $pesan);
    }

    /** POST /api/barang/{id}/foto — unggah / ganti foto barang (pemilik), field form "berkas". */
    public function foto(Request $request, int $id, BerkasService $berkas)
    {
        $b = Barang::find($id);
        if (! $b) {
            $this->tolak(404, 'Barang tidak ditemukan.');
        }
        $lama = $b->foto_url;
        $f = $request->file('berkas');
        $url = $berkas->simpan(is_array($f) ? null : $f, 'barang-'.$this->tokoId());
        $b->foto_url = $url;
        $b->save();
        $berkas->hapusLama($lama);

        return $this->ok(['foto_url' => $url], 'Foto barang diperbarui.');
    }
}
