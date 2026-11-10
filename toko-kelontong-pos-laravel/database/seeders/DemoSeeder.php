<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\ItemTransaksi;
use App\Models\KategoriBarang;
use App\Models\MutasiStok;
use App\Models\Pengguna;
use App\Models\Toko;
use App\Models\Transaksi;
use App\Models\TutupBuku;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data demo toko pertama (port dari src/lib/seed.ts versi Next.js).
 * Akun: pemilik@demo.id / demo1234 · kasir@demo.id / demo1234
 * Katalog: /toko/toko-berkah
 */
class DemoSeeder extends Seeder
{
    private int $acak = 7;

    /** RNG deterministik yang sama dengan versi Next.js → data demo identik. */
    private function rnd(int $n): int
    {
        $this->acak = ($this->acak * 9301 + 49297) % 233280;

        return (int) floor(($this->acak / 233280) * $n);
    }

    public function run(): void
    {
        if (Toko::where('slug', 'toko-berkah')->exists()) {
            $this->command?->warn('Toko demo sudah ada — dilewati.');

            return;
        }

        DB::transaction(function () {
            $toko = Toko::create([
                'nama' => 'Toko Kelontong Berkah',
                'slug' => 'toko-berkah',
                'alamat' => 'Jl. Merdeka No. 45, Pasar Baru',
                'telepon' => '081234567890',
                'email' => 'pemilik@demo.id',
                'deskripsi' => 'Menyediakan sembako, minuman, bumbu dapur, dan kebutuhan harian dengan harga bersahabat.',
                'jam_buka' => 'Senin–Sabtu 06.00–21.00, Minggu 07.00–17.00',
                'catatan_struk' => 'Barang yang sudah dibeli tidak dapat ditukar kecuali ada perjanjian.',
            ]);

            $buatAkun = function (string $nama, string $email, string $peran) use ($toko) {
                $p = new Pengguna(['nama' => $nama, 'email' => $email, 'kata_sandi' => 'demo1234', 'peran' => $peran]);
                $p->toko_id = $toko->id;
                $p->save();

                return $p;
            };
            $pemilik = $buatAkun('Pak Supri', 'pemilik@demo.id', 'pemilik');
            $kasir = $buatAkun('Rina (Kasir)', 'kasir@demo.id', 'kasir');

            $kategori = [];
            foreach (['Sembako', 'Makanan', 'Minuman', 'Bumbu', 'Kebersihan', 'Rokok'] as $nama) {
                $k = new KategoriBarang(['nama' => $nama]);
                $k->toko_id = $toko->id;
                $k->save();
                $kategori[$nama] = $k->id;
            }

            // kode, nama, kategori, harga jual, harga modal, stok, stok minimum, satuan
            $daftar = [
                ['8999999001', 'Beras Rojolele 5kg', 'Sembako', 72000, 65000, 30, 5, 'karung'],
                ['8999999002', 'Minyak Goreng 1L', 'Sembako', 19500, 17500, 40, 6, 'pouch'],
                ['8999999003', 'Gula Pasir 1kg', 'Sembako', 17500, 15500, 30, 5, 'bks'],
                ['8999999004', 'Telur Ayam Negeri 1kg', 'Sembako', 28000, 25000, 20, 3, 'kg'],
                ['8998866101', 'Mi Instan Goreng', 'Makanan', 3500, 3000, 120, 20, 'bks'],
                ['8998866102', 'Mi Instan Kuah Ayam Bawang', 'Makanan', 3500, 3000, 100, 20, 'bks'],
                ['8992753211', 'Kopi Sachet Mix', 'Minuman', 2000, 1600, 12, 10, 'sachet'],
                ['8992753212', 'Teh Celup Kotak', 'Minuman', 7000, 6000, 18, 5, 'kotak'],
                ['8991389221', 'Air Mineral 600ml', 'Minuman', 4000, 3000, 60, 12, 'btl'],
                ['8991389222', 'Teh Kotak 250ml', 'Minuman', 4500, 3800, 30, 6, 'kotak'],
                ['8993189111', 'Sabun Mandi Batang', 'Kebersihan', 4500, 3800, 8, 5, 'batang'],
                ['8993189112', 'Sampo Sachet', 'Kebersihan', 1000, 800, 60, 15, 'sachet'],
                ['8993189113', 'Pasta Gigi 120g', 'Kebersihan', 9000, 7800, 14, 4, 'tube'],
                ['8996001301', 'Rokok Kretek 12', 'Rokok', 24000, 22500, 15, 5, 'bungkus'],
                ['8996001302', 'Rokok Mild 16', 'Rokok', 33000, 31000, 12, 5, 'bungkus'],
                ['8991001401', 'Kecap Manis 210ml', 'Bumbu', 11500, 10000, 16, 4, 'pouch'],
                ['8991001402', 'Kaldu Sapi Sachet', 'Bumbu', 1000, 750, 50, 10, 'sachet'],
                ['8991001403', 'Garam Dapur 250g', 'Bumbu', 3000, 2200, 25, 5, 'bks'],
            ];

            $awal = now()->subDays(14)->setTime(7, 0);
            $barang = [];
            foreach ($daftar as [$kode, $nama, $kat, $jual, $modal, $stok, $min, $satuan]) {
                $b = new Barang([
                    'kategori_id' => $kategori[$kat], 'kode_barang' => $kode, 'nama_barang' => $nama,
                    'satuan' => $satuan, 'harga_modal' => $modal, 'harga_jual' => $jual, 'stok_minimum' => $min,
                ]);
                $b->toko_id = $toko->id;
                $b->stok = $stok;
                $b->dibuat_pada = $b->diubah_pada = $awal;
                $b->save();

                $this->mutasi($toko->id, $b->id, $pemilik->id, 'awal', $stok, 0, $stok, 'Stok awal barang', '', $awal);
                $barang[] = $b;
            }

            // Riwayat penjualan 7 hari terakhir (tanpa hari ini) agar laporan langsung berisi.
            for ($h = 7; $h >= 1; $h--) {
                $tgl = now()->subDays($h)->toDateString();
                $jumlahTrx = 3 + $this->rnd(4);

                for ($i = 1; $i <= $jumlahTrx; $i++) {
                    $waktu = sprintf('%s %02d:%02d:00', $tgl, 8 + $i * 2, $this->rnd(60));
                    $pilih = []; // index barang => qty
                    $n = 1 + $this->rnd(3);
                    for ($k = 0; $k < $n; $k++) {
                        $idx = $this->rnd(count($barang));
                        $q = 1 + $this->rnd(3);
                        if (($pilih[$idx] ?? 0) + $q <= $barang[$idx]->stok - 2) {
                            $pilih[$idx] = ($pilih[$idx] ?? 0) + $q;
                        }
                    }
                    if (! $pilih) {
                        continue;
                    }

                    $total = $totalModal = 0;
                    foreach ($pilih as $idx => $q) {
                        $total += $barang[$idx]->harga_jual * $q;
                        $totalModal += $barang[$idx]->harga_modal * $q;
                    }
                    $bayar = (int) ceil($total / 10000) * 10000;
                    $nomor = sprintf('TRX-%s-%04d', str_replace('-', '', $tgl), $i);

                    $trx = new Transaksi([
                        'pengguna_id' => $kasir->id, 'nomor_transaksi' => $nomor, 'tanggal' => $tgl,
                        'waktu' => $waktu, 'total' => $total, 'total_modal' => $totalModal,
                        'uang_bayar' => $bayar, 'kembalian' => $bayar - $total, 'metode_bayar' => 'tunai',
                    ]);
                    $trx->toko_id = $toko->id;
                    $trx->save();

                    foreach ($pilih as $idx => $q) {
                        $b = $barang[$idx];
                        ItemTransaksi::create([
                            'transaksi_id' => $trx->id, 'barang_id' => $b->id, 'nama_barang' => $b->nama_barang,
                            'satuan' => $b->satuan, 'jumlah' => $q, 'harga_jual' => $b->harga_jual,
                            'harga_modal' => $b->harga_modal, 'subtotal' => $b->harga_jual * $q,
                        ]);
                        $this->mutasi($toko->id, $b->id, $kasir->id, 'penjualan', -$q, $b->stok, $b->stok - $q, 'Penjualan', $nomor, $waktu);
                        $b->stok -= $q;
                        $b->saveQuietly();
                    }
                }

                // Hari-hari lalu sudah tutup buku, kecuali kemarin (agar bisa dicoba).
                if ($h > 1) {
                    $r = Transaksi::where('toko_id', $toko->id)->whereDate('tanggal', $tgl)->selesai()
                        ->selectRaw('COUNT(*) n, COALESCE(SUM(total),0) total, COALESCE(SUM(total_modal),0) modal')
                        ->first();
                    if ($r && $r->n > 0) {
                        $tb = new TutupBuku([
                            'tanggal' => $tgl, 'jumlah_transaksi' => $r->n, 'total_penjualan' => $r->total,
                            'total_modal' => $r->modal, 'laba_kotor' => $r->total - $r->modal,
                            'uang_tunai' => $r->total, 'ditutup_oleh' => $pemilik->id, 'ditutup_pada' => "$tgl 21:05:00",
                        ]);
                        $tb->toko_id = $toko->id;
                        $tb->save();
                    }
                }
            }
        });

        $this->command?->info('Data demo dibuat: pemilik@demo.id / kasir@demo.id (sandi demo1234).');
    }

    private function mutasi(int $tokoId, int $barangId, int $penggunaId, string $jenis, int $jumlah, int $sebelum, int $sesudah, string $ket, string $ref, $waktu): void
    {
        $m = new MutasiStok([
            'barang_id' => $barangId, 'pengguna_id' => $penggunaId, 'jenis' => $jenis, 'jumlah' => $jumlah,
            'stok_sebelum' => $sebelum, 'stok_sesudah' => $sesudah, 'keterangan' => $ket, 'referensi' => $ref,
        ]);
        $m->toko_id = $tokoId;
        $m->dibuat_pada = $waktu;
        $m->save();
    }
}
