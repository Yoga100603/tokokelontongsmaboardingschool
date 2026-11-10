{{-- Port app/(app)/penjualan/klien.tsx — rekap harian, banding kemarin, rincian transaksi, Tutup Buku Harian. --}}
<x-layouts.app judul="Penjualan Harian">
    <div x-data="penjualanHarian(@js($pemilik))">
        <x-kepala-halaman judul="Penjualan Harian" sub="Ringkasan pemasukan dan rincian setiap transaksi per hari.">
            <x-slot:aksi>
                <div class="flex items-center gap-1">
                    <button type="button" class="btn btn-sekunder btn-kecil" @click="geser(-1)" aria-label="Hari sebelumnya">
                        <x-ikon nama="kiri" class="w-4 h-4" />
                    </button>
                    <input type="date" class="input w-40" :value="tanggal" :max="d?.hari_ini"
                        @change="$event.target.value && (tanggal = $event.target.value)" aria-label="Pilih tanggal">
                    <button type="button" class="btn btn-sekunder btn-kecil" :disabled="!bisaMaju()" @click="geser(1)" aria-label="Hari berikutnya">
                        <x-ikon nama="kanan" class="w-4 h-4" />
                    </button>
                </div>
            </x-slot:aksi>
        </x-kepala-halaman>

        <template x-if="!d">
            <x-memuat />
        </template>

        <template x-if="d">
            <div>
                {{-- Status tutup buku --}}
                <div class="kartu p-4 mb-5 flex flex-col sm:flex-row sm:items-center gap-3 justify-between" :class="d.tutup_buku ? 'bg-slate-50' : ''">
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl p-2" :class="d.tutup_buku ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-700'">
                            <span x-show="d.tutup_buku"><x-ikon nama="kunci" class="w-5 h-5" /></span>
                            <span x-show="!d.tutup_buku"><x-ikon nama="kalender" class="w-5 h-5" /></span>
                        </span>
                        <div>
                            <p class="font-bold text-slate-900"
                                x-text="`${TK.tanggal(d.tanggal, true)} — ${d.tutup_buku ? 'Buku sudah ditutup' : 'Buku masih terbuka'}`"></p>
                            <p class="text-sm text-slate-500" x-text="keteranganStatus()"></p>
                            <template x-if="d.tutup_buku?.catatan">
                                <p class="text-sm text-slate-600 mt-1 italic" x-text="`“${d.tutup_buku.catatan}”`"></p>
                            </template>
                        </div>
                    </div>
                    @if ($pemilik)
                        <template x-if="pemilik && !d.tutup_buku">
                            <button type="button" class="btn btn-utama" @click="tutup = true" :disabled="d.ringkasan.jumlah_transaksi === 0"
                                :title="d.ringkasan.jumlah_transaksi === 0 ? 'Belum ada transaksi' : ''">
                                <x-ikon nama="kalender" class="w-4 h-4" /> Tutup Buku Harian
                            </button>
                        </template>
                    @endif
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                    <x-statistik label="Total pemasukan" ikon="dompet">
                        <span x-text="TK.rupiah(d.ringkasan.total_penjualan)"></span>
                        <x-slot:sub>
                            <span :class="banding().kelas" x-text="banding().teks"></span>
                        </x-slot:sub>
                    </x-statistik>
                    <x-statistik label="Jumlah transaksi" ikon="struk" warna="sky">
                        <span x-text="TK.angka(d.ringkasan.jumlah_transaksi)"></span>
                        <x-slot:sub><span x-text="`Kemarin: ${d.kemarin.jumlah_transaksi}`"></span></x-slot:sub>
                    </x-statistik>
                    <x-statistik label="Barang terjual" ikon="paket" warna="violet">
                        <span x-text="TK.angka(d.ringkasan.item_terjual)"></span>
                    </x-statistik>
                    @if ($pemilik)
                        <x-statistik label="Laba kotor" ikon="tren-naik" warna="amber">
                            <span x-text="TK.rupiah(d.ringkasan.laba_kotor ?? 0)"></span>
                            <x-slot:sub><span x-text="`Modal: ${TK.rupiah(d.ringkasan.total_modal ?? 0)}`"></span></x-slot:sub>
                        </x-statistik>
                    @else
                        <x-statistik label="Uang masuk (kas)" ikon="tren-naik" warna="amber">
                            <span x-text="TK.rupiah(d.ringkasan.uang_tunai)"></span>
                        </x-statistik>
                    @endif
                </div>

                <div class="grid lg:grid-cols-[1fr_320px] gap-5">
                    <div class="kartu">
                        <div class="px-4 py-3 border-b border-slate-100 font-bold text-slate-900" x-text="`Transaksi (${d.transaksi.length})`"></div>
                        <template x-if="d.transaksi.length === 0">
                            <x-kosong ikon="struk" judul="Belum ada transaksi" teks="Transaksi dari layar kasir akan muncul di sini." />
                        </template>
                        <template x-if="d.transaksi.length > 0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50 text-xs text-slate-500 text-left">
                                        <tr>
                                            <th class="px-4 py-2">Jam</th>
                                            <th class="px-4 py-2">No. Struk</th>
                                            <th class="px-4 py-2 hidden sm:table-cell">Kasir</th>
                                            <th class="px-4 py-2">Metode</th>
                                            <th class="px-4 py-2 text-right">Total</th>
                                            @if ($pemilik)
                                                <th class="px-4 py-2 text-right hidden md:table-cell">Laba</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="t in d.transaksi" :key="t.nomor_transaksi">
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-4 py-2 text-slate-500 tabular-nums" x-text="t.waktu.slice(11, 16)"></td>
                                                <td class="px-4 py-2">
                                                    <a :href="urlStruk(t.nomor_transaksi)" class="font-mono text-emerald-700 hover:underline" x-text="t.nomor_transaksi"></a>
                                                    <p class="text-[11px] text-slate-400" x-text="`${t.jumlah_item} item${t.nama_pelanggan ? ' · ' + t.nama_pelanggan : ''}`"></p>
                                                </td>
                                                <td class="px-4 py-2 text-slate-600 hidden sm:table-cell" x-text="t.kasir"></td>
                                                <td class="px-4 py-2">
                                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                                        :class="t.metode_bayar === 'hutang' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                                                        x-text="labelMetode(t.metode_bayar)"></span>
                                                </td>
                                                <td class="px-4 py-2 text-right font-semibold tabular-nums" x-text="TK.rupiah(t.total)"></td>
                                                @if ($pemilik)
                                                    <td class="px-4 py-2 text-right text-emerald-700 tabular-nums hidden md:table-cell" x-text="TK.rupiah(t.laba ?? 0)"></td>
                                                @endif
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                    <div class="space-y-5">
                        <div class="kartu p-4">
                            <h3 class="font-bold text-slate-900 mb-3">Per metode bayar</h3>
                            <p class="text-sm text-slate-500" x-show="d.per_metode.length === 0">-</p>
                            <ul class="space-y-2 text-sm" x-show="d.per_metode.length > 0">
                                <template x-for="m in d.per_metode" :key="m.metode_bayar">
                                    <li class="flex justify-between">
                                        <span class="text-slate-600">
                                            <span x-text="labelMetode(m.metode_bayar)"></span>
                                            <span class="text-xs text-slate-400" x-text="`(${m.jumlah})`"></span>
                                        </span>
                                        <span class="font-semibold tabular-nums" x-text="TK.rupiah(m.total)"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div class="kartu p-4">
                            <h3 class="font-bold text-slate-900 mb-3">Terlaris hari ini</h3>
                            <p class="text-sm text-slate-500" x-show="d.terlaris.length === 0">-</p>
                            <ol class="space-y-2 text-sm" x-show="d.terlaris.length > 0">
                                <template x-for="(b, i) in d.terlaris" :key="b.nama_barang + i">
                                    <li class="flex justify-between gap-2">
                                        <span class="text-slate-700 truncate" x-text="`${i + 1}. ${b.nama_barang}`"></span>
                                        <span class="text-slate-500 whitespace-nowrap" x-text="`${b.jumlah} ${b.satuan}`"></span>
                                    </li>
                                </template>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        @if ($pemilik)
            <x-modal buka="tutup" judul="Tutup Buku Harian" lebar="max-w-md">
                <p class="text-sm text-slate-700 mb-3">
                    Catatan tanggal <b x-text="TK.tanggal(tanggal, true)"></b> akan dikunci. Setelah ditutup, kasir tidak dapat menyimpan transaksi baru pada tanggal ini.
                </p>
                <dl class="rounded-lg bg-slate-50 p-3 text-sm space-y-1 mb-3">
                    <div class="flex justify-between">
                        <dt>Transaksi</dt>
                        <dd class="font-semibold" x-text="d?.ringkasan.jumlah_transaksi ?? 0"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Total penjualan</dt>
                        <dd class="font-semibold" x-text="TK.rupiah(d?.ringkasan.total_penjualan ?? 0)"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Uang masuk (kas)</dt>
                        <dd class="font-semibold" x-text="TK.rupiah(d?.ringkasan.uang_tunai ?? 0)"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Laba kotor</dt>
                        <dd class="font-semibold text-emerald-700" x-text="TK.rupiah(d?.ringkasan.laba_kotor ?? 0)"></dd>
                    </div>
                </dl>
                <textarea class="input" rows="2" placeholder="Catatan (opsional), cth. uang laci sesuai" x-model="catatan" maxlength="300"></textarea>
                <x-slot:kaki>
                    <button type="button" class="btn btn-sekunder" @click="tutup = false" :disabled="proses">Batal</button>
                    <button type="button" class="btn btn-utama" @click="tutupBuku()" :disabled="proses">
                        <span x-show="proses"><x-ikon nama="memuat" class="w-4 h-4 animate-spin" /></span>
                        <span x-show="!proses"><x-ikon nama="kunci" class="w-4 h-4" /></span>
                        Tutup Buku
                    </button>
                </x-slot:kaki>
            </x-modal>
        @endif
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                const LABEL_METODE = { tunai: 'Tunai', transfer: 'Transfer', qris: 'QRIS', hutang: 'Hutang (bon)' };
                const URL_STRUK = @js(url('/struk'));

                Alpine.data('penjualanHarian', (pemilik) => ({
                    pemilik,
                    tanggal: TK.hariIni(),
                    d: null,
                    tutup: false,
                    catatan: '',
                    proses: false,

                    init() {
                        this.muat();
                        this.$watch('tanggal', () => this.muat());
                        // Modal tidak boleh ditutup saat proses berjalan
                        this.$watch('tutup', (v) => { if (!v && this.proses) this.tutup = true; });
                    },

                    async muat() {
                        this.d = null;
                        const tgl = this.tanggal;
                        try {
                            const r = await TK.panggil(`/api/penjualan?tanggal=${encodeURIComponent(tgl)}`);
                            if (tgl === this.tanggal) this.d = r.data;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },

                    geser(n) {
                        this.tanggal = TK.geserHari(this.tanggal, n);
                    },

                    bisaMaju() {
                        return this.d ? this.tanggal < this.d.hari_ini : false;
                    },

                    labelMetode(m) {
                        return LABEL_METODE[m] ?? m;
                    },

                    urlStruk(nomor) {
                        return `${URL_STRUK}/${encodeURIComponent(nomor)}`;
                    },

                    banding() {
                        const kini = this.d.ringkasan.total_penjualan;
                        const lalu = this.d.kemarin.total_penjualan;
                        if (!lalu) return { teks: 'Kemarin: belum ada penjualan', kelas: '' };
                        const p = Math.round(((kini - lalu) / lalu) * 100);
                        return {
                            teks: `${p >= 0 ? '▲' : '▼'} ${Math.abs(p)}% dari kemarin`,
                            kelas: p >= 0 ? 'text-emerald-700' : 'text-rose-700',
                        };
                    },

                    keteranganStatus() {
                        const tb = this.d.tutup_buku;
                        if (tb) return `Ditutup ${TK.waktu(tb.ditutup_pada)} oleh ${tb.ditutup_oleh_nama}. Angka dikunci: ${TK.rupiah(tb.total_penjualan)}.`;
                        return this.pemilik
                            ? 'Periksa rekap dan transaksi, lalu tekan Tutup Buku Harian untuk mengunci catatan hari ini.'
                            : 'Pemilik toko akan menutup buku di akhir hari.';
                    },

                    async tutupBuku() {
                        this.proses = true;
                        try {
                            const r = await TK.panggil('/api/penjualan/tutup-buku', { body: { tanggal: this.tanggal, catatan: this.catatan } });
                            TK.notif(r.pesan ?? 'Buku ditutup.', 'sukses');
                            this.proses = false;
                            this.tutup = false;
                            this.catatan = '';
                            this.muat();
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        } finally {
                            this.proses = false;
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
