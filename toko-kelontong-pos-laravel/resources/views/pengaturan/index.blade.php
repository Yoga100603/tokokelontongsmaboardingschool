{{-- Port app/(app)/pengaturan/klien.tsx — Akun & Pengaturan. --}}
@php
    $pemilik = $pengguna['peran'] === 'pemilik';
    $tabs = array_merge(
        $pemilik ? [['toko', 'Profil Toko', 'store'], ['kasir', 'Akun Kasir', 'pengguna']] : [],
        [['profil', 'Profil Saya', 'pengguna']],
    );
@endphp
<x-layouts.app judul="Akun & Pengaturan">
    <div x-data="{ tab: @js($pemilik ? 'toko' : 'profil') }">
        <x-kepala-halaman judul="Akun & Pengaturan"
            :sub="$pemilik ? 'Atur profil toko yang tampil di katalog & struk, serta akun untuk kasir.' : 'Ubah nama dan kata sandi akun Anda.'" />

        @if ($baru)
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 flex gap-2">
                <x-ikon nama="centang-bulat" class="w-5 h-5 shrink-0" />
                <div>
                    <b>Toko berhasil didaftarkan!</b> Lengkapi profil toko (alamat, telepon, jam buka), buat akun kasir, lalu tambahkan barang di menu Barang &amp; Stok.
                </div>
            </div>
        @endif

        <div class="flex gap-1 mb-5 border-b border-slate-200 overflow-x-auto">
            @foreach ($tabs as [$id, $label, $ikon])
                <button type="button" @click="tab = @js($id)"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px whitespace-nowrap"
                    :class="tab === @js($id) ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-800'">
                    <x-ikon :nama="$ikon" class="w-4 h-4" /> {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($pemilik)
            {{-- ── Profil Toko ─────────────────────────────────────────── --}}
            <template x-if="tab === 'toko'">
                <div x-data="profilToko">
                    <template x-if="!t"><x-memuat /></template>
                    <template x-if="t">
                        <form @submit.prevent="simpan" class="grid lg:grid-cols-[1fr_300px] gap-5">
                            <div class="kartu p-5 grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Nama toko<span class="text-rose-500"> *</span></label>
                                    <input class="input" required minlength="3" x-model="t.nama">
                                </div>
                                <div>
                                    <label class="label">Alamat katalog<span class="text-rose-500"> *</span></label>
                                    <input class="input" required x-model="t.slug" @blur="t.slug = buatSlug(t.slug || t.nama)">
                                    <p class="mt-1 text-[11px] text-slate-500" x-text="'Katalog: /toko/' + buatSlug(t.slug || t.nama)"></p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Alamat toko</label>
                                    <input class="input" x-model="t.alamat" placeholder="Jl. ...">
                                </div>
                                <div>
                                    <label class="label">Nomor telepon / WhatsApp</label>
                                    <input class="input" x-model="t.telepon" inputmode="tel" placeholder="08...">
                                    <p class="mt-1 text-[11px] text-slate-500">Dipakai untuk tombol Hubungi di katalog.</p>
                                </div>
                                <div>
                                    <label class="label">Email toko</label>
                                    <input class="input" type="email" x-model="t.email">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Jam buka</label>
                                    <input class="input" x-model="t.jam_buka" placeholder="cth. Senin–Sabtu 07.00–21.00">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Deskripsi toko</label>
                                    <textarea class="input" rows="3" maxlength="500" x-model="t.deskripsi"></textarea>
                                    <p class="mt-1 text-[11px] text-slate-500">Tampil di halaman katalog publik.</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="label">Catatan kaki struk</label>
                                    <input class="input" maxlength="200" x-model="t.catatan_struk">
                                </div>
                                <label class="sm:col-span-2 flex items-center gap-2 text-sm">
                                    <input type="checkbox" class="w-4 h-4 accent-emerald-600" :checked="!!t.katalog_aktif"
                                        @change="t.katalog_aktif = $event.target.checked ? 1 : 0">
                                    Aktifkan halaman katalog publik
                                </label>
                                <div class="sm:col-span-2">
                                    <button type="submit" class="btn btn-utama" :disabled="proses">
                                        <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses" x-cloak />
                                        Simpan profil toko
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-5">
                                <div class="kartu p-5 text-center">
                                    <p class="text-sm font-semibold text-slate-700 mb-3">Logo toko</p>
                                    <div class="w-24 h-24 mx-auto rounded-2xl bg-slate-100 overflow-hidden flex items-center justify-center">
                                        <template x-if="logoAman(t.logo_url)">
                                            <img :src="t.logo_url" alt="Logo toko" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!logoAman(t.logo_url)">
                                            <x-ikon nama="store" class="w-8 h-8 text-slate-300" />
                                        </template>
                                    </div>
                                    <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 cursor-pointer hover:underline">
                                        <x-ikon nama="gambar" class="w-4 h-4" /> Ganti logo
                                        <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden"
                                            @change="unggahLogo($event.target.files[0]); $event.target.value = ''">
                                    </label>
                                    <p class="text-[11px] text-slate-400 mt-1">JPG/PNG/WEBP, maks. 2 MB</p>
                                </div>
                                <div class="kartu p-5">
                                    <p class="text-sm font-semibold text-slate-700">Katalog publik</p>
                                    <p class="text-xs text-slate-500 mt-1">Bagikan tautan ini ke pembeli.</p>
                                    <a :href="'/toko/' + t.slug" target="_blank" rel="noreferrer"
                                        class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-emerald-700 hover:underline break-all">
                                        <span x-text="'/toko/' + t.slug"></span> <x-ikon nama="tautan-luar" class="w-3.5 h-3.5" />
                                    </a>
                                </div>
                            </div>
                        </form>
                    </template>
                </div>
            </template>

            {{-- ── Akun Kasir ──────────────────────────────────────────── --}}
            <template x-if="tab === 'kasir'">
                <div x-data="akunKasir" class="kartu">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                        <p class="font-bold text-slate-900">Akun di toko ini</p>
                        <button type="button" class="btn btn-utama btn-kecil" @click="bukaForm()">
                            <x-ikon nama="tambah" class="w-4 h-4" /> Tambah Kasir
                        </button>
                    </div>
                    <template x-if="!akun"><x-memuat /></template>
                    <template x-if="akun">
                        <ul class="divide-y divide-slate-100">
                            <template x-for="a in akun" :key="a.id">
                                <li class="flex items-center gap-3 px-4 py-3">
                                    <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center font-bold text-slate-600 shrink-0"
                                        x-text="a.nama.slice(0, 1).toUpperCase()"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-slate-900 flex items-center gap-2">
                                            <span x-text="a.nama"></span>
                                            <span x-show="a.peran === 'pemilik'"><x-lencana warna="biru">pemilik</x-lencana></span>
                                            <span x-show="a.peran !== 'pemilik'"><x-lencana warna="abu"><span x-text="a.peran"></span></x-lencana></span>
                                            <span x-show="!a.aktif"><x-lencana warna="merah">nonaktif</x-lencana></span>
                                        </p>
                                        <p class="text-xs text-slate-500 truncate"
                                            x-text="a.email + ' · terakhir masuk ' + (a.terakhir_masuk ? TK.waktu(a.terakhir_masuk) : 'belum pernah')"></p>
                                    </div>
                                    <template x-if="a.peran === 'kasir'">
                                        <button type="button" class="btn btn-sekunder btn-kecil" @click="bukaForm(a)">Ubah</button>
                                    </template>
                                </li>
                            </template>
                        </ul>
                    </template>

                    <x-modal buka="formBuka" judul="Tambah Akun Kasir" judul-teks="form.id ? 'Ubah Akun Kasir' : 'Tambah Akun Kasir'" lebar="max-w-md">
                        <form id="form-kasir" @submit.prevent="simpan" class="space-y-4">
                            <div>
                                <label class="label">Nama<span class="text-rose-500"> *</span></label>
                                <input class="input" required x-model="form.nama" x-ref="namaKasir">
                            </div>
                            <div>
                                <label class="label">Email (untuk masuk)<span class="text-rose-500"> *</span></label>
                                <input class="input" type="email" required :disabled="!!form.id" x-model="form.email">
                            </div>
                            <div>
                                <label class="label">
                                    <span x-text="form.id ? 'Atur ulang kata sandi' : 'Kata sandi'"></span><span x-show="!form.id" class="text-rose-500"> *</span>
                                </label>
                                <input class="input" type="text" :required="!form.id" minlength="8" x-model="form.kata_sandi" autocomplete="new-password">
                                <p class="mt-1 text-[11px] text-slate-500"
                                    x-text="form.id ? 'Kosongkan jika tidak diubah. Minimal 8 karakter.' : 'Minimal 8 karakter. Berikan ke kasir.'"></p>
                            </div>
                            <template x-if="form.id">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" class="w-4 h-4 accent-emerald-600" x-model="form.aktif">
                                    Akun aktif (nonaktifkan untuk mencabut akses kasir)
                                </label>
                            </template>
                        </form>
                        <x-slot:kaki>
                            <button type="button" class="btn btn-sekunder" @click="formBuka = false">Batal</button>
                            <button type="submit" form="form-kasir" class="btn btn-utama" :disabled="proses">
                                <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses" x-cloak />
                                Simpan
                            </button>
                        </x-slot:kaki>
                    </x-modal>
                </div>
            </template>
        @endif

        {{-- ── Profil Saya ─────────────────────────────────────────────── --}}
        <template x-if="tab === 'profil'">
            <div x-data="profilSaya" class="kartu p-5 max-w-lg">
                <form @submit.prevent="simpan" class="space-y-4">
                    <div>
                        <label class="label">Nama<span class="text-rose-500"> *</span></label>
                        <input class="input" required x-model="nama">
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input class="input" disabled value="{{ $pengguna['email'] }}">
                    </div>
                    <div class="pt-2 border-t border-slate-100">
                        <p class="text-sm font-semibold text-slate-700 flex items-center gap-2 mb-3">
                            <x-ikon nama="kunci" class="w-4 h-4" /> Ganti kata sandi (opsional)
                        </p>
                        <div class="space-y-3">
                            <input class="input" type="password" placeholder="Kata sandi lama" x-model="lama" autocomplete="current-password" :required="!!baru">
                            <input class="input" type="password" placeholder="Kata sandi baru (min. 8 karakter)" minlength="8" x-model="baru" autocomplete="new-password">
                            <input class="input" type="password" placeholder="Ulangi kata sandi baru" x-model="ulang" autocomplete="new-password" :required="!!baru">
                        </div>
                        <p class="text-[11px] text-slate-500 mt-2">Setelah ganti sandi, sesi di perangkat lain otomatis keluar.</p>
                    </div>
                    <button type="submit" class="btn btn-utama" :disabled="proses">
                        <x-ikon nama="memuat" class="w-4 h-4 animate-spin" x-show="proses" x-cloak />
                        Simpan
                    </button>
                </form>
            </div>
        </template>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                /** Setara buatSlug() di lib/utils.ts. */
                const buatSlug = (s) =>
                    String(s || '').toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
                        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50) || 'toko';

                Alpine.data('profilToko', () => ({
                    t: null,
                    proses: false,
                    buatSlug,
                    async init() {
                        try {
                            this.t = (await TK.panggil('/api/toko')).data;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },
                    logoAman(url) {
                        return /^\/api\/berkas\/[a-z0-9-]+\.(jpg|png|webp|gif)$/.test(url || '');
                    },
                    async simpan() {
                        this.proses = true;
                        try {
                            const r = await TK.panggil('/api/toko', { method: 'PUT', body: this.t });
                            this.t = r.data;
                            TK.notif('Profil toko disimpan.', 'sukses');
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        } finally {
                            this.proses = false;
                        }
                    },
                    async unggahLogo(f) {
                        if (!f) return;
                        const fd = new FormData();
                        fd.append('berkas', f);
                        try {
                            const r = await TK.panggil('/api/toko/logo', { form: fd });
                            this.t.logo_url = r.data.logo_url;
                            TK.notif('Logo diperbarui.', 'sukses');
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },
                }));

                Alpine.data('akunKasir', () => ({
                    akun: null,
                    form: { nama: '', email: '', kata_sandi: '', aktif: true },
                    formBuka: false,
                    proses: false,
                    init() {
                        this.muat();
                    },
                    async muat() {
                        try {
                            this.akun = (await TK.panggil('/api/pengguna')).data;
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        }
                    },
                    bukaForm(a = null) {
                        this.form = a
                            ? { id: a.id, nama: a.nama, email: a.email, kata_sandi: '', aktif: !!a.aktif }
                            : { nama: '', email: '', kata_sandi: '', aktif: true };
                        this.formBuka = true;
                        this.$nextTick(() => this.$refs.namaKasir?.focus());
                    },
                    async simpan() {
                        this.proses = true;
                        try {
                            const r = this.form.id
                                ? await TK.panggil(`/api/pengguna/${this.form.id}`, { method: 'PUT', body: this.form })
                                : await TK.panggil('/api/pengguna', { body: this.form });
                            TK.notif(r.pesan ?? 'Tersimpan.', 'sukses');
                            this.formBuka = false;
                            this.muat();
                        } catch (e) {
                            TK.notif(e.message, 'galat');
                        } finally {
                            this.proses = false;
                        }
                    },
                }));

                Alpine.data('profilSaya', () => ({
                    nama: @js($pengguna['nama']),
                    lama: '',
                    baru: '',
                    ulang: '',
                    proses: false,
                    async simpan() {
                        if (this.baru && this.baru !== this.ulang) return TK.notif('Konfirmasi kata sandi baru tidak sama.', 'galat');
                        this.proses = true;
                        try {
                            const r = await TK.panggil('/api/pengguna/saya', {
                                method: 'PUT',
                                body: { nama: this.nama, sandi_lama: this.lama, sandi_baru: this.baru },
                            });
                            TK.notif(r.pesan ?? 'Tersimpan.', 'sukses');
                            this.lama = this.baru = this.ulang = '';
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
