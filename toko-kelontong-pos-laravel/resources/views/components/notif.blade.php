{{-- Toast notifikasi global. Pemicu dari JS: TK.notif('Pesan', 'sukses'|'galat'|'info') --}}
<div x-data="notifToast" @notif.window="tambah($event.detail)"
    class="pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-3 print:hidden" aria-live="polite">
    <template x-for="n in daftar" :key="n.id">
        <div x-transition.opacity
            class="pointer-events-auto flex w-full max-w-sm items-start gap-2 rounded-lg border px-3 py-2 text-sm shadow-lg"
            :class="{
                'bg-emerald-50 border-emerald-200 text-emerald-800': n.jenis === 'sukses',
                'bg-rose-50 border-rose-200 text-rose-800': n.jenis === 'galat',
                'bg-sky-50 border-sky-200 text-sky-800': n.jenis === 'info',
            }">
            <span class="flex-1" x-text="n.pesan"></span>
            <button type="button" @click="hapus(n.id)" class="opacity-60 hover:opacity-100" aria-label="Tutup">
                <x-ikon nama="x" class="w-4 h-4" />
            </button>
        </div>
    </template>
</div>
