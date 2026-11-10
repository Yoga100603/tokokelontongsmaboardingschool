import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { panggil, rupiah, angka, tanggal, waktu, nomorWa, hariIni, geserHari } from './lib/util';
import { bunyiBeep, bunyiSukses, bunyiGalat } from './lib/suara';

window.Alpine = Alpine;
window.Chart = Chart;

/**
 * Helper global untuk komponen Alpine di tiap halaman (setara lib/klien.ts, lib/utils.ts, lib/sound.ts):
 *   await TK.panggil('/api/barang', { body: {...} })   → {sukses, pesan, data} atau throw Error(pesan)
 *   TK.rupiah(12000) → "Rp12.000"     TK.notif('Tersimpan', 'sukses'|'galat'|'info')
 */
window.TK = {
    panggil, rupiah, angka, tanggal, waktu, nomorWa, hariIni, geserHari,
    bunyiBeep, bunyiSukses, bunyiGalat,
    notif(pesan, jenis = 'sukses') {
        window.dispatchEvent(new CustomEvent('notif', { detail: { pesan, jenis } }));
    },
};

// Store notifikasi (toast) — dirender oleh <x-notif /> di layout aplikasi.
document.addEventListener('alpine:init', () => {
    Alpine.data('notifToast', () => ({
        daftar: [],
        tambah({ pesan, jenis }) {
            const id = Date.now() + Math.random();
            this.daftar.push({ id, pesan, jenis });
            setTimeout(() => this.hapus(id), jenis === 'galat' ? 6000 : 3500);
        },
        hapus(id) {
            this.daftar = this.daftar.filter((n) => n.id !== id);
        },
    }));
});

// Tombol submit form biasa otomatis "memuat" (disabled + spinner) agar tidak dobel kirim.
document.addEventListener('submit', (e) => {
    const btn = e.target.querySelector('button[type=submit][data-memuat]');
    if (btn) {
        btn.disabled = true;
        btn.querySelector('[data-spinner]')?.classList.remove('hidden');
    }
});

Alpine.start();
