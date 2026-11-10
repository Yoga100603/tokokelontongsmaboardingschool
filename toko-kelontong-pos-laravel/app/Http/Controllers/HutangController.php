<?php

namespace App\Http\Controllers;

use App\Models\Hutang;
use App\Services\OpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Buku hutang/kasbon pelanggan (setara app/(app)/hutang + app/api/hutang). */
class HutangController extends Controller
{
    public function halaman(): View
    {
        return view('hutang.index');
    }

    /** GET /api/hutang — daftar (aktif dulu) + ringkasan hutang berjalan. */
    public function index(): JsonResponse
    {
        $tokoId = $this->tokoId();
        $rows = DB::table('hutang as h')
            ->leftJoin('transaksi as t', 't.id', '=', 'h.transaksi_id')
            ->where('h.toko_id', $tokoId)
            ->orderByRaw("CASE WHEN h.status = 'lunas' THEN 1 ELSE 0 END")
            ->orderByDesc('h.id')
            ->limit(300)
            ->get(['h.id', 'h.nama_pelanggan', 'h.kontak', 'h.jumlah', 'h.catatan', 'h.status', 'h.dibuat_pada', 'h.lunas_pada', 't.nomor_transaksi'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'nama_pelanggan' => $r->nama_pelanggan,
                'kontak' => (string) $r->kontak,
                'jumlah' => (int) $r->jumlah,
                'catatan' => (string) $r->catatan,
                'status' => $r->status,
                'dibuat_pada' => $r->dibuat_pada,
                'lunas_pada' => $r->lunas_pada,
                'nomor_transaksi' => $r->nomor_transaksi,
            ]);

        $r = DB::table('hutang')->where('toko_id', $tokoId)->where('status', 'aktif')
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(jumlah), 0) as total')->first();

        return $this->ok(['hutang' => $rows, 'aktif' => ['jumlah' => (int) $r->jumlah, 'total' => (int) $r->total]]);
    }

    /** POST /api/hutang — catat hutang manual (di luar transaksi kasir). */
    public function store(Request $request): JsonResponse
    {
        $data = [
            'nama_pelanggan' => OpnameService::teks($request->input('nama_pelanggan'), 'Nama pelanggan', wajib: true, maks: 80),
            'kontak' => OpnameService::teks($request->input('kontak'), 'Kontak', maks: 30),
            'jumlah' => $this->angka($request->input('jumlah'), 'Nominal hutang', 1),
            'catatan' => OpnameService::teks($request->input('catatan'), 'Catatan', maks: 200),
        ];
        Hutang::create($data); // toko_id & dibuat_pada otomatis

        return $this->ok(null, 'Catatan hutang disimpan.', 201);
    }

    /** PATCH /api/hutang — tandai lunas. Body: {id} */
    public function lunas(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (! is_numeric($id) || (int) $id != $id || (int) $id <= 0) {
            $this->tolak(404, 'Data tidak ditemukan.');
        }
        $n = Hutang::whereKey((int) $id)->where('status', 'aktif')
            ->update(['status' => 'lunas', 'lunas_pada' => now()]);
        if (! $n) {
            $this->tolak(404, 'Hutang tidak ditemukan atau sudah lunas.');
        }

        return $this->ok(null, 'Hutang ditandai lunas.');
    }

    /** Setara angka() di lib/api.ts (bilangan bulat, min/maks). */
    private function angka(mixed $v, string $label, int $min = 0, int $maks = 1_000_000_000_000): int
    {
        if ($v === null || $v === '') {
            $this->tolak(422, "{$label} wajib diisi.");
        }
        if (is_int($v)) {
            $n = $v;
        } elseif (is_float($v)) {
            if (floor($v) !== $v) {
                $this->tolak(422, "{$label} harus berupa bilangan bulat.");
            }
            $n = (int) $v;
        } elseif (is_scalar($v)) {
            $s = preg_replace('/[^\d-]/', '', (string) $v);
            if (! preg_match('/^-?\d*$/', $s)) {
                $this->tolak(422, "{$label} harus berupa bilangan bulat.");
            }
            $n = (int) $s; // "" → 0 (sama dengan Number("") di JS)
        } else {
            $this->tolak(422, "{$label} harus berupa bilangan bulat.");
        }
        if ($n < $min) {
            $this->tolak(422, "{$label} minimal {$min}.");
        }
        if ($n > $maks) {
            $this->tolak(422, "{$label} terlalu besar.");
        }

        return $n;
    }
}
