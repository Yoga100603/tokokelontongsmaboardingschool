@props(['judul', 'teks' => null, 'ikon' => null])
{{-- Keadaan kosong (setara Kosong di ui.tsx). Slot default = tombol aksi opsional. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-4 py-12 text-center']) }}>
    @if ($ikon)
        <div class="mb-3 text-slate-300"><x-ikon :nama="$ikon" class="h-10 w-10" /></div>
    @endif
    <p class="font-semibold text-slate-700">{{ $judul }}</p>
    @if ($teks)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $teks }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
