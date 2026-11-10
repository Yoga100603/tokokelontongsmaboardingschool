{{-- Buku hutang/kasbon pelanggan (port app/(app)/hutang/page.tsx). --}}
<x-layouts.app judul="Buku Hutang">
    <div x-data="bukuHutang">
        <x-kepala-halaman judul="Buku Hutang" sub="Catatan bon/kasbon pelanggan. Hutang dari layar kasir (metode Hutang) tercatat otomatis.">
            <x-slot:aksi>
                <button type="button" class="btn btn-utama" @click="baru = true">
                    <x-ikon nama="tambah" class="w-4 h-4" /> Catat Hutang
                </button>
            </x-slot:aksi>
        </x-kepala-halaman>

        <div class="grid grid-cols-2 gap-3 mb-5 max-w-xl">
            <x-statistik label="Total hutang berjalan" warna="rose" ikon="buku"><span x-text="TK.rupiah(d?.aktif.total ?? 0)"></span></x-statistik>
            <x-statistik label="Pelanggan berhutang" warna="amber"><span x-text="d?.aktif.jumlah ?? 0"></span></x-statistik>
        </div>

        <div class="kartu">
            <div class="flex gap-1 p-2 border-b border-slate-100">
                <template x-for="t in ['aktif', 'lunas']" :key="t">
                    <button type="button" @click="tab = t" class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                        :class="tab === t ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        x-text="t === 'aktif' ? 'Belum lunas' : 'Lunas'"></button>
                </template>
            </div>

            <template x-if="!d"><x-memuat /></template>
            <template x-if="d && daftar.length === 0">
                <div class="flex flex-col items-center justify-center px-4 py-12 text-center">
                    <div class="mb-3 text-slate-300"><x-ikon nama="buku" class="h-10 w-10" /></div>
                    <p class="font-semibold text-slate-700" x-text="tab === 'aktif' ? 'Tidak ada hutang berjalan' : 'Belum ada hutang lunas'"></p>
                </div>
            </template>
            <template x-if="d && daftar.length > 0">
                <ul class="divide-y divide-slate-100">
                    <template x-for="h in daftar" :key="h.id">
                        <li class="flex flex-col sm:flex-row sm:items-center gap-2 px-4 py-3">
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-slate-900" x-text="h.nama_pelanggan"></p>
                                <p class="text-xs text-slate-500">
                                    <span x-text="TK.waktu(h.dibuat_pada) + (h.kontak ? ` · ${h.kontak}` : '')"></span>
                                    <template x-if="h.nomor_transaksi">
                                        <span> · <a :href="`/struk/${encodeURIComponent(h.nomor_transaksi)}`" class="font-mono text-emerald-700 hover:underline" x-text="h.nomor_transaksi"></a></span>
                                    </template>
                                </p>
                                <template x-if="h.catatan && !h.nomor_transaksi"><p class="text-xs text-slate-500" x-text="h.catatan"></p></template>
                                <template x-if="h.lunas_pada"><p class="text-xs text-emerald-700" x-text="`Lunas ${TK.waktu(h.lunas_pada)}`"></p></template>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold tabular-nums" x-text="TK.rupiah(h.jumlah)"></span>
                                <template x-if="h.status === 'aktif'">
                                    <div class="flex items-center gap-2">
                                        <template x-if="h.kontak && TK.nomorWa(h.kontak).length >= 10">
                                            <a :href="linkWa(h)" target="_blank" rel="noopener noreferrer"
                                                class="p-1.5 rounded-lg hover:bg-slate-100 text-emerald-700" title="Ingatkan via WhatsApp">
                                                <x-ikon nama="wa" class="w-4 h-4" />
                                            </a>
                                        </template>
                                        <button type="button" class="btn btn-sekunder btn-kecil" @click="lunas = h">
                                            <x-ikon nama="centang-bulat" class="w-4 h-4 text-emerald-600" /> Lunas
                                        </button>
                                    </div>
                                </template>
                                <template x-if="h.status !== 'aktif'">
                                    <x-lencana warna="hijau">Lunas</x-lencana>
                                </template>
                            </div>
                        </li>
                    </template>
                </ul>
            </template>
        </div>

        <x-modal buka="baru" judul="Catat Hutang Manual" lebar="max-w-md">
            <form id="form-hutang" @submit.prevent="simpan" class="space-y-4">
                <div>
                    <label for="f-nama-pelanggan" class="label">Nama pelanggan<span class="text-rose-500"> *</span></label>
                    <input id="f-nama-pelanggan" class="input" required x-model="f.nama_pelanggan">
                </div>
                <div>
                    <label for="f-kontak" class="label">No. HP</label>
                    <input id="f-kontak" class="input" inputmode="tel" x-model="f.kontak">
                </div>
                <div>
                    <label for="f-jumlah" class="label">Nominal (Rp)<span class="text-rose-500"> *</span></label>
                    <x-input-uang model="f.jumlah" id="f-jumlah" required />
                </div>
                <div>
                    <label for="f-catatan" class="label">Catatan</label>
                    <input id="f-catatan" class="input" x-model="f.catatan">
                </div>
            </form>
            <x-slot:kaki>
                <button type="button" class="btn btn-sekunder" @click="baru = false">Batal</button>
                <button type="submit" form="form-hutang" class="btn btn-utama" :disabled="proses">
                    <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses" x-cloak /> Simpan
                </button>
            </x-slot:kaki>
        </x-modal>

        <x-modal buka="lunasBuka" judul="Tandai lunas?" lebar="max-w-sm">
            <p class="text-sm">
                Hutang <b x-text="lunas?.nama_pelanggan"></b> sebesar <b x-text="TK.rupiah(lunas?.jumlah ?? 0)"></b> sudah dibayar?
            </p>
            <x-slot:kaki>
                <button type="button" class="btn btn-sekunder" @click="lunas = null">Batal</button>
                <button type="button" class="btn btn-utama" @click="tandaiLunas()" :disabled="proses">
                    <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses" x-cloak /> Ya, lunas
                </button>
            </x-slot:kaki>
        </x-modal>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                const kosong = () => ({ nama_pelanggan: '', kontak: '', jumlah: 0, catatan: '' });
                Alpine.data('bukuHutang', () => ({
                    d: null,
                    tab: 'aktif',
                    baru: false,
                    f: kosong(),
                    lunas: null,
                    proses: false,

                    init() {
                        this.muat();
                    },
                    get lunasBuka() {
                        return !!this.lunas;
                    },
                    set lunasBuka(v) {
                        if (!v) this.lunas = null;
                    },
                    get daftar() {
                        return this.d?.hutang.filter((h) => h.status === this.tab) ?? [];
                    },
                    linkWa(h) {
                        const teks = `Halo ${h.nama_pelanggan}, mengingatkan catatan bon sebesar ${TK.rupiah(h.jumlah)}. Terima kasih.`;
                        return `https://wa.me/${TK.nomorWa(h.kontak)}?text=${encodeURIComponent(teks)}`;
                    },
                    async muat() {
                        try {
                            this.d = (await TK.panggil('/api/hutang')).data;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },
                    async simpan() {
                        this.proses = true;
                        try {
                            await TK.panggil('/api/hutang', { body: this.f });
                            TK.notif('Catatan hutang disimpan.', 'sukses');
                            this.baru = false;
                            this.f = kosong();
                            this.muat();
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        } finally {
                            this.proses = false;
                        }
                    },
                    async tandaiLunas() {
                        if (!this.lunas) return;
                        this.proses = true;
                        try {
                            await TK.panggil('/api/hutang', { method: 'PATCH', body: { id: this.lunas.id } });
                            TK.notif(`Hutang ${this.lunas.nama_pelanggan} lunas.`, 'sukses');
                            this.lunas = null;
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
