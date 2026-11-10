{{-- Kertas struk yang bisa dicetak (port KertasStruk di components/Struk.tsx).
     Pakai: @include('struk._kertas', ['v' => 'namaVarAlpine']) — `v` = variabel Alpine berisi data struk.
     Hanya #struk-cetak yang tampil saat print (lihat resources/css/app.css). --}}
@php $v = $v ?? 's'; @endphp
@include('struk._skrip')
<div id="struk-cetak" class="font-mono text-[12px] leading-snug text-black bg-white mx-auto w-full max-w-[320px] p-4 border border-dashed border-slate-300 rounded-lg print:border-0 print:p-0 print:max-w-none">
    <div class="text-center mb-2">
        <template x-if="{{ $v }}.toko.logo_url">
            <img :src="{{ $v }}.toko.logo_url" alt="" class="h-10 mx-auto mb-1 object-contain grayscale">
        </template>
        <p class="font-bold text-[14px] uppercase" x-text="{{ $v }}.toko.nama"></p>
        <template x-if="{{ $v }}.toko.alamat"><p x-text="{{ $v }}.toko.alamat"></p></template>
        <template x-if="{{ $v }}.toko.telepon"><p x-text="'Telp ' + {{ $v }}.toko.telepon"></p></template>
    </div>
    <div class="border-t border-dashed border-black my-2"></div>
    <div class="grid grid-cols-[52px_1fr] gap-x-1">
        <span>No</span>
        <span x-text="': ' + {{ $v }}.nomor_transaksi"></span>
        <span>Waktu</span>
        <span x-text="': ' + TK.waktu({{ $v }}.waktu)"></span>
        <span>Kasir</span>
        <span x-text="': ' + {{ $v }}.kasir"></span>
        <template x-if="{{ $v }}.nama_pelanggan"><span>Plgn</span></template>
        <template x-if="{{ $v }}.nama_pelanggan"><span x-text="': ' + {{ $v }}.nama_pelanggan"></span></template>
    </div>
    <div class="border-t border-dashed border-black my-2"></div>
    <template x-for="(i, k) in {{ $v }}.item" :key="k">
        <div class="mb-1">
            <p x-text="i.nama_barang"></p>
            <div class="flex justify-between pl-2">
                <span x-text="i.jumlah + ' ' + i.satuan + ' x ' + TK.rupiah(i.harga_jual)"></span>
                <span x-text="TK.rupiah(i.subtotal)"></span>
            </div>
        </div>
    </template>
    <div class="border-t border-dashed border-black my-2"></div>
    <div class="flex justify-between font-bold text-[13px]">
        <span>TOTAL</span>
        <span x-text="TK.rupiah({{ $v }}.total)"></span>
    </div>
    <div class="flex justify-between">
        <span x-text="'Bayar (' + StrukUtil.labelMetode({{ $v }}.metode_bayar) + ')'"></span>
        <span x-text="TK.rupiah({{ $v }}.uang_bayar)"></span>
    </div>
    <template x-if="{{ $v }}.metode_bayar === 'hutang'">
        <div class="flex justify-between font-bold">
            <span>Sisa hutang</span>
            <span x-text="TK.rupiah({{ $v }}.total - {{ $v }}.uang_bayar)"></span>
        </div>
    </template>
    <template x-if="{{ $v }}.metode_bayar !== 'hutang'">
        <div class="flex justify-between">
            <span>Kembali</span>
            <span x-text="TK.rupiah({{ $v }}.kembalian)"></span>
        </div>
    </template>
    <div class="border-t border-dashed border-black my-2"></div>
    <p class="text-center" x-text="{{ $v }}.toko.catatan_struk"></p>
    <template x-if="{{ $v }}.status === 'dibatalkan'">
        <p class="text-center font-bold mt-1">*** DIBATALKAN ***</p>
    </template>
</div>
