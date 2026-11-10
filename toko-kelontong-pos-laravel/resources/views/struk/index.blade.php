{{-- Riwayat struk (port app/(app)/struk/page.tsx). Data dari GET /api/transaksi. --}}
@include('struk._skrip')
<x-layouts.app judul="Riwayat Struk">
    <div x-data="riwayatStruk">
        <x-kepala-halaman judul="Riwayat Struk" sub="Cari struk transaksi untuk dilihat ulang, dicetak, disimpan, atau dikirim ke pembeli." />
        <div class="kartu">
            <div class="p-3 border-b border-slate-100 grid sm:grid-cols-[1fr_150px_150px] gap-2">
                <div class="relative">
                    <x-ikon nama="cari" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input type="text" x-model="q" @input="hal = 1" class="input pl-9" placeholder="Nomor struk atau nama pelanggan">
                </div>
                <input type="date" x-model="dari" @input="hal = 1" :max="sampai" class="input" aria-label="Dari tanggal">
                <input type="date" x-model="sampai" @input="hal = 1" :min="dari" class="input" aria-label="Sampai tanggal">
            </div>

            <template x-if="!data">
                <x-memuat />
            </template>
            <template x-if="data && data.transaksi.length === 0">
                <x-kosong ikon="struk" judul="Tidak ada struk" teks="Tidak ada transaksi pada rentang tanggal ini." />
            </template>
            <template x-if="data && data.transaksi.length > 0">
                <div>
                    <div class="px-4 py-2 text-xs text-slate-500 bg-slate-50 border-b border-slate-100"
                        x-text="data.total + ' transaksi · total ' + TK.rupiah(data.nilai)"></div>
                    <ul class="divide-y divide-slate-100">
                        <template x-for="t in data.transaksi" :key="t.nomor_transaksi">
                            <li>
                                <a :href="'{{ url('/struk') }}/' + encodeURIComponent(t.nomor_transaksi)" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <x-ikon nama="struk" class="w-4 h-4" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-slate-900 font-mono text-sm" x-text="t.nomor_transaksi"></p>
                                        <p class="text-xs text-slate-500 truncate"
                                            x-text="TK.waktu(t.waktu) + ' · ' + t.kasir + ' · ' + t.jumlah_item + ' item' + (t.nama_pelanggan ? ' · ' + t.nama_pelanggan : '')"></p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="font-bold tabular-nums" x-text="TK.rupiah(t.total)"></p>
                                        <div class="flex gap-1 justify-end mt-0.5">
                                            <template x-if="t.metode_bayar === 'hutang'">
                                                <x-lencana warna="merah"><span x-text="StrukUtil.labelMetode(t.metode_bayar)"></span></x-lencana>
                                            </template>
                                            <template x-if="t.metode_bayar !== 'hutang'">
                                                <x-lencana warna="abu"><span x-text="StrukUtil.labelMetode(t.metode_bayar)"></span></x-lencana>
                                            </template>
                                            <template x-if="t.jumlah_log > 0">
                                                <x-lencana warna="biru"><x-ikon nama="cetak" class="w-3 h-3" /> <span x-text="t.jumlah_log"></span></x-lencana>
                                            </template>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        </template>
                    </ul>
                    <template x-if="jumlahHal > 1">
                        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm">
                            <button type="button" class="btn btn-sekunder btn-kecil" :disabled="hal <= 1" @click="hal--">
                                <x-ikon nama="kiri" class="w-4 h-4" /> Sebelumnya
                            </button>
                            <span class="text-slate-500" x-text="'Halaman ' + hal + ' dari ' + jumlahHal"></span>
                            <button type="button" class="btn btn-sekunder btn-kecil" :disabled="hal >= jumlahHal" @click="hal++">
                                Berikutnya <x-ikon nama="kanan" class="w-4 h-4" />
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('riwayatStruk', () => ({
                    dari: TK.hariIni(),
                    sampai: TK.hariIni(),
                    q: '',
                    hal: 1,
                    data: null,
                    _jeda: null,

                    init() {
                        this.jadwal();
                        ['dari', 'sampai', 'q', 'hal'].forEach((k) => this.$watch(k, () => this.jadwal()));
                    },

                    get jumlahHal() {
                        return this.data ? Math.max(1, Math.ceil(this.data.total / this.data.per_halaman)) : 1;
                    },

                    jadwal() {
                        clearTimeout(this._jeda);
                        this._jeda = setTimeout(() => this.muat(), 250);
                    },

                    async muat() {
                        const p = new URLSearchParams({ dari: this.dari, sampai: this.sampai, halaman: String(this.hal) });
                        if (this.q.trim()) p.set('q', this.q.trim());
                        try {
                            this.data = (await TK.panggil('/api/transaksi?' + p)).data;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
