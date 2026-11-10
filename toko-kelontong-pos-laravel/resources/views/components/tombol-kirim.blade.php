@props(['varian' => 'utama'])
{{-- Tombol submit: otomatis disabled + spinner saat form dikirim (lihat resources/js/app.js). --}}
<button type="submit" data-memuat {{ $attributes->merge(['class' => "btn btn-$varian"]) }}>
    <x-ikon nama="memuat" class="hidden w-4 h-4 animate-spin" data-spinner />
    {{ $slot }}
</button>
