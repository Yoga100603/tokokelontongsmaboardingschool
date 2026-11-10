@props(['jenis' => 'info'])
@php
    $w = [
        'galat' => 'bg-rose-50 border-rose-200 text-rose-800',
        'sukses' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'info' => 'bg-sky-50 border-sky-200 text-sky-800',
    ][$jenis];
@endphp
<div role="{{ $jenis === 'galat' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "rounded-lg border px-3 py-2 text-sm mb-4 $w"]) }}>
    {{ $slot }}
</div>
