{{-- Tombol aksi struk: cetak, simpan, kirim — setiap aksi dicatat ke log_struk (port AksiStruk di components/Struk.tsx).
     Pakai: @include('struk._aksi', ['v' => 'namaVarAlpine', 'onLog' => 'muat()'])
     `v` = variabel Alpine berisi data struk; `onLog` = ekspresi JS (opsional) setelah log tercatat. --}}
@php
    $v = $v ?? 's';
    $onLog = $onLog ?? '';
@endphp
@include('struk._skrip')
<div x-data="aksiStruk(() => {{ $v }}, () => { {{ $onLog }} })" class="space-y-3 print:hidden">
    <div class="flex gap-2">
        <select x-model="kertas" class="input w-32" aria-label="Ukuran kertas">
            <option value="58mm">Struk 58mm</option>
            <option value="80mm">Struk 80mm</option>
            <option value="a4">Kertas A4</option>
        </select>
        <button type="button" @click="cetak()" class="btn btn-utama flex-1">
            <x-ikon nama="cetak" class="w-4 h-4" /> Cetak
        </button>
        <button type="button" @click="simpan()" class="btn btn-sekunder" title="Unduh struk sebagai file teks">
            <x-ikon nama="unduh" class="w-4 h-4" /> Simpan
        </button>
    </div>
    <div class="flex gap-2">
        <input type="text" x-model="nomor" class="input" placeholder="No. WhatsApp pembeli (08...)" inputmode="tel">
        <button type="button" @click="kirim()" class="btn btn-sekunder shrink-0">
            <x-ikon nama="kirim" class="w-4 h-4" /> Kirim
        </button>
    </div>
    <template x-if="ambilStruk().sudah_tutup_buku">
        <p class="text-xs text-slate-500 flex items-center gap-1">
            <x-ikon nama="kunci" class="w-3.5 h-3.5" /> Tanggal transaksi ini sudah tutup buku.
        </p>
    </template>
</div>
