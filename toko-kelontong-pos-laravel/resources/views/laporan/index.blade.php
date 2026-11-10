{{-- Port app/(app)/laporan/klien.tsx — rentang tanggal, grafik, produk terlaris, laba kotor, unduh CSV. --}}
<x-layouts.app judul="Laporan Penjualan">
    <div x-data="laporanPenjualan">
        <x-kepala-halaman judul="Laporan Penjualan" sub="Penjualan per rentang tanggal, produk terlaris, dan ringkasan laba kotor.">
            <x-slot:aksi>
                <a :href="`/api/laporan/unduh?dari=${dari}&sampai=${sampai}`" class="btn btn-sekunder">
                    <x-ikon nama="unduh" class="w-4 h-4" /> Unduh (Excel/CSV)
                </a>
            </x-slot:aksi>
        </x-kepala-halaman>

        <div class="kartu p-3 mb-5 flex flex-wrap items-center gap-2">
            <template x-for="p in preset()" :key="p.l">
                <button type="button" @click="dari = p.a; sampai = p.b"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border"
                    :class="dari === p.a && sampai === p.b ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200'"
                    x-text="p.l"></button>
            </template>
            <div class="flex items-center gap-2 ml-auto">
                <input type="date" class="input w-40" :value="dari" :max="sampai"
                    @change="$event.target.value && (dari = $event.target.value)" aria-label="Dari tanggal">
                <span class="text-slate-400">–</span>
                <input type="date" class="input w-40" :value="sampai" :min="dari" :max="hari"
                    @change="$event.target.value && (sampai = $event.target.value)" aria-label="Sampai tanggal">
            </div>
        </div>

        <template x-if="!d">
            <x-memuat />
        </template>

        <template x-if="d">
            <div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                    <x-statistik label="Total penjualan" ikon="dompet">
                        <span x-text="TK.rupiah(d.ringkasan.total_penjualan)"></span>
                        <x-slot:sub><span :class="tren().kelas" x-text="tren().teks"></span></x-slot:sub>
                    </x-statistik>
                    <x-statistik label="Laba kotor" ikon="tren-naik" warna="amber">
                        <span x-text="TK.rupiah(d.ringkasan.laba_kotor)"></span>
                        <x-slot:sub><span x-text="`Margin ${margin()}% · modal ${TK.rupiah(d.ringkasan.total_modal)}`"></span></x-slot:sub>
                    </x-statistik>
                    <x-statistik label="Transaksi" ikon="struk" warna="sky">
                        <span x-text="TK.angka(d.ringkasan.jumlah_transaksi)"></span>
                        <x-slot:sub><span x-text="`Rata-rata ${TK.rupiah(d.ringkasan.rata_rata_transaksi ?? 0)}`"></span></x-slot:sub>
                    </x-statistik>
                    <x-statistik label="Barang terjual" ikon="paket" warna="violet">
                        <span x-text="TK.angka(d.ringkasan.item_terjual)"></span>
                    </x-statistik>
                </div>

                <div class="kartu p-4 mb-5">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="font-bold text-slate-900">Penjualan per hari</h2>
                            <p class="text-xs text-slate-500" x-text="`${TK.tanggal(d.dari)} – ${TK.tanggal(d.sampai)}`"></p>
                        </div>
                        <button type="button" class="btn btn-hantu btn-kecil" @click="tabel = !tabel">
                            <span x-show="tabel"><x-ikon nama="grafik" class="w-4 h-4" /></span>
                            <span x-show="!tabel"><x-ikon nama="file" class="w-4 h-4" /></span>
                            <span x-text="tabel ? 'Grafik' : 'Tabel'"></span>
                        </button>
                    </div>
                    <div x-show="tabel" class="overflow-x-auto max-h-80">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-slate-500 text-left bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2">Tanggal</th>
                                    <th class="px-3 py-2 text-right">Transaksi</th>
                                    <th class="px-3 py-2 text-right">Penjualan</th>
                                    <th class="px-3 py-2 text-right">Laba kotor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="h in d.harian" :key="h.tanggal">
                                    <tr>
                                        <td class="px-3 py-1.5" x-text="TK.tanggal(h.tanggal, true)"></td>
                                        <td class="px-3 py-1.5 text-right tabular-nums" x-text="h.jumlah_transaksi"></td>
                                        <td class="px-3 py-1.5 text-right tabular-nums" x-text="TK.rupiah(h.penjualan)"></td>
                                        <td class="px-3 py-1.5 text-right tabular-nums" x-text="TK.rupiah(h.laba)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div x-show="!tabel">
                        @include('laporan._grafik', ['data' => 'd.harian', 'tinggi' => 180])
                    </div>
                </div>

                <div class="grid lg:grid-cols-[1fr_320px] gap-5">
                    <div class="kartu">
                        <div class="px-4 py-3 border-b border-slate-100 font-bold text-slate-900">Produk terlaris</div>
                        <template x-if="d.terlaris.length === 0">
                            <x-kosong judul="Belum ada penjualan pada periode ini" />
                        </template>
                        <template x-if="d.terlaris.length > 0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50 text-xs text-slate-500 text-left">
                                        <tr>
                                            <th class="px-4 py-2 w-8">#</th>
                                            <th class="px-4 py-2">Barang</th>
                                            <th class="px-4 py-2 text-right">Terjual</th>
                                            <th class="px-4 py-2 text-right">Penjualan</th>
                                            <th class="px-4 py-2 text-right">Laba kotor</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(b, i) in d.terlaris" :key="b.nama_barang + i">
                                            <tr>
                                                <td class="px-4 py-2 text-slate-400" x-text="i + 1"></td>
                                                <td class="px-4 py-2 font-medium" x-text="b.nama_barang"></td>
                                                <td class="px-4 py-2 text-right tabular-nums whitespace-nowrap" x-text="`${b.jumlah} ${b.satuan}`"></td>
                                                <td class="px-4 py-2 text-right tabular-nums" x-text="TK.rupiah(b.penjualan)"></td>
                                                <td class="px-4 py-2 text-right tabular-nums text-emerald-700" x-text="TK.rupiah(b.laba)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                    <div class="kartu p-4 self-start">
                        <h3 class="font-bold text-slate-900 mb-3">Metode pembayaran</h3>
                        <p class="text-sm text-slate-500" x-show="d.per_metode.length === 0">-</p>
                        <ul class="space-y-3 text-sm" x-show="d.per_metode.length > 0">
                            <template x-for="m in d.per_metode" :key="m.metode_bayar">
                                <li>
                                    <div class="flex justify-between">
                                        <span class="text-slate-700">
                                            <span x-text="labelMetode(m.metode_bayar)"></span>
                                            <span class="text-xs text-slate-400" x-text="`(${m.jumlah})`"></span>
                                        </span>
                                        <span class="font-semibold tabular-nums" x-text="TK.rupiah(m.total)"></span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-slate-100 mt-1 overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500"
                                            :style="`width: ${(m.total / Math.max(1, d.ringkasan.total_penjualan)) * 100}%`"></div>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                const LABEL_METODE = { tunai: 'Tunai', transfer: 'Transfer', qris: 'QRIS', hutang: 'Hutang (bon)' };

                Alpine.data('laporanPenjualan', () => ({
                    hari: TK.hariIni(),
                    dari: TK.geserHari(TK.hariIni(), -6),
                    sampai: TK.hariIni(),
                    d: null,
                    tabel: false,

                    init() {
                        this.muat();
                        this.$watch('dari', () => this.muat());
                        this.$watch('sampai', () => this.muat());
                    },

                    async muat() {
                        const { dari, sampai } = this;
                        if (!dari || !sampai || dari > sampai) return;
                        this.d = null;
                        try {
                            const r = await TK.panggil(`/api/laporan?dari=${encodeURIComponent(dari)}&sampai=${encodeURIComponent(sampai)}`);
                            if (dari === this.dari && sampai === this.sampai) this.d = r.data;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },

                    preset() {
                        const h = this.hari;
                        return [
                            { l: '7 hari', a: TK.geserHari(h, -6), b: h },
                            { l: '30 hari', a: TK.geserHari(h, -29), b: h },
                            { l: 'Bulan ini', a: h.slice(0, 8) + '01', b: h },
                        ];
                    },

                    labelMetode(m) {
                        return LABEL_METODE[m] ?? m;
                    },

                    margin() {
                        const r = this.d?.ringkasan;
                        return r && r.total_penjualan ? Math.round((r.laba_kotor / r.total_penjualan) * 100) : 0;
                    },

                    tren() {
                        const kini = this.d.ringkasan.total_penjualan;
                        const lalu = this.d.periode_sebelumnya.total_penjualan;
                        if (!lalu) return { teks: 'Periode sebelumnya: -', kelas: '' };
                        const p = Math.round(((kini - lalu) / lalu) * 100);
                        return {
                            teks: `${p >= 0 ? '▲' : '▼'} ${Math.abs(p)}% dari periode sebelumnya`,
                            kelas: p >= 0 ? 'text-emerald-700' : 'text-rose-700',
                        };
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
