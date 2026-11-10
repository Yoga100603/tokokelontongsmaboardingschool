{{-- Port app/(app)/dasbor/klien.tsx — kondisi toko hari ini dalam satu halaman (pemilik). --}}
<x-layouts.app judul="Dasbor">
    <div x-data="dasborToko">
        <template x-if="!d">
            <x-memuat />
        </template>

        <template x-if="d">
            <div>
                <div class="mb-5">
                    <h1 class="text-xl font-bold text-slate-900">Halo, {{ explode(' ', trim($nama))[0] }} 👋</h1>
                    <p class="text-sm text-slate-500" x-text="`${TK.tanggal(d.tanggal, true)} — kondisi toko hari ini dalam satu halaman.`"></p>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                    <x-statistik label="Penjualan hari ini" ikon="dompet">
                        <span x-text="TK.rupiah(d.hari_ini.total_penjualan)"></span>
                        <x-slot:sub><span x-text="persen(d.hari_ini.total_penjualan, d.kemarin.total_penjualan)"></span></x-slot:sub>
                    </x-statistik>
                    <x-statistik label="Laba kotor hari ini" ikon="tren-naik" warna="amber">
                        <span x-text="TK.rupiah(d.hari_ini.laba_kotor)"></span>
                        <x-slot:sub><span x-text="`Modal ${TK.rupiah(d.hari_ini.total_modal)}`"></span></x-slot:sub>
                    </x-statistik>
                    <x-statistik label="Transaksi hari ini" ikon="struk" warna="sky">
                        <span x-text="TK.angka(d.hari_ini.jumlah_transaksi)"></span>
                        <x-slot:sub><span x-text="`${TK.angka(d.hari_ini.item_terjual)} barang terjual`"></span></x-slot:sub>
                    </x-statistik>
                    <x-statistik label="Hutang pelanggan" ikon="buku" warna="rose">
                        <span x-text="TK.rupiah(d.hutang.total)"></span>
                        <x-slot:sub><span x-text="`${d.hutang.jumlah} catatan belum lunas`"></span></x-slot:sub>
                    </x-statistik>
                </div>

                <div class="grid lg:grid-cols-[1fr_340px] gap-5">
                    <div class="space-y-5">
                        <div class="kartu p-4">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="font-bold text-slate-900">Penjualan 7 hari terakhir</h2>
                                <a href="{{ route('laporan') }}" class="text-xs font-semibold text-emerald-700 hover:underline">Laporan lengkap →</a>
                            </div>
                            @include('laporan._grafik', ['data' => 'd.grafik', 'tinggi' => 160])
                        </div>
                        <div class="kartu">
                            <div class="px-4 py-3 border-b border-slate-100 flex justify-between">
                                <h2 class="font-bold text-slate-900">Terlaris 7 hari</h2>
                            </div>
                            <p class="p-4 text-sm text-slate-500" x-show="d.terlaris.length === 0">Belum ada penjualan.</p>
                            <ol class="divide-y divide-slate-100" x-show="d.terlaris.length > 0">
                                <template x-for="(b, i) in d.terlaris" :key="b.nama_barang + i">
                                    <li class="flex items-center justify-between px-4 py-2.5 text-sm">
                                        <span class="truncate">
                                            <span class="text-slate-400 mr-2" x-text="i + 1"></span><span x-text="b.nama_barang"></span>
                                        </span>
                                        <span class="text-slate-600 whitespace-nowrap">
                                            <span x-text="`${b.jumlah} ${b.satuan}`"></span> · <b class="text-slate-900" x-text="TK.rupiah(b.penjualan)"></b>
                                        </span>
                                    </li>
                                </template>
                            </ol>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="kartu p-4">
                            <h2 class="font-bold text-slate-900 mb-3">
                                Perlu perhatian
                                <span x-show="perhatian() > 0">
                                    <x-lencana warna="kuning"><span x-text="perhatian()"></span></x-lencana>
                                </span>
                            </h2>
                            <ul class="space-y-2 text-sm">
                                <template x-for="o in d.opname_menunggu" :key="o.id">
                                    <li>
                                        <a :href="`${URL_OPNAME}/${o.id}`" class="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 hover:bg-amber-100">
                                            <x-ikon nama="opname" class="w-4 h-4 text-amber-700 shrink-0" />
                                            <span class="flex-1">Opname <b class="font-mono" x-text="o.kode"></b> menunggu persetujuan</span>
                                            <x-ikon nama="kanan" class="w-4 h-4" />
                                        </a>
                                    </li>
                                </template>
                                <template x-for="b in d.belum_tutup_buku" :key="b.tanggal">
                                    <li>
                                        <a href="{{ route('penjualan') }}" class="flex items-center gap-2 rounded-lg bg-sky-50 border border-sky-200 px-3 py-2 hover:bg-sky-100">
                                            <x-ikon nama="kalender" class="w-4 h-4 text-sky-700 shrink-0" />
                                            <span class="flex-1" x-text="`${TK.tanggal(b.tanggal)} belum tutup buku (${b.jumlah} transaksi)`"></span>
                                            <x-ikon nama="kanan" class="w-4 h-4" />
                                        </a>
                                    </li>
                                </template>
                                <li class="flex items-center gap-2 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2">
                                    <span x-show="d.hari_ini_ditutup"><x-ikon nama="kunci" class="w-4 h-4 text-slate-600" /></span>
                                    <span x-show="!d.hari_ini_ditutup"><x-ikon nama="kalender" class="w-4 h-4 text-emerald-600" /></span>
                                    <span class="flex-1" x-text="d.hari_ini_ditutup ? 'Buku hari ini sudah ditutup' : 'Buku hari ini masih terbuka'"></span>
                                    <a x-show="!d.hari_ini_ditutup" href="{{ route('penjualan') }}" class="text-xs font-semibold text-emerald-700 hover:underline">Rekap →</a>
                                </li>
                            </ul>
                        </div>
                        <div class="kartu">
                            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                                <h2 class="font-bold text-slate-900 flex items-center gap-2">
                                    <x-ikon nama="peringatan" class="w-4 h-4 text-amber-600" /> Stok menipis
                                </h2>
                                <a href="{{ route('barang') }}" class="text-xs font-semibold text-emerald-700 hover:underline">Kelola →</a>
                            </div>
                            <p class="p-4 text-sm text-slate-500" x-show="d.stok_menipis.length === 0">Semua stok aman.</p>
                            <ul class="divide-y divide-slate-100" x-show="d.stok_menipis.length > 0">
                                <template x-for="b in d.stok_menipis" :key="b.id">
                                    <li class="flex justify-between px-4 py-2 text-sm">
                                        <span class="truncate" x-text="b.nama_barang"></span>
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            :class="b.stok === 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                                            x-text="b.stok === 0 ? 'Habis' : `${b.stok} ${b.satuan}`"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('dasborToko', () => ({
                    d: null,
                    URL_OPNAME: @js(url('/opname')),

                    async init() {
                        try {
                            this.d = (await TK.panggil('/api/dasbor')).data;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },

                    persen(kini, lalu) {
                        if (!lalu) return 'Kemarin belum ada penjualan';
                        const p = Math.round(((kini - lalu) / lalu) * 100);
                        return `${p >= 0 ? '▲' : '▼'} ${Math.abs(p)}% dari kemarin`;
                    },

                    perhatian() {
                        return this.d.opname_menunggu.length + this.d.belum_tutup_buku.length + (this.d.stok_menipis.length ? 1 : 0);
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
