@props(['judul', 'sub' => null])
{{-- Kerangka halaman masuk/daftar/lupa/reset (setara components/KartuAuth.tsx). --}}
<x-layouts.dasar :judul="$judul">
    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-emerald-50 via-slate-50 to-teal-50 px-4 py-10">
        <a href="{{ route('beranda') }}" class="mb-6 flex items-center gap-2">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white">
                <x-ikon nama="store" class="w-5 h-5" />
            </span>
            <span class="text-lg font-extrabold text-slate-900">TokoKasir</span>
        </a>
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-xl font-bold text-slate-900">{{ $judul }}</h1>
            @if ($sub)
                <p class="mt-1 text-sm text-slate-500">{{ $sub }}</p>
            @endif
            <div class="mt-6">
                @error('umum')
                    <x-pesan jenis="galat">{{ $message }}</x-pesan>
                @enderror
                @if (session('sukses'))
                    <x-pesan jenis="sukses">{{ session('sukses') }}</x-pesan>
                @endif
                @if (session('info'))
                    <x-pesan jenis="info">{{ session('info') }}</x-pesan>
                @endif
                {{ $slot }}
            </div>
        </div>
        @isset($kaki)
            <div class="mt-5 text-center text-sm text-slate-600">{{ $kaki }}</div>
        @endisset
    </div>
</x-layouts.dasar>
