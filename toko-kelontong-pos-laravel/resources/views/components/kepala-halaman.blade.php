@props(['judul', 'sub' => null])
{{-- Judul halaman + slot `aksi` di kanan (setara KepalaHalaman di ui.tsx). --}}
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $judul }}</h1>
        @if ($sub)
            <p class="mt-0.5 text-sm text-slate-500">{{ $sub }}</p>
        @endif
    </div>
    @isset($aksi)
        <div class="flex flex-wrap gap-2">{{ $aksi }}</div>
    @endisset
</div>
