{{--
  Port components/GrafikBatang.tsx — grafik batang satu seri (penjualan per hari) memakai div/CSS.
  Satu warna, batang tipis dengan ujung membulat menempel di garis dasar, tooltip saat hover/fokus.
  Pemakaian (di dalam scope Alpine induk): @include('laporan._grafik', ['data' => 'd.harian', 'tinggi' => 180])
  `data` = EKSPRESI Alpine berisi array {tanggal, penjualan, laba?, jumlah_transaksi?|jumlah?}.
--}}
@php $tinggi = $tinggi ?? 180; @endphp
<div x-data="{
        aktif: null,
        maks(s) { return Math.max(1, ...s.map((x) => Number(x.penjualan) || 0)); },
        labelSetiap(s) { return Math.max(1, Math.ceil(s.length / 10)); },
        tinggiBatang(x, s) { return x.penjualan ? Math.max(2, (x.penjualan / this.maks(s)) * 100) : 0; },
    }">
    <div class="relative" style="height: {{ (int) $tinggi }}px" @mouseleave="aktif = null">
        {{-- garis bantu --}}
        <template x-for="g in [0.5, 1]" :key="g">
            <div class="absolute inset-x-0 border-t border-dashed border-slate-200" :style="`bottom: ${g * 100}%`">
                <span class="absolute -top-2 left-0 text-[10px] text-slate-400 bg-white pr-1" x-text="TK.rupiah(maks({{ $data }}) * g)"></span>
            </div>
        </template>
        <div class="absolute inset-0 flex items-end gap-[2px] pl-0 border-b border-slate-300">
            <template x-for="(t, i) in {{ $data }}" :key="t.tanggal">
                <button type="button" class="flex-1 h-full flex items-end justify-center group focus:outline-none"
                    @mouseenter="aktif = i" @focus="aktif = i" @blur="aktif = null"
                    :aria-label="`${TK.tanggal(t.tanggal)}: ${TK.rupiah(t.penjualan)}`">
                    <span class="w-full max-w-[28px] rounded-t-[4px] transition-colors"
                        :class="aktif === i ? 'bg-emerald-700' : 'bg-emerald-500'"
                        :style="`height: ${tinggiBatang(t, {{ $data }})}%`"></span>
                </button>
            </template>
        </div>
        <template x-if="aktif !== null && ({{ $data }})[aktif]">
            <div class="pointer-events-none absolute z-10 -translate-x-1/2 rounded-lg bg-slate-900 text-white text-xs px-3 py-2 shadow-lg whitespace-nowrap"
                :style="`left: ${((aktif + 0.5) / ({{ $data }}).length) * 100}%; bottom: 100%`">
                <p class="font-semibold" x-text="TK.tanggal(({{ $data }})[aktif].tanggal, true)"></p>
                <p x-text="'Penjualan: ' + TK.rupiah(({{ $data }})[aktif].penjualan)"></p>
                <p class="text-slate-300" x-show="({{ $data }})[aktif].laba !== undefined" x-text="'Laba kotor: ' + TK.rupiah(({{ $data }})[aktif].laba)"></p>
                <p class="text-slate-300" x-show="(({{ $data }})[aktif].jumlah_transaksi ?? ({{ $data }})[aktif].jumlah) !== undefined"
                    x-text="(({{ $data }})[aktif].jumlah_transaksi ?? ({{ $data }})[aktif].jumlah) + ' transaksi'"></p>
            </div>
        </template>
    </div>
    <div class="flex gap-[2px] mt-1">
        <template x-for="(t, i) in {{ $data }}" :key="t.tanggal">
            <span class="flex-1 text-center text-[10px] text-slate-500 tabular-nums"
                x-text="i % labelSetiap({{ $data }}) === 0 ? Number(t.tanggal.slice(8, 10)) : ''"></span>
        </template>
    </div>
</div>
