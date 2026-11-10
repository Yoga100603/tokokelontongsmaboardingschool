{{-- Lembar hitung satu sesi opname (port app/(app)/opname/[id]/klien.tsx). --}}
<x-layouts.app judul="Lembar Opname">
    <div x-data="lembarOpname(@js($id), @js($pemilik))">
        <template x-if="galat">
            <div class="kartu"><x-kosong judul="Sesi tidak ditemukan"><span class="text-sm text-slate-500" x-text="galat"></span></x-kosong></div>
        </template>
        <template x-if="!galat && !s"><x-memuat /></template>

        <template x-if="s">
            <div>
                <a href="{{ route('opname') }}" class="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-emerald-700 mb-4">
                    <x-ikon nama="kiri" class="w-4 h-4" /> Semua sesi opname
                </a>
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold font-mono" x-text="s.kode"></h1>
                            <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                :class="WARNA[STATUS[s.status][1]]" x-text="STATUS[s.status][0]"></span>
                        </div>
                        <p class="text-sm text-slate-500 mt-1"
                            x-text="`Dibuat ${TK.waktu(s.dibuat_pada)} oleh ${s.dibuat_oleh_nama ?? '-'}` + (s.diajukan_pada ? ` · diajukan ${TK.waktu(s.diajukan_pada)}` : '') + (s.diputuskan_pada ? ` · diputuskan ${TK.waktu(s.diputuskan_pada)} oleh ${s.diputuskan_oleh_nama ?? '-'}` : '')"></p>
                        <template x-if="s.catatan"><p class="text-sm text-slate-600 mt-1" x-text="s.catatan"></p></template>
                        <template x-if="s.status === 'ditolak'"><p class="text-sm text-rose-700 mt-1" x-text="`Alasan ditolak: ${s.alasan_tolak}`"></p></template>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-if="draft">
                            <div class="contents">
                                <button type="button" class="btn btn-hantu" @click="konfirm = 'batal'">
                                    <x-ikon nama="hapus" class="w-4 h-4" /> Batalkan
                                </button>
                                <button type="button" class="btn btn-sekunder" @click="simpan()" :disabled="!berubah || proses === 'simpan'">
                                    <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses === 'simpan'" x-cloak />
                                    <x-ikon nama="simpan" class="w-4 h-4" x-show="proses !== 'simpan'" /> Simpan
                                </button>
                                <button type="button" class="btn btn-utama" @click="konfirm = 'ajukan'" :disabled="!!proses">
                                    <x-ikon nama="kirim" class="w-4 h-4" /> Ajukan ke Pemilik
                                </button>
                            </div>
                        </template>
                        <template x-if="s.status === 'diajukan' && pemilik">
                            <div class="contents">
                                <button type="button" class="btn btn-bahaya" @click="tolak = true">
                                    <x-ikon nama="x" class="w-4 h-4" /> Tolak
                                </button>
                                <button type="button" class="btn btn-utama" @click="konfirm = 'setujui'">
                                    <x-ikon nama="centang-bulat" class="w-4 h-4" /> Setujui &amp; Sesuaikan Stok
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
                <template x-if="s.status === 'diajukan' && !pemilik">
                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Menunggu persetujuan pemilik. Stok belum berubah.</div>
                </template>

                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="kartu p-3">
                        <p class="text-xs text-slate-500">Sudah dihitung</p>
                        <p class="text-lg font-bold" x-text="`${statistik.hitung}/${s.item.length}`"></p>
                    </div>
                    <div class="kartu p-3">
                        <p class="text-xs text-slate-500">Barang selisih</p>
                        <p class="text-lg font-bold" :class="statistik.selisih && 'text-amber-700'" x-text="statistik.selisih"></p>
                    </div>
                    <div class="kartu p-3">
                        <p class="text-xs text-slate-500">Selisih tanpa alasan</p>
                        <p class="text-lg font-bold" :class="statistik.tanpaAlasan && 'text-rose-700'" x-text="statistik.tanpaAlasan"></p>
                    </div>
                </div>

                <div class="kartu">
                    <div class="p-3 border-b border-slate-100 flex flex-col sm:flex-row gap-2 sm:items-center">
                        <div class="relative flex-1">
                            <x-ikon nama="cari" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <input class="input pl-9" placeholder="Cari barang" x-model="cari">
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" x-model="hanyaSelisih" class="w-4 h-4 accent-emerald-600">
                            Hanya yang selisih
                        </label>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-xs text-slate-500 text-left">
                                <tr>
                                    <th class="px-4 py-2">Barang</th>
                                    <th class="px-3 py-2 text-right">Stok sistem</th>
                                    <th class="px-3 py-2 text-right w-28">Stok fisik</th>
                                    <th class="px-3 py-2 text-right">Selisih</th>
                                    <th class="px-4 py-2 min-w-[200px]">Alasan selisih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="i in tampil" :key="i.barang_id">
                                    <tr :class="selisihBaris(i) ? 'bg-amber-50/40' : ''">
                                        <td class="px-4 py-2">
                                            <p class="font-medium text-slate-900" x-text="i.nama_barang"></p>
                                            <p class="text-[11px] text-slate-400">
                                                <span class="font-mono" x-text="i.kode_barang"></span>
                                                <span x-text="` · ${i.kategori ?? 'Tanpa kategori'}` + (!draft && s.status === 'diajukan' && i.stok_sekarang !== i.stok_sistem ? ` · stok kini ${i.stok_sekarang}` : '')"></span>
                                            </p>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                            <span x-text="i.stok_sistem"></span> <span class="text-xs text-slate-400" x-text="i.satuan"></span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <template x-if="draft">
                                                <input type="number" min="0" inputmode="numeric" class="input text-right py-1.5"
                                                    :value="isian[i.barang_id]?.fisik ?? ''" @input="isi(i.barang_id, 'fisik', $event.target.value)"
                                                    :aria-label="`Stok fisik ${i.nama_barang}`">
                                            </template>
                                            <template x-if="!draft">
                                                <span class="tabular-nums font-semibold" x-text="i.stok_fisik ?? '-'"></span>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2 text-right font-bold tabular-nums"
                                            :class="{ 'text-emerald-700': selisihBaris(i) > 0, 'text-rose-700': selisihBaris(i) < 0 }"
                                            x-text="teksSelisih(selisihBaris(i))"></td>
                                        <td class="px-4 py-2">
                                            <template x-if="draft && selisihBaris(i)">
                                                <input class="input py-1.5" maxlength="200" placeholder="Wajib: cth. rusak, hilang, salah catat"
                                                    :class="!(isian[i.barang_id]?.alasan ?? '').trim() && 'border-amber-400'"
                                                    :value="isian[i.barang_id]?.alasan ?? ''" @input="isi(i.barang_id, 'alasan', $event.target.value)">
                                            </template>
                                            <template x-if="draft && !selisihBaris(i)">
                                                <span class="text-xs text-slate-400">—</span>
                                            </template>
                                            <template x-if="!draft">
                                                <span class="text-slate-600" x-text="i.alasan || '—'"></span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <template x-if="tampil.length === 0"><x-kosong judul="Tidak ada barang" /></template>
                    </div>
                </div>
            </div>
        </template>

        {{-- Konfirmasi ajukan / setujui / batal --}}
        <x-modal buka="konfirmBuka" judul="Konfirmasi" judul-teks="judulKonfirm" lebar="max-w-md">
            <p class="text-sm text-slate-700" x-text="teksKonfirm"></p>
            <x-slot:kaki>
                <button type="button" class="btn btn-sekunder" @click="konfirm = ''" :disabled="!!proses">Kembali</button>
                <template x-if="konfirm === 'batal'">
                    <button type="button" class="btn btn-bahaya" @click="batalkan()" :disabled="proses === 'batal'">
                        <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses === 'batal'" x-cloak /> Batalkan sesi
                    </button>
                </template>
                <template x-if="konfirm && konfirm !== 'batal'">
                    <button type="button" class="btn btn-utama" @click="aksi(konfirm)" :disabled="!!proses">
                        <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses === konfirm || proses === 'simpan'" x-cloak />
                        <span x-text="konfirm === 'ajukan' ? 'Ajukan' : 'Setujui'"></span>
                    </button>
                </template>
            </x-slot:kaki>
        </x-modal>

        {{-- Tolak (wajib alasan) --}}
        <x-modal buka="tolak" judul="Tolak hasil opname" lebar="max-w-md">
            <p class="text-sm text-slate-600 mb-3">Stok tidak akan berubah. Kasir dapat memulai sesi opname baru.</p>
            <textarea rows="3" class="input" placeholder="Alasan penolakan (wajib)" x-model="alasanTolak" maxlength="200"></textarea>
            <x-slot:kaki>
                <button type="button" class="btn btn-sekunder" @click="tolak = false">Batal</button>
                <button type="button" class="btn btn-bahaya" @click="aksi('tolak')" :disabled="!alasanTolak.trim() || proses === 'tolak'">
                    <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses === 'tolak'" x-cloak /> Tolak
                </button>
            </x-slot:kaki>
        </x-modal>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('lembarOpname', (id, pemilik) => ({
                    STATUS: @js(\App\Http\Controllers\OpnameController::LABEL),
                    WARNA: {
                        hijau: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        merah: 'bg-rose-50 text-rose-700 border-rose-200',
                        kuning: 'bg-amber-50 text-amber-800 border-amber-200',
                        abu: 'bg-slate-100 text-slate-600 border-slate-200',
                    },
                    id,
                    pemilik,
                    s: null,
                    galat: '',
                    isian: {},
                    berubah: false,
                    proses: '',
                    cari: '',
                    hanyaSelisih: false,
                    tolak: false,
                    alasanTolak: '',
                    konfirm: '',

                    init() {
                        this.muat();
                        // peringatan jika keluar halaman dengan hitungan belum disimpan
                        window.addEventListener('beforeunload', (e) => {
                            if (this.berubah) e.preventDefault();
                        });
                    },
                    get konfirmBuka() {
                        return !!this.konfirm;
                    },
                    set konfirmBuka(v) {
                        if (!v && !this.proses) this.konfirm = '';
                    },
                    get draft() {
                        return this.s?.status === 'draft';
                    },
                    async muat() {
                        try {
                            const r = await TK.panggil(`/api/opname/${this.id}`);
                            const awal = {};
                            for (const i of r.data.item) awal[i.barang_id] = { fisik: i.stok_fisik === null ? '' : String(i.stok_fisik), alasan: i.alasan };
                            this.isian = awal;
                            this.s = r.data;
                            this.berubah = false;
                        } catch (e) {
                            this.galat = e.message;
                        }
                    },
                    selisihOf(i) {
                        const v = this.isian[i.barang_id]?.fisik;
                        return v === '' || v === undefined ? null : Number(v) - i.stok_sistem;
                    },
                    selisihBaris(i) {
                        return this.draft ? this.selisihOf(i) : i.selisih;
                    },
                    teksSelisih(x) {
                        return x === null ? '-' : x > 0 ? `+${x}` : String(x);
                    },
                    get tampil() {
                        if (!this.s) return [];
                        const q = this.cari.trim().toLowerCase();
                        return this.s.item.filter((i) => {
                            if (q && !i.nama_barang.toLowerCase().includes(q) && !i.kode_barang.toLowerCase().includes(q)) return false;
                            if (this.hanyaSelisih) {
                                const x = this.selisihBaris(i);
                                return x !== null && x !== 0;
                            }
                            return true;
                        });
                    },
                    get statistik() {
                        let hitung = 0, selisih = 0, tanpaAlasan = 0;
                        if (!this.s) return { hitung, selisih, tanpaAlasan };
                        for (const i of this.s.item) {
                            const x = this.selisihBaris(i);
                            if (x !== null) hitung++;
                            if (x) {
                                selisih++;
                                if (!(this.draft ? this.isian[i.barang_id]?.alasan : i.alasan)?.trim()) tanpaAlasan++;
                            }
                        }
                        return { hitung, selisih, tanpaAlasan };
                    },
                    get judulKonfirm() {
                        return this.konfirm === 'ajukan' ? 'Ajukan hasil opname?' : this.konfirm === 'setujui' ? 'Setujui opname?' : 'Batalkan sesi opname?';
                    },
                    get teksKonfirm() {
                        if (!this.s) return '';
                        if (this.konfirm === 'ajukan')
                            return `Hitungan ${this.statistik.hitung} dari ${this.s.item.length} barang akan dikirim ke pemilik. Setelah diajukan, hitungan tidak bisa diubah. Semua barang harus sudah dihitung dan selisih wajib diberi alasan.`;
                        if (this.konfirm === 'setujui')
                            return `Stok ${this.statistik.selisih} barang akan disesuaikan sesuai selisih hitungan fisik dan dicatat sebagai mutasi penyesuaian. Penjualan yang terjadi setelah penghitungan tetap diperhitungkan.`;
                        if (this.konfirm === 'batal') return 'Sesi draft ini beserta hitungannya akan dihapus. Stok tidak berubah.';
                        return '';
                    },
                    isi(barangId, k, v) {
                        this.isian[barangId] = { ...this.isian[barangId], [k]: v };
                        this.berubah = true;
                    },
                    async simpan(diam = false) {
                        if (!this.s) return false;
                        this.proses = 'simpan';
                        try {
                            await TK.panggil(`/api/opname/${this.id}`, {
                                method: 'PUT',
                                body: {
                                    item: this.s.item.map((i) => ({
                                        barang_id: i.barang_id,
                                        stok_fisik: this.isian[i.barang_id]?.fisik ?? '',
                                        alasan: this.isian[i.barang_id]?.alasan ?? '',
                                    })),
                                },
                            });
                            this.berubah = false;
                            if (!diam) TK.notif('Hitungan tersimpan.', 'sukses');
                            return true;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                            return false;
                        } finally {
                            this.proses = '';
                        }
                    },
                    async aksi(a) {
                        if (a === 'ajukan' && !(await this.simpan(true))) return;
                        this.proses = a;
                        try {
                            const r = await TK.panggil(`/api/opname/${this.id}/${a}`, { body: a === 'tolak' ? { alasan: this.alasanTolak } : {} });
                            TK.notif(r.pesan ?? 'Berhasil.', 'sukses');
                            this.tolak = false;
                            this.proses = '';
                            this.konfirm = '';
                            this.muat();
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        } finally {
                            this.proses = '';
                        }
                    },
                    async batalkan() {
                        this.proses = 'batal';
                        try {
                            await TK.panggil(`/api/opname/${this.id}`, { method: 'DELETE' });
                            this.berubah = false;
                            window.location.href = '/opname';
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                            this.proses = '';
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
