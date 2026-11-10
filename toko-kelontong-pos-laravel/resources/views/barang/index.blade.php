{{-- Port app/(app)/barang/klien.tsx — Barang & Stok. Data dimuat via TK.panggil seperti sumber. --}}
<x-layouts.app judul="Barang & Stok">
    <div x-data="halamanBarang(@js($pemilik))">
        <x-kepala-halaman judul="Barang & Stok" sub="Kelola barang dagangan, catat barang masuk/keluar, dan pantau stok menipis.">
            @if ($pemilik)
                <x-slot:aksi>
                    <button type="button" class="btn btn-sekunder" @click="kelolaKat = true">
                        <x-ikon nama="tag" /> Kategori
                    </button>
                    <button type="button" class="btn btn-utama" @click="bukaTambah()">
                        <x-ikon nama="tambah" /> Tambah Barang
                    </button>
                </x-slot:aksi>
            @endif
        </x-kepala-halaman>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <x-statistik label="Jenis barang aktif" ikon="paket"><span x-text="TK.angka(ringkas?.total ?? 0)">0</span></x-statistik>
            <button type="button" class="text-left" @click="filterStok = filterStok === 'menipis' ? '' : 'menipis'">
                <x-statistik label="Stok menipis" ikon="peringatan" warna="amber">
                    <span x-text="TK.angka(ringkas?.menipis ?? 0)">0</span>
                    <x-slot:sub>Klik untuk menyaring</x-slot:sub>
                </x-statistik>
            </button>
            <button type="button" class="text-left" @click="filterStok = filterStok === 'habis' ? '' : 'habis'">
                <x-statistik label="Stok habis" ikon="kotak-masuk" warna="rose">
                    <span x-text="TK.angka(ringkas?.habis ?? 0)">0</span>
                    <x-slot:sub>Klik untuk menyaring</x-slot:sub>
                </x-statistik>
            </button>
            @if ($pemilik)
                <x-statistik label="Nilai stok (harga modal)" ikon="dompet" warna="sky"><span x-text="TK.rupiah(ringkas?.nilai_modal ?? 0)">Rp0</span></x-statistik>
            @endif
        </div>

        <div class="kartu">
            <div class="p-3 border-b border-slate-100 grid sm:grid-cols-[1fr_180px_160px] gap-2">
                <div class="relative">
                    <x-ikon nama="cari" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input type="search" class="input pl-9" placeholder="Cari nama atau kode barang" x-model="cari">
                </div>
                <select class="input" x-model="filterKat" aria-label="Saring kategori">
                    <option value="">Semua kategori</option>
                    <template x-for="k in kategori" :key="k.id">
                        <option :value="String(k.id)" x-text="k.nama"></option>
                    </template>
                </select>
                <select class="input" x-model="filterStok" aria-label="Saring stok">
                    <option value="">Semua stok</option>
                    <option value="menipis">Stok menipis</option>
                    <option value="habis">Stok habis</option>
                </select>
            </div>

            <template x-if="memuat">
                <x-memuat />
            </template>
            <template x-if="!memuat && data.length === 0">
                <div class="flex flex-col items-center justify-center px-4 py-12 text-center">
                    <div class="mb-3 text-slate-300"><x-ikon nama="paket" class="h-10 w-10" /></div>
                    <p class="font-semibold text-slate-700">Tidak ada barang</p>
                    <p class="mt-1 max-w-sm text-sm text-slate-500"
                        x-text="cari || filterKat || filterStok ? 'Coba ubah pencarian atau saringan.' : 'Tambahkan barang pertama Anda.'"></p>
                </div>
            </template>
            <template x-if="!memuat && data.length > 0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500 text-left">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Barang</th>
                                <th class="px-4 py-2.5 font-semibold hidden md:table-cell">Kategori</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Harga jual</th>
                                @if ($pemilik)
                                    <th class="px-4 py-2.5 font-semibold text-right hidden lg:table-cell">Modal</th>
                                @endif
                                <th class="px-4 py-2.5 font-semibold text-right">Stok</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="b in data" :key="b.id">
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center overflow-hidden shrink-0">
                                                <template x-if="b.foto_url">
                                                    <img :src="b.foto_url" alt="" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!b.foto_url">
                                                    <x-ikon nama="paket" class="w-4 h-4 text-slate-300" />
                                                </template>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-slate-900" x-text="b.nama_barang"></p>
                                                <p class="text-[11px] text-slate-400 font-mono">
                                                    <span x-text="b.kode_barang"></span>
                                                    <span x-show="!b.tampil_di_katalog" class="ml-2 font-sans text-slate-400">· tidak di katalog</span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600 hidden md:table-cell" x-text="b.kategori ?? '-'"></td>
                                    <td class="px-4 py-2.5 text-right font-semibold tabular-nums" x-text="TK.rupiah(b.harga_jual)"></td>
                                    @if ($pemilik)
                                        <td class="px-4 py-2.5 text-right text-slate-500 tabular-nums hidden lg:table-cell" x-text="TK.rupiah(b.harga_modal ?? 0)"></td>
                                    @endif
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                        <template x-if="b.stok === 0">
                                            <x-lencana warna="merah">Habis</x-lencana>
                                        </template>
                                        <template x-if="b.stok > 0 && b.stok <= b.stok_minimum">
                                            <x-lencana warna="kuning"><x-ikon nama="peringatan" class="w-3 h-3" /> <span x-text="b.stok + ' ' + b.satuan"></span></x-lencana>
                                        </template>
                                        <template x-if="b.stok > b.stok_minimum">
                                            <span class="tabular-nums"><span x-text="b.stok"></span> <span class="text-slate-400 text-xs" x-text="b.satuan"></span></span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex justify-end gap-0.5">
                                            <button type="button" title="Stok masuk" aria-label="Stok masuk" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500" @click="bukaMutasi(b, 'masuk')">
                                                <x-ikon nama="unduh" class="w-4 h-4 text-emerald-600" />
                                            </button>
                                            <button type="button" title="Stok keluar" aria-label="Stok keluar" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500" @click="bukaMutasi(b, 'keluar')">
                                                <x-ikon nama="unggah" class="w-4 h-4 text-rose-600" />
                                            </button>
                                            <button type="button" title="Riwayat stok" aria-label="Riwayat stok" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500" @click="bukaRiwayat(b)">
                                                <x-ikon nama="riwayat" class="w-4 h-4" />
                                            </button>
                                            @if ($pemilik)
                                                <button type="button" title="Ubah" aria-label="Ubah" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500" @click="bukaEdit(b)">
                                                    <x-ikon nama="ubah" class="w-4 h-4" />
                                                </button>
                                                <button type="button" title="Hapus" aria-label="Hapus" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500" @click="hapus = b; hapusBuka = true">
                                                    <x-ikon nama="hapus" class="w-4 h-4 text-rose-500" />
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        @if ($pemilik)
            {{-- Form tambah/ubah --}}
            <x-modal buka="formBuka" judul="Tambah Barang" judul-teks="editId ? 'Ubah Barang' : 'Tambah Barang'" lebar="max-w-2xl">
                <form id="form-barang" @submit.prevent="kirimForm()" class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="b-nama" class="label">Nama barang<span class="text-rose-500"> *</span></label>
                        <input id="b-nama" class="input" required maxlength="150" x-model="form.nama_barang" x-ref="namaBarang">
                    </div>
                    <div>
                        <label for="b-kode" class="label">Kode barang / barcode<span class="text-rose-500"> *</span></label>
                        <input id="b-kode" class="input font-mono" required maxlength="50" x-model="form.kode_barang">
                    </div>
                    <div>
                        <label for="b-kategori" class="label">Kategori</label>
                        <select id="b-kategori" class="input" x-model="form.kategori_id">
                            <option value="">Tanpa kategori</option>
                            <template x-for="k in kategori" :key="k.id">
                                <option :value="String(k.id)" x-text="k.nama" :selected="String(k.id) === form.kategori_id"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label for="b-satuan" class="label">Satuan</label>
                        <input id="b-satuan" class="input" maxlength="20" x-model="form.satuan">
                        <p class="mt-1 text-[11px] text-slate-500">cth. pcs, bks, kg, botol</p>
                    </div>
                    <div>
                        <label class="label">Harga jual (Rp)<span class="text-rose-500"> *</span></label>
                        <x-input-uang model="form.harga_jual" required />
                    </div>
                    <div>
                        <label class="label">Harga modal (Rp)</label>
                        <x-input-uang model="form.harga_modal" />
                        <p class="mt-1 text-[11px] text-slate-500">Dipakai untuk menghitung laba kotor.</p>
                    </div>
                    <template x-if="!editId">
                        <div>
                            <label for="b-stok" class="label">Stok awal</label>
                            <input id="b-stok" type="number" min="0" class="input" x-model="form.stok">
                        </div>
                    </template>
                    <template x-if="editId">
                        <div>
                            <label class="label">Stok saat ini</label>
                            <input class="input" disabled :value="form.stok">
                            <p class="mt-1 text-[11px] text-slate-500">Ubah stok lewat tombol Stok Masuk/Keluar atau Stok Opname.</p>
                        </div>
                    </template>
                    <div>
                        <label for="b-min" class="label">Batas stok menipis</label>
                        <input id="b-min" type="number" min="0" class="input" x-model="form.stok_minimum">
                        <p class="mt-1 text-[11px] text-slate-500">Peringatan muncul jika stok ≤ angka ini.</p>
                    </div>
                    <div>
                        <span class="label">Foto barang (opsional)</span>
                        <label class="flex items-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 cursor-pointer hover:border-emerald-400">
                            <x-ikon nama="gambar" /> <span class="truncate" x-text="foto ? foto.name : 'Pilih gambar'"></span>
                            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
                                @change="foto = $event.target.files[0] ?? null; $event.target.value = ''">
                        </label>
                        <p class="mt-1 text-[11px] text-slate-500">JPG/PNG/WEBP, maks. 2 MB.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700 self-end pb-2">
                        <input type="checkbox" x-model="form.tampil_di_katalog" class="w-4 h-4 accent-emerald-600">
                        Tampilkan di katalog publik
                    </label>
                </form>
                <x-slot:kaki>
                    <button type="button" class="btn btn-sekunder" @click="formBuka = false">Batal</button>
                    <button type="submit" form="form-barang" class="btn btn-utama" :disabled="simpan">
                        <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="simpan" x-cloak /> Simpan
                    </button>
                </x-slot:kaki>
            </x-modal>

            {{-- Hapus --}}
            <x-modal buka="hapusBuka" judul="Hapus barang?" lebar="max-w-sm">
                <p class="text-sm text-slate-700">
                    <b x-text="hapus?.nama_barang"></b> akan dihapus dari daftar. Jika barang ini pernah terjual, barang hanya dinonaktifkan agar riwayat transaksi dan laporan tetap utuh.
                </p>
                <x-slot:kaki>
                    <button type="button" class="btn btn-sekunder" @click="hapusBuka = false">Batal</button>
                    <button type="button" class="btn btn-bahaya" :disabled="simpan" @click="konfirmasiHapus()">
                        <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="simpan" x-cloak /> Hapus
                    </button>
                </x-slot:kaki>
            </x-modal>

            {{-- Kategori --}}
            <x-modal buka="kelolaKat" judul="Kategori Barang" lebar="max-w-md">
                <form @submit.prevent="tambahKategori()" class="flex gap-2 mb-4">
                    <input class="input" placeholder="Nama kategori baru" x-model="katBaru" required maxlength="40">
                    <button type="submit" class="btn btn-utama">Tambah</button>
                </form>
                <ul class="divide-y divide-slate-100">
                    <template x-for="k in kategori" :key="k.id">
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span><span x-text="k.nama"></span> <span class="text-xs text-slate-400" x-text="'(' + k.jumlah_barang + ' barang)'"></span></span>
                            <button type="button" @click="hapusKategori(k)" class="text-rose-500 hover:text-rose-700 p-1" :aria-label="'Hapus kategori ' + k.nama">
                                <x-ikon nama="hapus" class="w-4 h-4" />
                            </button>
                        </li>
                    </template>
                </ul>
            </x-modal>
        @endif

        {{-- Stok masuk/keluar --}}
        <x-modal buka="mutasiBuka" judul="Catat Stok Masuk" judul-teks="mutasi.jenis === 'masuk' ? 'Catat Stok Masuk' : 'Catat Stok Keluar'" lebar="max-w-md">
            <form id="form-mutasi" @submit.prevent="kirimMutasi()" class="space-y-4">
                <div class="rounded-lg bg-slate-50 p-3 text-sm">
                    <p class="font-semibold" x-text="mutasi.b?.nama_barang"></p>
                    <p class="text-slate-500">
                        <span x-text="'Stok sekarang: ' + (mutasi.b?.stok ?? 0) + ' ' + (mutasi.b?.satuan ?? '')"></span>
                        <template x-if="Number(jumlah) > 0">
                            <span> → <b :class="mutasi.jenis === 'masuk' ? 'text-emerald-700' : 'text-rose-700'"
                                    x-text="(mutasi.b?.stok ?? 0) + (mutasi.jenis === 'masuk' ? 1 : -1) * Number(jumlah)"></b></span>
                        </template>
                    </p>
                </div>
                <div>
                    <label for="m-jumlah" class="label"><span x-text="'Jumlah (' + (mutasi.b?.satuan ?? '') + ')'"></span><span class="text-rose-500"> *</span></label>
                    <input id="m-jumlah" type="number" min="1" required class="input" x-model="jumlah">
                </div>
                <div>
                    <label for="m-ket" class="label">Keterangan<span class="text-rose-500" x-show="mutasi.jenis === 'keluar'"> *</span></label>
                    <input id="m-ket" class="input" maxlength="200" :required="mutasi.jenis === 'keluar'" x-model="ket">
                    <p class="mt-1 text-[11px] text-slate-500"
                        x-text="mutasi.jenis === 'masuk' ? 'cth. Kiriman dari pemasok' : 'cth. Rusak, kedaluwarsa, dipakai sendiri'"></p>
                </div>
            </form>
            <x-slot:kaki>
                <button type="button" class="btn btn-sekunder" @click="mutasiBuka = false">Batal</button>
                <button type="submit" form="form-mutasi" class="btn" :class="mutasi.jenis === 'keluar' ? 'btn-bahaya' : 'btn-utama'" :disabled="simpan">
                    <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="simpan" x-cloak /> Simpan
                </button>
            </x-slot:kaki>
        </x-modal>

        {{-- Riwayat mutasi --}}
        <x-modal buka="riwayatBuka" judul="Riwayat Stok" judul-teks="'Riwayat Stok — ' + (riwayat.b?.nama_barang ?? '')" lebar="max-w-3xl">
            <template x-if="!riwayat.data">
                <x-memuat />
            </template>
            <template x-if="riwayat.data && riwayat.data.length === 0">
                <x-kosong judul="Belum ada mutasi" />
            </template>
            <template x-if="riwayat.data && riwayat.data.length > 0">
                <div class="overflow-x-auto -mx-5">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-slate-500 text-left bg-slate-50">
                            <tr>
                                <th class="px-5 py-2">Waktu</th>
                                <th class="px-2 py-2">Jenis</th>
                                <th class="px-2 py-2 text-right">Jumlah</th>
                                <th class="px-2 py-2 text-right">Stok</th>
                                <th class="px-5 py-2">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="m in riwayat.data" :key="m.id">
                                <tr>
                                    <td class="px-5 py-2 whitespace-nowrap text-slate-600" x-text="TK.waktu(m.dibuat_pada)"></td>
                                    <td class="px-2 py-2">
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            :class="WARNA_LENCANA[labelJenis(m.jenis)[1]]" x-text="labelJenis(m.jenis)[0]"></span>
                                    </td>
                                    <td class="px-2 py-2 text-right font-semibold tabular-nums" :class="m.jumlah > 0 ? 'text-emerald-700' : 'text-rose-700'"
                                        x-text="(m.jumlah > 0 ? '+' : '') + m.jumlah"></td>
                                    <td class="px-2 py-2 text-right text-slate-500 tabular-nums whitespace-nowrap">
                                        <span x-text="m.stok_sebelum"></span> → <b class="text-slate-800" x-text="m.stok_sesudah"></b>
                                    </td>
                                    <td class="px-5 py-2 text-slate-600">
                                        <span x-text="m.keterangan"></span>
                                        <span x-show="m.referensi" class="text-xs text-slate-400 font-mono" x-text="' · ' + m.referensi"></span>
                                        <span x-show="m.oleh" class="text-xs text-slate-400" x-text="' · ' + m.oleh"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </x-modal>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                const LABEL_JENIS = {
                    awal: ['Stok awal', 'abu'],
                    masuk: ['Masuk', 'hijau'],
                    keluar: ['Keluar', 'merah'],
                    penjualan: ['Penjualan', 'biru'],
                    penyesuaian: ['Opname', 'kuning'],
                };
                const KOSONG = { kode_barang: '', nama_barang: '', satuan: 'pcs', kategori_id: '', harga_jual: 0, harga_modal: 0, stok: 0, stok_minimum: 5, tampil_di_katalog: true };

                Alpine.data('halamanBarang', (pemilik) => ({
                    pemilik,
                    WARNA_LENCANA: {
                        hijau: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        merah: 'bg-rose-50 text-rose-700 border-rose-200',
                        kuning: 'bg-amber-50 text-amber-800 border-amber-200',
                        biru: 'bg-sky-50 text-sky-700 border-sky-200',
                        abu: 'bg-slate-100 text-slate-600 border-slate-200',
                    },
                    data: [],
                    ringkas: null,
                    kategori: [],
                    memuat: true,
                    cari: '',
                    filterKat: '',
                    filterStok: '',

                    formBuka: false,
                    form: { ...KOSONG },
                    editId: null,
                    foto: null,
                    simpan: false,
                    mutasiBuka: false,
                    mutasi: { b: null, jenis: 'masuk' },
                    jumlah: '',
                    ket: '',
                    riwayatBuka: false,
                    riwayat: { b: null, data: null },
                    hapusBuka: false,
                    hapus: null,
                    kelolaKat: false,
                    katBaru: '',
                    _tunda: null,

                    init() {
                        this.muat();
                        this.muatKategori();
                        const tunda = () => {
                            clearTimeout(this._tunda);
                            this._tunda = setTimeout(() => this.muat(), 250);
                        };
                        this.$watch('cari', tunda);
                        this.$watch('filterKat', tunda);
                        this.$watch('filterStok', tunda);
                    },

                    labelJenis(j) {
                        return LABEL_JENIS[j] ?? [j, 'abu'];
                    },

                    async muat() {
                        const p = new URLSearchParams();
                        if (this.cari.trim()) p.set('q', this.cari.trim());
                        if (this.filterKat) p.set('kategori_id', this.filterKat);
                        if (this.filterStok) p.set('filter', this.filterStok);
                        try {
                            const r = await TK.panggil(`/api/barang?${p}`);
                            this.data = r.data.barang;
                            this.ringkas = r.data.ringkasan;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        } finally {
                            this.memuat = false;
                        }
                    },

                    async muatKategori() {
                        try {
                            this.kategori = (await TK.panggil('/api/kategori')).data;
                        } catch {
                            /* abaikan */
                        }
                    },

                    bukaTambah() {
                        this.editId = null;
                        this.foto = null;
                        this.form = { ...KOSONG };
                        this.formBuka = true;
                        this.$nextTick(() => this.$refs.namaBarang?.focus());
                    },

                    bukaEdit(b) {
                        this.editId = b.id;
                        this.foto = null;
                        this.form = {
                            kode_barang: b.kode_barang,
                            nama_barang: b.nama_barang,
                            satuan: b.satuan,
                            kategori_id: b.kategori_id ? String(b.kategori_id) : '',
                            harga_jual: b.harga_jual,
                            harga_modal: b.harga_modal ?? 0,
                            stok: b.stok,
                            stok_minimum: b.stok_minimum,
                            tampil_di_katalog: !!b.tampil_di_katalog,
                        };
                        this.formBuka = true;
                    },

                    async kirimForm() {
                        this.simpan = true;
                        try {
                            let id = this.editId;
                            if (this.editId) {
                                await TK.panggil(`/api/barang/${this.editId}`, { method: 'PUT', body: this.form });
                            } else {
                                id = (await TK.panggil('/api/barang', { body: this.form })).data.id;
                            }
                            if (this.foto && id) {
                                const fd = new FormData();
                                fd.append('berkas', this.foto);
                                try {
                                    await TK.panggil(`/api/barang/${id}/foto`, { form: fd });
                                } catch (err) {
                                    TK.notif(err.message, 'galat');
                                }
                            }
                            TK.notif(this.editId ? 'Data barang diperbarui.' : 'Barang baru ditambahkan.', 'sukses');
                            this.formBuka = false;
                            this.muat();
                            this.muatKategori();
                        } catch (err) {
                            TK.notif(err.message, 'galat');
                        } finally {
                            this.simpan = false;
                        }
                    },

                    bukaMutasi(b, jenis) {
                        this.mutasi = { b, jenis };
                        this.jumlah = '';
                        this.ket = '';
                        this.mutasiBuka = true;
                    },

                    async kirimMutasi() {
                        if (!this.mutasi.b) return;
                        this.simpan = true;
                        try {
                            const r = await TK.panggil(`/api/barang/${this.mutasi.b.id}/stok`, {
                                body: { jenis: this.mutasi.jenis, jumlah: this.jumlah, keterangan: this.ket },
                            });
                            TK.notif(r.pesan ?? 'Tersimpan.', 'sukses');
                            this.mutasiBuka = false;
                            this.muat();
                        } catch (err) {
                            TK.notif(err.message, 'galat');
                        } finally {
                            this.simpan = false;
                        }
                    },

                    async bukaRiwayat(b) {
                        this.riwayat = { b, data: null };
                        this.riwayatBuka = true;
                        try {
                            const r = await TK.panggil(`/api/barang/${b.id}/stok`);
                            this.riwayat = { b, data: r.data };
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                            this.riwayatBuka = false;
                        }
                    },

                    async konfirmasiHapus() {
                        if (!this.hapus) return;
                        this.simpan = true;
                        try {
                            const r = await TK.panggil(`/api/barang/${this.hapus.id}`, { method: 'DELETE' });
                            TK.notif(r.pesan ?? 'Dihapus.', 'sukses');
                            this.hapusBuka = false;
                            this.hapus = null;
                            this.muat();
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        } finally {
                            this.simpan = false;
                        }
                    },

                    async tambahKategori() {
                        try {
                            await TK.panggil('/api/kategori', { body: { nama: this.katBaru } });
                            this.katBaru = '';
                            this.muatKategori();
                        } catch (err) {
                            TK.notif(err.message, 'galat');
                        }
                    },

                    async hapusKategori(k) {
                        try {
                            await TK.panggil(`/api/kategori/${k.id}`, { method: 'DELETE' });
                            this.muatKategori();
                            this.muat();
                        } catch (err) {
                            TK.notif(err.message, 'galat');
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
