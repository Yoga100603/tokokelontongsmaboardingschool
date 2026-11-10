{{-- Port app/page.tsx — landing page publik. --}}
@php
    $fitur = [
        ['keranjang', 'Layar Kasir', 'Cari atau pindai barang, total dan kembalian dihitung otomatis dalam satu layar.'],
        ['paket', 'Barang & Stok', 'Catat barang masuk/keluar, stok berkurang otomatis saat terjual, ada peringatan stok menipis.'],
        ['struk', 'Struk Transaksi', 'Struk langsung tampil, bisa dicetak di printer biasa atau printer struk, disimpan, atau dikirim via WhatsApp.'],
        ['kalender', 'Penjualan Harian', 'Rekap pemasukan harian dan tombol Tutup Buku untuk mengunci catatan hari itu.'],
        ['grafik', 'Laporan Penjualan', 'Laporan per rentang tanggal, produk terlaris, laba kotor, dan unduh ke Excel.'],
        ['opname', 'Stok Opname', 'Kasir menghitung stok fisik, pemilik menyetujui. Setiap penyesuaian tercatat.'],
        ['tautan-luar', 'Katalog Publik', 'Halaman toko berisi alamat, jam buka, dan daftar barang yang bisa dilihat pembeli tanpa login.'],
        ['pengguna', 'Akun Pemilik & Kasir', 'Setiap toko punya akun sendiri, pemilik bisa membuat akun untuk kasir.'],
    ];
@endphp
<x-layouts.dasar>
    <div class="bg-white">
        <header class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('beranda') }}" class="flex items-center gap-2">
                <span class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white">
                    <x-ikon nama="store" class="w-5 h-5" />
                </span>
                <span class="font-extrabold text-slate-900">TokoKasir</span>
            </a>
            <nav class="flex items-center gap-2">
                @if ($u)
                    <a href="{{ $u->isPemilik() ? route('dasbor') : route('kasir') }}"
                        class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                        Buka {{ $u->toko->nama }}
                    </a>
                @else
                    <a href="{{ route('masuk') }}" class="px-3 py-2 text-sm font-semibold text-slate-700 hover:text-emerald-700">Masuk</a>
                    <a href="{{ route('daftar') }}" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">Daftar Gratis</a>
                @endif
            </nav>
        </header>

        <section class="bg-gradient-to-br from-emerald-50 via-white to-teal-50 border-y border-emerald-100">
            <div class="max-w-6xl mx-auto px-4 py-16 sm:py-24 grid lg:grid-cols-2 gap-10 items-center">
                <div>
                    <span class="inline-block rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold px-3 py-1 mb-4">Untuk toko &amp; warung UMKM</span>
                    <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">
                        Kasir, stok, dan laporan toko dalam satu aplikasi.
                    </h1>
                    <p class="mt-4 text-lg text-slate-600 max-w-xl">
                        Tidak perlu lagi menghitung nota satu per satu. Setiap transaksi otomatis mengurangi stok dan masuk ke rekap harian. Cukup buka
                        di peramban, tanpa instalasi.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('daftar') }}" class="px-5 py-3 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 shadow-sm">
                            Daftarkan Toko Anda
                        </a>
                        <a href="{{ route('masuk') }}" class="px-5 py-3 rounded-xl bg-white border border-slate-300 font-semibold text-slate-700 hover:bg-slate-50">
                            Coba Akun Demo
                        </a>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-lg p-5">
                    <p class="text-xs font-semibold text-slate-500">Rekap hari ini</p>
                    <div class="grid grid-cols-3 gap-3 mt-3">
                        @foreach ([['Penjualan', 'Rp1.245.500'], ['Transaksi', '48'], ['Laba kotor', 'Rp186.300']] as [$l, $v])
                            <div class="rounded-xl bg-slate-50 p-3">
                                <p class="text-[11px] text-slate-500">{{ $l }}</p>
                                <p class="font-bold text-slate-900 text-sm sm:text-base">{{ $v }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach ([['Beras 5kg', '12 terjual'], ['Minyak Goreng 1L', '21 terjual'], ['Mi Instan Goreng', '64 terjual']] as [$n, $j])
                            <div class="flex justify-between text-sm border-b border-slate-100 pb-2">
                                <span class="text-slate-700">{{ $n }}</span>
                                <span class="font-semibold text-emerald-700">{{ $j }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-4 text-[11px] text-slate-400">Contoh tampilan — data ilustrasi.</p>
                </div>
            </div>
        </section>

        <section class="max-w-6xl mx-auto px-4 py-16">
            <h2 class="text-2xl font-bold text-slate-900 text-center">Semua yang dibutuhkan toko kecil</h2>
            <p class="text-slate-600 text-center mt-2">Penjualan, stok, dan laporan tersimpan otomatis dan saling terhubung.</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-10">
                @foreach ($fitur as [$ikon, $judul, $teks])
                    <div class="rounded-2xl border border-slate-200 p-5 hover:border-emerald-300 transition-colors">
                        <x-ikon :nama="$ikon" class="w-6 h-6 text-emerald-600" />
                        <h3 class="font-bold text-slate-900 mt-3">{{ $judul }}</h3>
                        <p class="text-sm text-slate-600 mt-1">{{ $teks }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-10 flex items-start gap-3 rounded-2xl bg-slate-50 border border-slate-200 p-5">
                <x-ikon nama="kunci" class="w-6 h-6 text-emerald-600 shrink-0" />
                <p class="text-sm text-slate-700">
                    <b>Data transaksi aman:</b> transaksi baru tersimpan setelah kasir mengonfirmasi pembayaran, dan seluruh langkahnya (simpan nota, kurangi
                    stok, catat mutasi) berhasil bersama atau batal bersama. Tidak ada data setengah tersimpan. Kata sandi disimpan terenkripsi.
                </p>
            </div>
        </section>

        @if ($toko->isNotEmpty())
            <section class="bg-slate-50 border-t border-slate-200">
                <div class="max-w-6xl mx-auto px-4 py-14">
                    <h2 class="text-xl font-bold text-slate-900">Lihat katalog toko</h2>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-5">
                        @foreach ($toko as $t)
                            <a href="{{ route('katalog', $t->slug) }}" class="bg-white rounded-xl border border-slate-200 p-4 hover:border-emerald-400 hover:shadow-sm">
                                <p class="font-semibold text-slate-900">{{ $t->nama }}</p>
                                <p class="text-sm text-slate-500 truncate">{{ $t->alamat ?: 'Lihat daftar barang →' }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-500">
            TokoKasir — Aplikasi Manajemen Toko dan Kasir Berbasis Web untuk UMKM
        </footer>
    </div>
</x-layouts.dasar>
