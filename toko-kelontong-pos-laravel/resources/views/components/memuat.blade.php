@props(['teks' => 'Memuat...'])
<div {{ $attributes->merge(['class' => 'flex items-center justify-center gap-2 py-12 text-sm text-slate-500']) }}>
    <x-ikon nama="memuat" class="h-5 w-5 animate-spin text-emerald-600" /> {{ $teks }}
</div>
