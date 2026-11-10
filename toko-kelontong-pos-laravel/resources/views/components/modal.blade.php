@props(['buka', 'judul', 'lebar' => 'max-w-lg'])
{{--
  Modal Alpine (setara <Modal> di ui.tsx). `buka` = NAMA variabel boolean di x-data induk.
  Contoh:
    <div x-data="{ formBuka: false }">
      <x-modal buka="formBuka" judul="Tambah Barang">
        ...isi...
        <x-slot:kaki><button class="btn btn-sekunder" @click="formBuka = false">Batal</button></x-slot:kaki>
      </x-modal>
    </div>
  `judul` boleh ekspresi Alpine via atribut :judul-teks="ekspresi" (opsional).
--}}
<template x-teleport="body">
    <div x-show="{{ $buka }}" x-cloak x-transition.opacity @keydown.escape.window="{{ $buka }} = false"
        class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 sm:items-center sm:p-4"
        @mousedown.self="{{ $buka }} = false">
        <div role="dialog" aria-modal="true" aria-label="{{ $judul }}"
            class="flex max-h-[92vh] w-full flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl {{ $lebar }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                <h2 class="font-bold text-slate-900"
                    @if ($attributes->has('judul-teks')) x-text="{{ $attributes->get('judul-teks') }}" @endif>{{ $judul }}</h2>
                <button type="button" @click="{{ $buka }} = false" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Tutup">
                    <x-ikon nama="x" class="w-5 h-5" />
                </button>
            </div>
            <div class="overflow-y-auto px-5 py-4">{{ $slot }}</div>
            @isset($kaki)
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-5 py-3">{{ $kaki }}</div>
            @endisset
        </div>
    </div>
</template>
