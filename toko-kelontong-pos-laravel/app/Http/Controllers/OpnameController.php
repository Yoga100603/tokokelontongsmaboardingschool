<?php

namespace App\Http\Controllers;

use App\Models\KategoriBarang;
use App\Services\OpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Stok opname (setara app/(app)/opname/** + app/api/opname/**).
 * Alur: draft → diajukan (semua dihitung, selisih wajib beralasan) → disetujui/ditolak pemilik.
 * Stok hanya berubah saat disetujui, lewat mutasi 'penyesuaian'.
 */
class OpnameController extends Controller
{
    public const STATUS = ['draft', 'diajukan', 'disetujui', 'ditolak'];

    /** Label + warna lencana per status (setara STATUS di opname/klien.tsx). */
    public const LABEL = [
        'draft' => ['Draft', 'abu'],
        'diajukan' => ['Menunggu persetujuan', 'kuning'],
        'disetujui' => ['Disetujui', 'hijau'],
        'ditolak' => ['Ditolak', 'merah'],
    ];

    public function __construct(private OpnameService $layanan) {}

    // ── Halaman ──────────────────────────────────────────────────────

    public function halaman(): View
    {
        $kategori = KategoriBarang::orderBy('nama')->get(['id', 'nama']);

        return view('opname.index', ['pemilik' => $this->pengguna()->isPemilik(), 'kategori' => $kategori]);
    }

    public function halamanTampil(int $id): View
    {
        return view('opname.tampil', ['id' => $id, 'pemilik' => $this->pengguna()->isPemilik()]);
    }

    // ── API ──────────────────────────────────────────────────────────

    /** GET /api/opname — daftar sesi + jumlah per status. ?status= untuk menyaring. */
    public function index(Request $request): JsonResponse
    {
        $tokoId = $this->tokoId();
        $status = $request->query('status');
        $filter = in_array($status, self::STATUS, true) ? $status : null;

        $rows = DB::table('opname as o')
            ->leftJoin('pengguna as p1', 'p1.id', '=', 'o.dibuat_oleh')
            ->leftJoin('pengguna as p2', 'p2.id', '=', 'o.diputuskan_oleh')
            ->where('o.toko_id', $tokoId)
            ->when($filter, fn ($q) => $q->where('o.status', $filter))
            ->select([
                'o.id', 'o.kode', 'o.status', 'o.catatan', 'o.alasan_tolak', 'o.dibuat_pada', 'o.diajukan_pada', 'o.diputuskan_pada',
                'p1.nama as dibuat_oleh', 'p2.nama as diputuskan_oleh',
            ])
            ->selectSub('SELECT COUNT(*) FROM opname_item i WHERE i.opname_id = o.id', 'jumlah_barang')
            ->selectSub('SELECT COUNT(*) FROM opname_item i WHERE i.opname_id = o.id AND i.stok_fisik IS NOT NULL', 'sudah_dihitung')
            ->selectSub('SELECT COUNT(*) FROM opname_item i WHERE i.opname_id = o.id AND i.selisih <> 0', 'barang_selisih')
            ->orderByDesc('o.id')
            ->limit(100)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'kode' => $r->kode,
                'status' => $r->status,
                'catatan' => (string) $r->catatan,
                'alasan_tolak' => (string) $r->alasan_tolak,
                'dibuat_pada' => $r->dibuat_pada,
                'diajukan_pada' => $r->diajukan_pada,
                'diputuskan_pada' => $r->diputuskan_pada,
                'dibuat_oleh' => $r->dibuat_oleh,
                'diputuskan_oleh' => $r->diputuskan_oleh,
                'jumlah_barang' => (int) $r->jumlah_barang,
                'sudah_dihitung' => (int) $r->sudah_dihitung,
                'barang_selisih' => (int) $r->barang_selisih,
            ]);

        $ringkasan = DB::table('opname')->where('toko_id', $tokoId)
            ->groupBy('status')->select('status', DB::raw('COUNT(*) as jumlah'))
            ->pluck('jumlah', 'status')->map(fn ($n) => (int) $n);

        return $this->ok(['opname' => $rows, 'ringkasan' => (object) $ringkasan->all()]);
    }

    /** POST /api/opname — mulai sesi draft; stok sistem dipotret sekarang. */
    public function store(Request $request): JsonResponse
    {
        $catatan = OpnameService::teks($request->input('catatan'), 'Catatan', maks: 200);
        $kategori = (int) $request->input('kategori_id') ?: null;

        $id = $this->layanan->mulai($this->tokoId(), $this->pengguna()->id, $catatan, $kategori);

        return $this->ok(['id' => $id], 'Sesi opname dimulai. Silakan hitung stok fisik di rak.', 201);
    }

    /** GET /api/opname/{id} — detail sesi + lembar hitung. */
    public function show(int $id): JsonResponse
    {
        $o = DB::table('opname as o')
            ->leftJoin('pengguna as p1', 'p1.id', '=', 'o.dibuat_oleh')
            ->leftJoin('pengguna as p2', 'p2.id', '=', 'o.diputuskan_oleh')
            ->where('o.id', $id)->where('o.toko_id', $this->tokoId())
            ->select('o.*', 'p1.nama as dibuat_oleh_nama', 'p2.nama as diputuskan_oleh_nama')
            ->first();
        if (! $o) {
            $this->tolak(404, 'Sesi opname tidak ditemukan.');
        }

        $item = DB::table('opname_item as i')
            ->join('barang as b', 'b.id', '=', 'i.barang_id')
            ->leftJoin('kategori_barang as k', 'k.id', '=', 'b.kategori_id')
            ->where('i.opname_id', $id)
            ->orderBy('k.nama')->orderBy('b.nama_barang')
            ->get(['i.barang_id', 'i.stok_sistem', 'i.stok_fisik', 'i.selisih', 'i.alasan',
                'b.kode_barang', 'b.nama_barang', 'b.satuan', 'b.stok as stok_sekarang', 'k.nama as kategori'])
            ->map(fn ($r) => [
                'barang_id' => (int) $r->barang_id,
                'stok_sistem' => (int) $r->stok_sistem,
                'stok_fisik' => $r->stok_fisik === null ? null : (int) $r->stok_fisik,
                'selisih' => $r->selisih === null ? null : (int) $r->selisih,
                'alasan' => (string) $r->alasan,
                'kode_barang' => $r->kode_barang,
                'nama_barang' => $r->nama_barang,
                'satuan' => $r->satuan,
                'stok_sekarang' => (int) $r->stok_sekarang,
                'kategori' => $r->kategori,
            ]);

        $data = (array) $o;
        foreach (['id', 'toko_id', 'dibuat_oleh', 'diputuskan_oleh'] as $k) {
            $data[$k] = $data[$k] === null ? null : (int) $data[$k];
        }
        $data['catatan'] = (string) $data['catatan'];
        $data['alasan_tolak'] = (string) $data['alasan_tolak'];
        $data['item'] = $item;

        return $this->ok($data);
    }

    /** PUT /api/opname/{id} — simpan hitungan (hanya draft). Body: {item:[{barang_id, stok_fisik, alasan}]} */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = $request->input('item');
        if (! is_array($item) || ! array_is_list($item)) {
            $this->tolak(422, 'Data hitungan tidak valid.');
        }
        $this->layanan->simpanHitungan($id, $item);

        return $this->ok(null, 'Hitungan tersimpan.');
    }

    /** DELETE /api/opname/{id} — batalkan sesi yang masih draft. */
    public function destroy(int $id): JsonResponse
    {
        $this->layanan->batalkan($id);

        return $this->ok(null, 'Sesi opname dibatalkan.');
    }

    /** POST /api/opname/{id}/ajukan — kasir/pemilik: draft → diajukan. */
    public function ajukan(int $id): JsonResponse
    {
        return $this->ok(null, $this->layanan->ajukan($id));
    }

    /** POST /api/opname/{id}/setujui|tolak — pemilik saja (middleware peran:pemilik). */
    public function putuskan(Request $request, int $id, string $aksi): JsonResponse
    {
        $pesan = $aksi === 'tolak'
            ? $this->layanan->tolakSesi($id, $this->pengguna()->id, $request->input('alasan'))
            : $this->layanan->setujui($id, $this->tokoId(), $this->pengguna()->id);

        return $this->ok(null, $pesan);
    }
}
