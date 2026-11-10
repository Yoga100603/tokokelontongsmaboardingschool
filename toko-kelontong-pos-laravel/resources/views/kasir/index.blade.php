{{-- Layar kasir (port app/(app)/kasir/klien.tsx). Data barang dimuat dari GET /api/barang seperti sumber. --}}
<x-layouts.app judul="Kasir">
    <div x-data="kasir(@js($hariIniDitutup))" @keydown.window="pintasan($event)"
        class="grid lg:grid-cols-[1fr_380px] gap-4 pb-20 lg:pb-0">
        {{-- Daftar barang --}}
        <section class="min-w-0">
            @if ($hariIniDitutup)
                <div class="mb-3 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <x-ikon nama="kunci" class="w-4 h-4 shrink-0" /> Buku hari ini sudah ditutup oleh pemilik. Kasir hanya bisa melihat data.
                </div>
            @endif
            <form @submit.prevent="kirimCari()" class="relative">
                <x-ikon nama="cari" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                <input x-ref="cari" x-model="cari" type="text" class="input pl-10 py-3 text-base"
                    placeholder="Cari nama / ketik atau pindai kode barang lalu Enter  (F2)" aria-label="Cari barang" autocomplete="off">
            </form>
            <div class="flex gap-1.5 overflow-x-auto py-3 no-scrollbar">
                <template x-for="k in daftarKategori" :key="k">
                    <button type="button" @click="kategori = k" x-text="k"
                        class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap border"
                        :class="kategori === k ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200 hover:border-emerald-300'"></button>
                </template>
            </div>

            <template x-if="memuat">
                <x-memuat teks="Memuat barang..." />
            </template>
            <template x-if="!memuat && tersaring.length === 0">
                <div class="kartu">
                    <template x-if="barang.length">
                        <x-kosong ikon="paket" judul="Barang tidak ditemukan" teks="Coba kata kunci lain." />
                    </template>
                    <template x-if="!barang.length">
                        <x-kosong ikon="paket" judul="Belum ada barang" teks="Tambahkan barang dagangan terlebih dahulu.">
                            <a href="{{ route('barang') }}" class="text-sm font-semibold text-emerald-700 hover:underline">Ke Daftar Barang →</a>
                        </x-kosong>
                    </template>
                </div>
            </template>
            <template x-if="!memuat && tersaring.length > 0">
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5">
                    <template x-for="b in tersaring" :key="b.id">
                        <button type="button" @click="tambah(b)" :disabled="b.stok <= 0"
                            class="relative text-left bg-white rounded-xl border p-3 transition-all hover:shadow-md hover:border-emerald-400 disabled:opacity-50 disabled:hover:shadow-none disabled:hover:border-slate-200"
                            :class="diKeranjang(b.id) ? 'border-emerald-500 ring-1 ring-emerald-500' : 'border-slate-200'">
                            <template x-if="diKeranjang(b.id) > 0">
                                <span class="absolute -top-2 -right-2 bg-emerald-600 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center shadow"
                                    x-text="diKeranjang(b.id)"></span>
                            </template>
                            <p class="text-sm font-semibold text-slate-900 line-clamp-2 min-h-[2.5rem]" x-text="b.nama_barang"></p>
                            <p class="text-[11px] text-slate-400 font-mono" x-text="b.kode_barang"></p>
                            <div class="flex items-end justify-between mt-2 gap-1">
                                <span class="font-bold text-emerald-700 text-sm" x-text="TK.rupiah(b.harga_jual)"></span>
                                <template x-if="b.stok <= 0">
                                    <x-lencana warna="merah">Habis</x-lencana>
                                </template>
                                <template x-if="b.stok > 0 && b.stok <= b.stok_minimum">
                                    <x-lencana warna="kuning"><span x-text="b.stok + ' ' + b.satuan"></span></x-lencana>
                                </template>
                                <template x-if="b.stok > b.stok_minimum && b.stok > 0">
                                    <span class="text-[11px] text-slate-500" x-text="b.stok + ' ' + b.satuan"></span>
                                </template>
                            </div>
                        </button>
                    </template>
                </div>
            </template>
        </section>

        {{-- Keranjang + pembayaran --}}
        <aside x-ref="keranjang" class="lg:sticky lg:top-6 self-start">
            <div class="kartu flex flex-col lg:max-h-[calc(100vh-3rem)]">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                    <h2 class="font-bold text-slate-900 flex items-center gap-2">
                        <x-ikon nama="keranjang" class="w-5 h-5 text-emerald-600" /> Keranjang
                        <span class="text-xs font-medium text-slate-500" x-text="'(' + jumlahItem + ' item)'"></span>
                    </h2>
                    <template x-if="keranjang.length > 0">
                        <button type="button" @click="kosongkan()" class="text-xs text-rose-600 hover:underline flex items-center gap-1">
                            <x-ikon nama="muat-ulang" class="w-3.5 h-3.5" /> Kosongkan
                        </button>
                    </template>
                </div>
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 min-h-[120px]">
                    <template x-if="keranjang.length === 0">
                        <p class="text-sm text-slate-400 text-center py-10 px-4">Klik barang atau pindai kode untuk memasukkan ke keranjang.</p>
                    </template>
                    <template x-for="x in keranjang" :key="x.barang.id">
                        <div class="px-4 py-2.5">
                            <div class="flex justify-between gap-2">
                                <p class="text-sm font-medium text-slate-900 leading-tight" x-text="x.barang.nama_barang"></p>
                                <button type="button" @click="aturJumlah(x.barang.id, 0)" class="text-slate-400 hover:text-rose-600"
                                    :aria-label="'Hapus ' + x.barang.nama_barang">
                                    <x-ikon nama="hapus" class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="flex items-center justify-between mt-1.5">
                                <div class="flex items-center border border-slate-200 rounded-lg">
                                    <button type="button" @click="aturJumlah(x.barang.id, x.jumlah - 1)" class="p-1.5 hover:bg-slate-50" aria-label="Kurangi">
                                        <x-ikon nama="kurang" class="w-3.5 h-3.5" />
                                    </button>
                                    <input type="number" min="1" :value="x.jumlah"
                                        @input="aturJumlah(x.barang.id, Math.max(1, Math.floor(Number($event.target.value) || 1)))"
                                        class="w-12 text-center text-sm font-semibold border-x border-slate-200 py-1 focus:outline-none"
                                        :aria-label="'Jumlah ' + x.barang.nama_barang">
                                    <button type="button" @click="tambah(x.barang)" class="p-1.5 hover:bg-slate-50" aria-label="Tambah">
                                        <x-ikon nama="tambah" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <div class="text-right">
                                    <p class="text-[11px] text-slate-400" x-text="'@ ' + TK.rupiah(x.barang.harga_jual)"></p>
                                    <p class="text-sm font-bold text-slate-900" x-text="TK.rupiah(x.barang.harga_jual * x.jumlah)"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="border-t border-slate-200 p-4 space-y-3 bg-slate-50 rounded-b-xl">
                    <div class="flex justify-between items-baseline">
                        <span class="text-sm text-slate-600">Total belanja</span>
                        <span class="text-2xl font-extrabold text-slate-900 tabular-nums" x-text="TK.rupiah(total)"></span>
                    </div>
                    <div class="grid grid-cols-4 gap-1.5">
                        @foreach ([['tunai', 'Tunai', 'uang'], ['transfer', 'Transfer', 'dompet'], ['qris', 'QRIS', 'barcode'], ['hutang', 'Hutang', 'buku']] as [$id, $label, $ikon])
                            <button type="button" @click="metode = '{{ $id }}'; bayar = 0"
                                class="flex flex-col items-center gap-0.5 rounded-lg border py-1.5 text-[11px] font-semibold"
                                :class="metode === '{{ $id }}' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200'">
                                <x-ikon :nama="$ikon" class="w-4 h-4" />
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div x-show="metode === 'tunai' || metode === 'hutang'">
                        <label class="text-xs font-semibold text-slate-700" for="uang-bayar"
                            x-text="metode === 'tunai' ? 'Uang diterima' : 'Uang muka (opsional)'"></label>
                        <x-input-uang model="bayar" id="uang-bayar" placeholder="0" class="mt-1 text-lg font-bold text-right" />
                        <template x-if="metode === 'tunai' && total > 0">
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                <template x-for="p in pecahan" :key="p">
                                    <button type="button" @click="bayar = p" x-text="p === total ? 'Uang pas' : TK.rupiah(p)"
                                        class="px-2 py-1 rounded-md bg-white border border-slate-200 text-[11px] font-semibold text-slate-700 hover:border-emerald-400"></button>
                                </template>
                            </div>
                        </template>
                    </div>
                    <template x-if="metode === 'hutang'">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" x-model="pelanggan" class="input" placeholder="Nama pelanggan *" maxlength="80">
                            <input type="text" x-model="kontak" class="input" placeholder="No. HP (opsional)" maxlength="30">
                        </div>
                    </template>
                    <template x-if="metode === 'tunai'">
                        <div class="flex justify-between items-baseline rounded-lg px-3 py-2"
                            :class="bayar >= total && total > 0 ? 'bg-emerald-100' : 'bg-white border border-slate-200'">
                            <span class="text-sm font-medium text-slate-700">Kembalian</span>
                            <span class="text-xl font-bold tabular-nums" :class="bayar >= total ? 'text-emerald-700' : 'text-slate-400'"
                                x-text="bayar >= total ? TK.rupiah(kembalian) : 'kurang ' + TK.rupiah(total - bayar)"></span>
                        </div>
                    </template>
                    <button type="button" @click="mintaBayar()" :disabled="!keranjang.length || !cukup || hariIniDitutup"
                        class="btn btn-utama w-full py-3 text-base">
                        <span x-text="'Bayar' + (total > 0 ? ' ' + TK.rupiah(total) : '')"></span>
                        <span class="text-xs opacity-75">(F9)</span>
                    </button>
                </div>
            </div>
        </aside>

        {{-- Bar bawah mobile --}}
        <template x-if="keranjang.length > 0">
            <button type="button" @click="$refs.keranjang.scrollIntoView({ behavior: 'smooth' })"
                class="lg:hidden fixed bottom-0 inset-x-0 z-20 bg-emerald-600 text-white px-4 py-3 flex justify-between font-semibold shadow-[0_-4px_12px_rgba(0,0,0,0.1)]">
                <span x-text="'Keranjang (' + jumlahItem + ')'"></span>
                <span x-text="TK.rupiah(total) + ' →'"></span>
            </button>
        </template>

        {{-- Konfirmasi pembayaran: transaksi baru tersimpan setelah ini --}}
        <x-modal buka="konfirmasi" judul="Konfirmasi Pembayaran" lebar="max-w-sm">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Jumlah barang</dt>
                    <dd class="font-semibold" x-text="jumlahItem + ' item'"></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Metode</dt>
                    <dd class="font-semibold" x-text="StrukUtil.labelMetode(metode)"></dd>
                </div>
                <div class="flex justify-between text-base">
                    <dt class="text-slate-700">Total</dt>
                    <dd class="font-bold" x-text="TK.rupiah(total)"></dd>
                </div>
                <template x-if="metode === 'tunai'">
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Uang diterima</dt>
                            <dd class="font-semibold" x-text="TK.rupiah(bayar)"></dd>
                        </div>
                        <div class="flex justify-between text-lg border-t pt-2">
                            <dt class="font-semibold">Kembalian</dt>
                            <dd class="font-extrabold text-emerald-700" x-text="TK.rupiah(kembalian)"></dd>
                        </div>
                    </div>
                </template>
                <template x-if="metode === 'hutang'">
                    <div class="flex justify-between text-lg border-t pt-2">
                        <dt class="font-semibold" x-text="'Dicatat hutang (' + pelanggan + ')'"></dt>
                        <dd class="font-extrabold text-rose-700" x-text="TK.rupiah(total - bayar)"></dd>
                    </div>
                </template>
            </dl>
            <p class="text-xs text-slate-500 mt-3">Stok berkurang dan struk dibuat setelah Anda menekan Konfirmasi Bayar.</p>
            <x-slot:kaki>
                <button type="button" class="btn btn-sekunder" @click="konfirmasi = false" :disabled="proses">Batal</button>
                <button type="button" class="btn btn-utama" @click="simpan()" :disabled="proses" x-ref="tombolKonfirmasi">
                    <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses" />
                    Konfirmasi Bayar
                </button>
            </x-slot:kaki>
        </x-modal>

        {{-- Struk setelah bayar --}}
        <x-modal buka="bukaStruk" judul="Transaksi Berhasil" lebar="max-w-md">
            <template x-if="struk">
                <div class="space-y-4">
                    <div class="text-center">
                        <p class="text-sm text-slate-500">Kembalian</p>
                        <p class="text-3xl font-extrabold text-emerald-700" x-text="TK.rupiah(struk.kembalian)"></p>
                    </div>
                    @include('struk._kertas', ['v' => 'struk'])
                    @include('struk._aksi', ['v' => 'struk'])
                </div>
            </template>
            <x-slot:kaki>
                <button type="button" class="btn btn-utama" @click="bukaStruk = false">Transaksi Baru</button>
            </x-slot:kaki>
        </x-modal>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('kasir', (hariIniDitutup) => ({
                    hariIniDitutup,
                    barang: [],
                    memuat: true,
                    cari: '',
                    kategori: 'Semua',
                    keranjang: [],
                    metode: 'tunai',
                    bayar: 0,
                    pelanggan: '',
                    kontak: '',
                    konfirmasi: false,
                    proses: false,
                    struk: null,
                    bukaStruk: false,

                    init() {
                        this.muat();
                        this.$nextTick(() => this.$refs.cari?.focus());
                        this.$watch('konfirmasi', (v) => {
                            // Tidak bisa ditutup saat sedang menyimpan
                            if (!v && this.proses) this.konfirmasi = true;
                            if (v) setTimeout(() => this.$refs.tombolKonfirmasi?.focus(), 50);
                        });
                        this.$watch('bukaStruk', (v) => {
                            if (!v) {
                                this.struk = null;
                                this.$nextTick(() => this.$refs.cari?.focus());
                            }
                        });
                    },

                    async muat() {
                        try {
                            const r = await TK.panggil('/api/barang');
                            this.barang = r.data.barang;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        } finally {
                            this.memuat = false;
                        }
                    },

                    get total() {
                        return this.keranjang.reduce((s, b) => s + b.barang.harga_jual * b.jumlah, 0);
                    },
                    get jumlahItem() {
                        return this.keranjang.reduce((s, b) => s + b.jumlah, 0);
                    },
                    get kembalian() {
                        return this.metode === 'tunai' ? Math.max(0, this.bayar - this.total) : 0;
                    },
                    get cukup() {
                        if (this.metode === 'tunai') return this.bayar >= this.total;
                        if (this.metode === 'hutang') return this.pelanggan.trim() !== '' && this.bayar < this.total;
                        return true;
                    },
                    get daftarKategori() {
                        return ['Semua', ...Array.from(new Set(this.barang.map((b) => b.kategori ?? 'Lainnya'))).sort()];
                    },
                    get tersaring() {
                        const q = this.cari.trim().toLowerCase();
                        return this.barang.filter(
                            (b) =>
                                (this.kategori === 'Semua' || (b.kategori ?? 'Lainnya') === this.kategori) &&
                                (!q || b.nama_barang.toLowerCase().includes(q) || b.kode_barang.toLowerCase().includes(q))
                        );
                    },
                    get pecahan() {
                        const total = this.total;
                        const s = new Set([total]);
                        for (const p of [5000, 10000, 20000, 50000, 100000]) {
                            const v = Math.ceil(total / p) * p;
                            if (v > total) s.add(v);
                        }
                        return Array.from(s).filter((v) => v > 0).sort((a, b) => a - b).slice(0, 5);
                    },

                    diKeranjang(id) {
                        return this.keranjang.find((x) => x.barang.id === id)?.jumlah ?? 0;
                    },

                    tambah(b, qty = 1) {
                        const ada = this.keranjang.find((x) => x.barang.id === b.id);
                        const baru = (ada?.jumlah ?? 0) + qty;
                        if (b.stok <= 0) {
                            TK.bunyiGalat();
                            return TK.notif(`Stok ${b.nama_barang} habis.`, 'galat');
                        }
                        if (baru > b.stok) {
                            TK.bunyiGalat();
                            return TK.notif(`Stok ${b.nama_barang} tinggal ${b.stok} ${b.satuan}.`, 'galat');
                        }
                        TK.bunyiBeep();
                        if (ada) ada.jumlah = baru;
                        else this.keranjang.push({ barang: b, jumlah: qty });
                    },

                    aturJumlah(id, jumlah) {
                        this.keranjang = this.keranjang
                            .map((x) => {
                                if (x.barang.id !== id) return x;
                                if (jumlah > x.barang.stok) {
                                    TK.notif(`Stok ${x.barang.nama_barang} tinggal ${x.barang.stok} ${x.barang.satuan}.`, 'galat');
                                    return { ...x, jumlah: x.barang.stok };
                                }
                                return { ...x, jumlah };
                            })
                            .filter((x) => x.jumlah > 0);
                    },

                    kirimCari() {
                        const q = this.cari.trim().toLowerCase();
                        if (!q) return;
                        const persis =
                            this.barang.find((b) => b.kode_barang.toLowerCase() === q) ??
                            this.barang.find((b) => b.nama_barang.toLowerCase() === q);
                        const tersaring = this.tersaring;
                        const pilih = persis ?? (tersaring.length === 1 ? tersaring[0] : null);
                        if (pilih) {
                            this.tambah(pilih);
                            this.cari = '';
                        } else if (tersaring.length === 0) {
                            TK.bunyiGalat();
                            TK.notif(`Barang "${this.cari}" tidak ditemukan.`, 'galat');
                        }
                    },

                    kosongkan() {
                        this.keranjang = [];
                        this.bayar = 0;
                        this.pelanggan = '';
                        this.kontak = '';
                        this.metode = 'tunai';
                    },

                    mintaBayar() {
                        const galat = (p) => TK.notif(p, 'galat');
                        if (this.hariIniDitutup) return galat('Buku hari ini sudah ditutup. Transaksi baru tidak bisa disimpan.');
                        if (!this.keranjang.length) return galat('Keranjang masih kosong.');
                        if (this.metode === 'tunai' && this.bayar < this.total) {
                            TK.bunyiGalat();
                            return galat(`Uang pembayaran kurang ${TK.rupiah(this.total - this.bayar)}.`);
                        }
                        if (this.metode === 'hutang' && !this.pelanggan.trim()) return galat('Isi nama pelanggan untuk mencatat hutang.');
                        if (this.metode === 'hutang' && this.bayar >= this.total) return galat('Uang muka sudah menutup total. Pilih metode Tunai.');
                        this.konfirmasi = true;
                    },

                    async simpan() {
                        if (this.proses) return;
                        this.proses = true;
                        try {
                            const r = await TK.panggil('/api/transaksi', {
                                body: {
                                    item: this.keranjang.map((x) => ({ barang_id: x.barang.id, jumlah: x.jumlah })),
                                    metode_bayar: this.metode,
                                    uang_bayar: this.metode === 'tunai' || this.metode === 'hutang' ? this.bayar : this.total,
                                    nama_pelanggan: this.pelanggan,
                                    kontak: this.kontak,
                                },
                            });
                            TK.bunyiSukses();
                            this.proses = false;
                            this.konfirmasi = false;
                            this.struk = r.data;
                            this.bukaStruk = true;
                            this.kosongkan();
                            this.muat();
                        } catch (e) {
                            TK.bunyiGalat();
                            TK.notif(e.message, 'galat');
                            // stok mungkin berubah oleh kasir lain → segarkan
                            this.muat();
                        } finally {
                            this.proses = false;
                        }
                    },

                    // Pintasan keyboard: F2 = cari, F9 = bayar
                    pintasan(e) {
                        if (e.key === 'F2') {
                            e.preventDefault();
                            this.$refs.cari?.focus();
                        }
                        if (e.key === 'F9' && !this.konfirmasi && !this.bukaStruk) {
                            e.preventDefault();
                            this.mintaBayar();
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
