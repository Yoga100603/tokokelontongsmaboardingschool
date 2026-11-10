@props(['label', 'nama', 'wajib' => false, 'petunjuk' => null])
{{-- Label + input + petunjuk + pesan validasi. Atribut lain diteruskan ke <input>. --}}
<div>
    <label for="f-{{ $nama }}" class="label">
        {{ $label }}@if ($wajib)<span class="text-rose-500"> *</span>@endif
    </label>
    <input id="f-{{ $nama }}" name="{{ $nama }}" @if ($wajib) required @endif
        {{ $attributes->merge(['class' => 'input'.($errors->has($nama) ? ' border-rose-400' : '')]) }}>
    @if ($petunjuk)
        <p class="mt-1 text-[11px] text-slate-500">{{ $petunjuk }}</p>
    @endif
    @error($nama)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
