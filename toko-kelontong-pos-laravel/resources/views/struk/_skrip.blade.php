{{-- Skrip bersama struk (labelMetode + komponen Alpine aksiStruk). Aman di-include berkali-kali (@once). --}}
@once
    @push('skrip')
        <script>
            window.StrukUtil = {
                LABEL_METODE: { tunai: 'Tunai', transfer: 'Transfer', qris: 'QRIS', hutang: 'Hutang (bon)' },
                labelMetode(m) {
                    return this.LABEL_METODE[m] ?? m;
                },
            };

            document.addEventListener('alpine:init', () => {
                Alpine.data('aksiStruk', (ambil, setelahLog) => ({
                    ambilStruk: ambil,
                    kertas: '58mm',
                    nomor: '',

                    init() {
                        try {
                            const k = localStorage.getItem('ukuran-struk');
                            if (k) this.kertas = k;
                        } catch {
                            /* abaikan */
                        }
                    },

                    url(akhiran = '') {
                        return '/api/transaksi/' + encodeURIComponent(this.ambilStruk().nomor_transaksi) + akhiran;
                    },

                    async catat(aksi, tujuan = '') {
                        try {
                            await TK.panggil(this.url('/log'), { body: { aksi, tujuan } });
                            setelahLog?.();
                        } catch {
                            /* log gagal tidak menghalangi kasir */
                        }
                    },

                    cetak() {
                        try {
                            localStorage.setItem('ukuran-struk', this.kertas);
                        } catch {
                            /* abaikan */
                        }
                        document.documentElement.dataset.kertas = this.kertas;
                        window.print();
                        this.catat('cetak', this.kertas);
                    },

                    simpan() {
                        const a = document.createElement('a');
                        a.href = this.url('?format=txt');
                        a.download = 'struk-' + this.ambilStruk().nomor_transaksi + '.txt';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        this.catat('simpan', 'txt');
                    },

                    async kirim() {
                        const wa = TK.nomorWa(this.nomor);
                        if (wa.length < 10) return TK.notif('Masukkan nomor WhatsApp pembeli yang valid.', 'galat');
                        let teks = this.ambilStruk().teks;
                        if (!teks) {
                            try {
                                teks = (await TK.panggil(this.url())).data.teks;
                            } catch (e) {
                                return TK.notif(e.message, 'galat');
                            }
                        }
                        window.open('https://wa.me/' + wa + '?text=' + encodeURIComponent(teks ?? ''), '_blank', 'noopener');
                        this.catat('kirim', 'wa:' + wa);
                    },
                }));
            });
        </script>
    @endpush
@endonce
