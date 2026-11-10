@props(['judul' => null])
{{-- Kerangka halaman internal: sidebar + header mobile (setara components/Shell.tsx). --}}
@php
    $u = auth()->user();
    $menu = collect([
        ['dasbor', 'Dasbor', 'dasbor', ['pemilik']],
        ['kasir', 'Kasir', 'keranjang', ['pemilik', 'kasir']],
        ['barang', 'Barang & Stok', 'paket', ['pemilik', 'kasir']],
        ['struk', 'Riwayat Struk', 'struk', ['pemilik', 'kasir']],
        ['penjualan', 'Penjualan Harian', 'kalender', ['pemilik', 'kasir']],
        ['laporan', 'Laporan', 'grafik', ['pemilik']],
        ['opname', 'Stok Opname', 'opname', ['pemilik', 'kasir']],
        ['hutang', 'Buku Hutang', 'buku', ['pemilik', 'kasir']],
        ['pengaturan', 'Akun & Pengaturan', 'pengaturan', ['pemilik', 'kasir']],
    ])->filter(fn ($m) => in_array($u->peran, $m[3], true));
@endphp
<x-layouts.dasar :judul="$judul">
    <x-notif />
    <div x-data="{ buka: false }" class="min-h-screen bg-slate-100">
        {{-- Sidebar (desktop) & drawer (mobile) memakai isi yang sama --}}
        @foreach (['desktop', 'mobile'] as $mode)
            @if ($mode === 'desktop')
                <aside class="fixed inset-y-0 left-0 hidden w-60 bg-slate-900 lg:block print:hidden">
            @else
                <div x-show="buka" x-cloak class="fixed inset-0 z-40 lg:hidden print:hidden">
                    <div class="absolute inset-0 bg-slate-900/60" @click="buka = false"></div>
                    <aside class="absolute inset-y-0 left-0 w-64 bg-slate-900 shadow-xl">
                        <button type="button" @click="buka = false" class="absolute top-4 right-3 text-slate-400" aria-label="Tutup menu">
                            <x-ikon nama="x" class="w-5 h-5" />
                        </button>
            @endif

            <nav class="flex h-full flex-col">
                <div class="flex items-center gap-3 border-b border-white/10 px-4 py-4">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-white">
                        <x-ikon nama="store" class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-white">{{ $u->toko->nama }}</p>
                        <a href="{{ url('/toko/'.$u->toko->slug) }}" target="_blank" rel="noreferrer"
                            class="inline-flex items-center gap-1 text-[11px] text-emerald-300 hover:underline">
                            Lihat katalog <x-ikon nama="tautan-luar" class="w-3 h-3" />
                        </a>
                    </div>
                </div>
                <ul class="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                    @foreach ($menu as [$rute, $label, $ikon])
                        @php $aktif = request()->routeIs($rute, $rute.'.*'); @endphp
                        <li>
                            <a href="{{ route($rute) }}" @click="buka = false"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $aktif ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                                <x-ikon :nama="$ikon" class="w-4 h-4 shrink-0" />
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-white/10 p-3">
                    <div class="px-2 pb-2">
                        <p class="truncate text-sm font-semibold text-white">{{ $u->nama }}</p>
                        <p class="text-[11px] text-slate-400 capitalize">{{ $u->peran }}</p>
                    </div>
                    <form method="POST" action="{{ route('keluar') }}">
                        @csrf
                        <button type="submit" data-memuat
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-rose-500/10 hover:text-rose-300 disabled:opacity-50">
                            <x-ikon nama="keluar" class="w-4 h-4" /> Keluar
                        </button>
                    </form>
                </div>
            </nav>

            @if ($mode === 'desktop')
                </aside>
            @else
                    </aside>
                </div>
            @endif
        @endforeach

        <div class="lg:pl-60 print:pl-0">
            <header class="sticky top-0 z-30 flex h-14 items-center gap-3 bg-slate-900 px-4 text-white lg:hidden print:hidden">
                <button type="button" @click="buka = true" aria-label="Buka menu">
                    <x-ikon nama="menu" class="w-6 h-6" />
                </button>
                <span class="truncate text-sm font-bold">{{ $u->toko->nama }}</span>
            </header>
            <main class="mx-auto max-w-7xl p-4 sm:p-6 print:p-0">
                @if (request('akses') === 'ditolak')
                    <x-pesan jenis="galat">Fitur itu hanya untuk pemilik toko.</x-pesan>
                @endif
                @if (session('sukses'))
                    <x-pesan jenis="sukses">{{ session('sukses') }}</x-pesan>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.dasar>
