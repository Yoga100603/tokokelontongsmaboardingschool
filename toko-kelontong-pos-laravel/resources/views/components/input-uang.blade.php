@props(['model'])
{{-- Input uang Alpine (setara InputUang): tampil "10.000", nilai number di variabel `model`.
     <x-input-uang model="form.harga_jual" placeholder="0" /> --}}
<input type="text" inputmode="numeric" autocomplete="off"
    :value="{{ $model }} ? TK.angka({{ $model }}) : ''"
    @input="{{ $model }} = Number($event.target.value.replace(/\D/g, '')) || 0; $event.target.value = {{ $model }} ? TK.angka({{ $model }}) : ''"
    {{ $attributes->merge(['class' => 'input']) }}>
