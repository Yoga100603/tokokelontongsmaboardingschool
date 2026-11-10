@props(['label', 'ikon' => null, 'warna' => 'emerald'])
{{-- Kartu angka (setara Statistik di ui.tsx). Isi nilai lewat slot default; slot `sub` opsional.
     Nilai dinamis Alpine: <x-statistik label="Omzet"><span x-text="TK.rupiah(d.total)"></span></x-statistik> --}}
@php
    $w = [
        'emerald' => 'bg-emerald-50 text-emerald-600', 'sky' => 'bg-sky-50 text-sky-600',
        'amber' => 'bg-amber-50 text-amber-600', 'rose' => 'bg-rose-50 text-rose-600', 'violet' => 'bg-violet-50 text-violet-600',
    ][$warna];
@endphp
<div {{ $attributes->merge(['class' => 'kartu p-4']) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
        @if ($ikon)
            <span class="rounded-lg p-1.5 {{ $w }}"><x-ikon :nama="$ikon" class="w-4 h-4" /></span>
        @endif
    </div>
    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ $slot }}</p>
    @isset($sub)
        <div class="mt-1 text-xs text-slate-500">{{ $sub }}</div>
    @endisset
</div>
