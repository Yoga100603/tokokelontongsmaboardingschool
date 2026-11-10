{{-- Detail struk (port app/(app)/struk/[nomor]/page.tsx). Data dari GET /api/transaksi/{nomor}. --}}
<x-layouts.app judul="Struk {{ $nomor }}">
    <div x-data="detailStruk(@js($nomor))">
        <a href="{{ route('struk') }}" class="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-emerald-700 mb-4 print:hidden">
            <x-ikon nama="kiri" class="w-4 h-4" /> Riwayat struk
        </a>

        <template x-if="galat">
            <div class="kartu">
                <div class="flex flex-col items-center justify-center px-4 py-12 text-center">
                    <p class="font-semibold text-slate-700">Struk tidak ditemukan</p>
                    <p class="mt-1 max-w-sm text-sm text-slate-500" x-text="galat"></p>
                </div>
            </div>
        </template>
        <template x-if="!galat && !s">
            <x-memuat />
        </template>
        <template x-if="!galat && s">
            <div class="grid lg:grid-cols-[360px_1fr] gap-5 items-start">
                <div class="kartu p-4">
                    @include('struk._kertas', ['v' => 's'])
                </div>
                <div class="space-y-5 print:hidden">
                    <div class="kartu p-4">
                        <h2 class="font-bold text-slate-900 mb-3">Aksi struk</h2>
                        @include('struk._aksi', ['v' => 's', 'onLog' => 'muat()'])
                    </div>
                    <div class="kartu p-4">
                        <h2 class="font-bold text-slate-900 mb-3">Riwayat struk</h2>
                        <template x-if="!s.log?.length">
                            <p class="text-sm text-slate-500">Struk ini belum pernah dicetak, disimpan, atau dikirim.</p>
                        </template>
                        <template x-if="s.log?.length">
                            <ul class="space-y-2 text-sm">
                                <template x-for="(l, i) in s.log" :key="i">
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2">
                                            <template x-if="l.aksi === 'kirim'"><x-lencana warna="hijau"><span x-text="labelAksi(l.aksi)"></span></x-lencana></template>
                                            <template x-if="l.aksi === 'cetak'"><x-lencana warna="biru"><span x-text="labelAksi(l.aksi)"></span></x-lencana></template>
                                            <template x-if="l.aksi !== 'kirim' && l.aksi !== 'cetak'"><x-lencana warna="abu"><span x-text="labelAksi(l.aksi)"></span></x-lencana></template>
                                            <span class="text-slate-500" x-text="l.tujuan"></span>
                                        </span>
                                        <span class="text-xs text-slate-500" x-text="TK.waktu(l.dibuat_pada) + ' · ' + l.oleh"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @push('skrip')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('detailStruk', (nomor) => ({
                    nomor,
                    s: null,
                    galat: '',

                    init() {
                        this.muat();
                    },

                    labelAksi(a) {
                        return { cetak: 'Dicetak', simpan: 'Disimpan', kirim: 'Dikirim' }[a] ?? a;
                    },

                    async muat() {
                        try {
                            this.s = (await TK.panggil('/api/transaksi/' + encodeURIComponent(this.nomor))).data;
                        } catch (e) {
                            this.galat = e.message;
                            TK.notif(e.message, 'galat');
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
