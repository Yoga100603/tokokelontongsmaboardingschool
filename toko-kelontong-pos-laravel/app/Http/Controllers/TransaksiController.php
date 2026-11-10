<?php

namespace App\Http\Controllers;

use App\Models\LogStruk;
use App\Models\Transaksi;
use App\Services\StrukService;
use App\Services\TransaksiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Port app/api/transaksi/** dari versi Next.js. */
class TransaksiController extends Controller
{
    /**
     * GET /api/transaksi?dari=&sampai=&q=&halaman=
     * Riwayat struk/transaksi toko aktif.
     */
    public function index(Request $request): JsonResponse
    {
        $dari = self::tanggalValid($request->query('dari')) ? $request->query('dari') : now()->toDateString();
        $sampai = self::tanggalValid($request->query('sampai')) ? $request->query('sampai') : $dari;
        $q = mb_substr(trim((string) (is_string($request->query('q')) ? $request->query('q') : '')), 0, 60);
        $halaman = max(1, (int) (is_numeric($request->query('halaman')) ? $request->query('halaman') : 1));
        $per = 25;

        $saring = function ($b) use ($dari, $sampai, $q) {
            $b->where('t.toko_id', $this->tokoId())
                ->whereDate('t.tanggal', '>=', $dari)
                ->whereDate('t.tanggal', '<=', $sampai);
            if ($q !== '') {
                $b->where(fn ($w) => $w->where('t.nomor_transaksi', 'like', "%{$q}%")->orWhere('t.nama_pelanggan', 'like', "%{$q}%"));
            }

            return $b;
        };

        $rows = $saring(DB::table('transaksi as t')->leftJoin('pengguna as p', 'p.id', '=', 't.pengguna_id'))
            ->select('t.nomor_transaksi', 't.tanggal', 't.waktu', 't.total', 't.uang_bayar', 't.kembalian', 't.metode_bayar',
                't.nama_pelanggan', 't.status', 'p.nama as kasir')
            ->selectSub(fn ($s) => $s->from('item_transaksi as i')->whereColumn('i.transaksi_id', 't.id')->selectRaw('COALESCE(SUM(i.jumlah),0)'), 'jumlah_item')
            ->selectSub(fn ($s) => $s->from('log_struk as l')->whereColumn('l.transaksi_id', 't.id')->selectRaw('COUNT(*)'), 'jumlah_log')
            ->orderByDesc('t.waktu')->orderByDesc('t.id')
            ->limit($per)->offset(($halaman - 1) * $per)
            ->get()
            ->map(fn ($r) => [
                'nomor_transaksi' => $r->nomor_transaksi,
                'tanggal' => substr((string) $r->tanggal, 0, 10),
                'waktu' => substr((string) $r->waktu, 0, 19),
                'total' => (int) $r->total,
                'uang_bayar' => (int) $r->uang_bayar,
                'kembalian' => (int) $r->kembalian,
                'metode_bayar' => $r->metode_bayar,
                'nama_pelanggan' => (string) $r->nama_pelanggan,
                'status' => $r->status,
                'kasir' => (string) ($r->kasir ?? ''),
                'jumlah_item' => (int) $r->jumlah_item,
                'jumlah_log' => (int) $r->jumlah_log,
            ])->all();

        $ringkas = $saring(DB::table('transaksi as t'))
            ->selectRaw("COUNT(*) AS n, COALESCE(SUM(CASE WHEN t.status = 'selesai' THEN t.total END),0) AS nilai")
            ->first();

        return $this->ok([
            'transaksi' => $rows,
            'total' => (int) ($ringkas->n ?? 0),
            'nilai' => (int) ($ringkas->nilai ?? 0),
            'halaman' => $halaman,
            'per_halaman' => $per,
            'dari' => $dari,
            'sampai' => $sampai,
        ]);
    }

    /** POST /api/transaksi — tombol "Bayar". Lihat TransaksiService::buat. */
    public function store(Request $request, TransaksiService $layanan, StrukService $struk): JsonResponse
    {
        $b = self::bacaJson($request);
        $nomor = $layanan->buat($this->pengguna(), $b);

        // Sama dengan sumber: struk respons POST tanpa total_modal (untuk semua peran).
        return $this->ok($struk->ambil($this->tokoId(), $nomor), 'Transaksi berhasil disimpan.', 201);
    }

    /** GET /api/transaksi/{nomor} — detail satu struk. ?format=txt untuk unduhan teks. */
    public function show(Request $request, string $nomor, StrukService $struk): Response
    {
        $s = $struk->ambil($this->tokoId(), $nomor, $this->pengguna()->isPemilik());
        if (! $s) {
            $this->tolak(404, 'Struk tidak ditemukan.');
        }
        if ($request->query('format') === 'txt') {
            $nama = preg_replace('/[^A-Za-z0-9._-]/', '_', $s['nomor_transaksi']);

            return response(StrukService::teks($s), 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"struk-{$nama}.txt\"",
            ]);
        }

        return $this->ok([...$s, 'teks' => StrukService::teks($s)]);
    }

    /** POST /api/transaksi/{nomor}/log — catat aksi struk: cetak, simpan, atau kirim. */
    public function log(Request $request, string $nomor): JsonResponse
    {
        $b = self::bacaJson($request);
        $aksi = in_array($b['aksi'] ?? null, ['cetak', 'simpan', 'kirim'], true) ? $b['aksi'] : $this->tolak(422, 'Aksi struk tidak dikenal.');
        $tujuan = TransaksiService::teks($b['tujuan'] ?? null, 'Tujuan', maks: 60);

        $trx = Transaksi::query()->where('nomor_transaksi', $nomor)->first(['id']);
        if (! $trx) {
            $this->tolak(404, 'Struk tidak ditemukan.');
        }

        $log = new LogStruk(['transaksi_id' => $trx->id, 'pengguna_id' => $this->pengguna()->id, 'aksi' => $aksi, 'tujuan' => $tujuan]);
        $log->toko_id = $this->tokoId();
        $log->save();

        return $this->ok(null, 'Tercatat.');
    }

    /** Setara bacaJson(): badan wajib objek JSON. */
    private static function bacaJson(Request $request): array
    {
        $j = json_decode($request->getContent() ?: 'null', true);
        if (! is_array($j) || (array_is_list($j) && $j !== [])) {
            \App\Support\GalatApi::tolak(400, 'Format data tidak valid.');
        }

        return $j;
    }

    private static function tanggalValid(mixed $s): bool
    {
        if (! is_string($s) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
