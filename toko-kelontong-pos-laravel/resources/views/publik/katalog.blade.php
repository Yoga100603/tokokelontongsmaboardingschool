{{-- Port app/toko/[slug]/page.tsx — katalog publik toko (tanpa login). Harga modal tidak pernah dikirim ke view. --}}
@php
    $linkWa = fn (string $pesan) => 'https://wa.me/'.$wa.'?text='.rawurlencode($pesan);
    $urlKategori = function (int $id) use ($q, $toko) {
        $s = array_filter(['q' => $q, 'kategori' => $id ?: null], fn ($v) => $v !== null && $v !== '');
        return url('/toko/'.$toko->slug).($s ? '?'.http_build_query($s) : '');
    };
@endphp
<x-layouts.dasar :judul="$toko->nama">
    <div class="min-h-screen bg-slate-50">
        <header class="bg-gradient-to-br from-emerald-700 to-teal-600 text-white">
            <div class="max-w-6xl mx-auto px-4 py-10 sm:py-14">
                <div class="flex items-start gap-4">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="Logo {{ $toko->nama }}" class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover bg-white shrink-0">
                    @else
                        <span class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/15 flex items-center justify-center shrink-0">
                            <x-ikon nama="store" class="w-8 h-8" />
                        </span>
                    @endif
                    <div class="min-w-0">
                        <h1 class="text-2xl sm:text-4xl font-extrabold tracking-tight">{{ $toko->nama }}</h1>
                        @if ($toko->deskripsi)
                            <p class="mt-2 text-emerald-50 max-w-2xl">{{ $toko->deskripsi }}</p>
                        @endif
                    </div>
                </div>
                <div class="mt-6 grid sm:grid-cols-3 gap-3 text-sm">
                    @foreach ([['lokasi', $toko->alamat], ['jam', $toko->jam_buka], ['telepon', $toko->telepon]] as [$ikon, $isi])
                        @if ($isi)
                            <div class="flex items-start gap-2 bg-white/10 rounded-xl px-3 py-2.5">
                                <x-ikon :nama="$ikon" class="w-4 h-4 mt-0.5 shrink-0" />
                                <span>{{ $isi }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
                @if ($wa)
                    <div class="mt-6 flex flex-wrap gap-2">
                        <a href="{{ $linkWa("Halo {$toko->nama}, saya ingin bertanya.") }}" target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 rounded-xl bg-white text-emerald-800 font-semibold px-4 py-2.5 hover:bg-emerald-50">
                            <x-ikon nama="wa" class="w-4 h-4" /> Hubungi via WhatsApp
                        </a>
                        <a href="tel:{{ $toko->telepon }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 font-semibold px-4 py-2.5 hover:bg-white/25">
                            <x-ikon nama="telepon" class="w-4 h-4" /> Telepon
                        </a>
                    </div>
                @endif
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 py-8">
            <form class="relative max-w-xl" action="{{ url('/toko/'.$toko->slug) }}" method="GET">
                <x-ikon nama="cari" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                @if ($kat > 0)
                    <input type="hidden" name="kategori" value="{{ $kat }}">
                @endif
                <input name="q" value="{{ $q }}" placeholder="Cari barang..."
                    class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-24 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500">
                <button class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg bg-emerald-600 text-white text-sm font-semibold px-4 py-2">Cari</button>
            </form>

            @if ($kategori->isNotEmpty())
                <div class="flex gap-1.5 overflow-x-auto py-4">
                    <a href="{{ $urlKategori(0) }}"
                        class="px-3 py-1.5 rounded-full text-xs font-semibold border whitespace-nowrap {{ ! $kat ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200' }}">
                        Semua
                    </a>
                    @foreach ($kategori as $k)
                        <a href="{{ $urlKategori((int) $k->id) }}"
                            class="px-3 py-1.5 rounded-full text-xs font-semibold border whitespace-nowrap {{ $kat === (int) $k->id ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200' }}">
                            {{ $k->nama }}
                        </a>
                    @endforeach
                </div>
            @endif

            <p class="text-sm text-slate-500 mb-3">
                {{ $barang->count() }} barang{{ $q !== '' ? ' untuk "'.$q.'"' : '' }}
            </p>

            @if ($barang->isEmpty())
                <div class="text-center py-16 text-slate-500">
                    <x-ikon nama="paket" class="w-10 h-10 mx-auto text-slate-300 mb-2" />
                    Barang tidak ditemukan.
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach ($barang as $b)
                        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">
                            <div class="aspect-square bg-slate-100 flex items-center justify-center">
                                @if ($b->foto_url)
                                    <img src="{{ $b->foto_url }}" alt="{{ $b->nama_barang }}" loading="lazy" class="w-full h-full object-cover">
                                @else
                                    <x-ikon nama="paket" class="w-10 h-10 text-slate-300" />
                                @endif
                            </div>
                            <div class="p-3 flex flex-col flex-1">
                                @if ($b->kategori)
                                    <p class="text-[11px] text-slate-400">{{ $b->kategori }}</p>
                                @endif
                                <p class="text-sm font-semibold text-slate-900 line-clamp-2">{{ $b->nama_barang }}</p>
                                <p class="text-emerald-700 font-bold mt-1">
                                    {{ Format::rupiah($b->harga_jual) }} <span class="text-xs font-normal text-slate-500">/ {{ $b->satuan }}</span>
                                </p>
                                <div class="mt-auto pt-2 flex items-center justify-between gap-2">
                                    <span class="text-[11px] font-semibold {{ $b->tersedia ? 'text-emerald-700' : 'text-rose-600' }}">{{ $b->tersedia ? 'Tersedia' : 'Stok habis' }}</span>
                                    @if ($wa)
                                        <a href="{{ $linkWa("Halo {$toko->nama}, apakah {$b->nama_barang} (".Format::rupiah($b->harga_jual)."/{$b->satuan}) tersedia?") }}"
                                            target="_blank" rel="noopener noreferrer"
                                            class="text-[11px] font-semibold text-emerald-700 hover:underline inline-flex items-center gap-1">
                                            <x-ikon nama="wa" class="w-3.5 h-3.5" /> Tanya
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="text-xs text-slate-400 text-center mt-10">
                Katalog bersifat informatif. Harga dapat berubah, silakan hubungi toko untuk memastikan ketersediaan.
            </p>
        </main>
    </div>
</x-layouts.dasar>
