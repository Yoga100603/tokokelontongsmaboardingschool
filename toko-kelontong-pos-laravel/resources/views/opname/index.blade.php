{{-- Daftar sesi stok opname (port app/(app)/opname/klien.tsx). --}}
<x-layouts.app judul="Stok Opname">
    <div x-data="daftarOpname">
        <x-kepala-halaman judul="Stok Opname"
            :sub="$pemilik ? 'Periksa hasil hitung fisik dari kasir lalu setujui atau tolak. Stok hanya berubah setelah disetujui.' : 'Hitung stok fisik di rak, isi alasan bila ada selisih, lalu ajukan ke pemilik.'">
            <x-slot:aksi>
                <button type="button" class="btn btn-utama" @click="baru = true">
                    <x-ikon nama="tambah" class="w-4 h-4" /> Mulai Opname
                </button>
            </x-slot:aksi>
        </x-kepala-halaman>

        @if ($pemilik)
            <template x-if="(data?.ringkasan.diajukan ?? 0) > 0">
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Ada <b x-text="data.ringkasan.diajukan"></b> sesi opname menunggu persetujuan Anda.
                </div>
            </template>
        @endif

        <div class="kartu">
            <div class="flex gap-1 p-2 border-b border-slate-100 overflow-x-auto">
                <template x-for="[v, l] in tab" :key="v">
                    <button type="button" @click="pilihFilter(v)"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap"
                        :class="filter === v ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        x-text="l + (v && data?.ringkasan[v] ? ` (${data.ringkasan[v]})` : '')"></button>
                </template>
            </div>

            <template x-if="!data"><x-memuat /></template>
            <template x-if="data && data.opname.length === 0">
                <x-kosong ikon="opname" judul="Belum ada sesi opname" teks="Mulai opname untuk mencocokkan stok fisik di rak dengan stok di sistem." />
            </template>
            <template x-if="data && data.opname.length > 0">
                <ul class="divide-y divide-slate-100">
                    <template x-for="o in data.opname" :key="o.id">
                        <li>
                            <a :href="`/opname/${o.id}`" class="flex flex-col sm:flex-row sm:items-center gap-2 px-4 py-3 hover:bg-slate-50">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-semibold text-slate-900" x-text="o.kode"></span>
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            :class="WARNA[STATUS[o.status][1]]" x-text="STATUS[o.status][0]"></span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5"
                                        x-text="`Dibuat ${TK.waktu(o.dibuat_pada)} oleh ${o.dibuat_oleh ?? '-'}` + (o.diputuskan_oleh ? ` · diputuskan ${o.diputuskan_oleh}` : '') + (o.catatan ? ` · ${o.catatan}` : '')"></p>
                                    <template x-if="o.status === 'ditolak' && o.alasan_tolak">
                                        <p class="text-xs text-rose-700 mt-0.5" x-text="`Alasan ditolak: ${o.alasan_tolak}`"></p>
                                    </template>
                                </div>
                                <div class="text-xs text-slate-600 sm:text-right">
                                    <p x-text="`${o.sudah_dihitung}/${o.jumlah_barang} dihitung`"></p>
                                    <p :class="o.barang_selisih ? 'text-amber-700 font-semibold' : ''" x-text="`${o.barang_selisih} barang selisih`"></p>
                                </div>
                            </a>
                        </li>
                    </template>
                </ul>
            </template>
        </div>

        <x-modal buka="baru" judul="Mulai Sesi Opname" lebar="max-w-md">
            <form id="form-opname" @submit.prevent="mulai" class="space-y-4">
                <p class="text-sm text-slate-600">Stok sistem setiap barang akan dicatat sekarang sebagai pembanding hitungan fisik.</p>
                <div>
                    <label for="f-kategori" class="label">Barang yang dihitung</label>
                    <select id="f-kategori" class="input" x-model="kat">
                        <option value="">Semua barang</option>
                        @foreach ($kategori as $k)
                            <option value="{{ $k->id }}">Kategori: {{ $k->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-catatan" class="label">Catatan (opsional)</label>
                    <input id="f-catatan" class="input" x-model="catatan" maxlength="200" placeholder="cth. Opname akhir bulan rak depan">
                </div>
            </form>
            <x-slot:kaki>
                <button type="button" class="btn btn-sekunder" @click="baru = false">Batal</button>
                <button type="submit" form="form-opname" class="btn btn-utama" :disabled="proses">
                    <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses" x-cloak /> Mulai
                </button>
            </x-slot:kaki>
        </x-modal>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('daftarOpname', () => ({
                    STATUS: @js(\App\Http\Controllers\OpnameController::LABEL),
                    WARNA: {
                        hijau: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        merah: 'bg-rose-50 text-rose-700 border-rose-200',
                        kuning: 'bg-amber-50 text-amber-800 border-amber-200',
                        abu: 'bg-slate-100 text-slate-600 border-slate-200',
                    },
                    tab: [['', 'Semua'], ['draft', 'Draft'], ['diajukan', 'Menunggu'], ['disetujui', 'Disetujui'], ['ditolak', 'Ditolak']],
                    filter: '',
                    data: null,
                    baru: false,
                    kat: '',
                    catatan: '',
                    proses: false,

                    init() {
                        this.muat();
                    },
                    pilihFilter(v) {
                        this.filter = v;
                        this.muat();
                    },
                    async muat() {
                        try {
                            this.data = (await TK.panggil(`/api/opname${this.filter ? `?status=${this.filter}` : ''}`)).data;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },
                    async mulai() {
                        this.proses = true;
                        try {
                            const r = await TK.panggil('/api/opname', { body: { kategori_id: this.kat || null, catatan: this.catatan } });
                            window.location.href = `/opname/${r.data.id}`;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                            this.proses = false;
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
