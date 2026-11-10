@props(['warna' => 'abu'])
{{-- Badge kecil (setara Lencana di ui.tsx). warna: hijau|merah|kuning|biru|abu --}}
@php
    $w = [
        'hijau' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'merah' => 'bg-rose-50 text-rose-700 border-rose-200',
        'kuning' => 'bg-amber-50 text-amber-800 border-amber-200', 'biru' => 'bg-sky-50 text-sky-700 border-sky-200',
        'abu' => 'bg-slate-100 text-slate-600 border-slate-200',
    ][$warna];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-semibold $w"]) }}>{{ $slot }}</span>
