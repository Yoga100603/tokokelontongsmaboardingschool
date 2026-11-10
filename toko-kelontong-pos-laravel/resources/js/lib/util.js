/** Port lib/klien.ts + lib/utils.ts + lib/tanggal-klien.ts dari versi Next.js. */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * fetch JSON ke API internal (session + CSRF). Melempar Error berisi pesan server bila gagal.
 * opsi: { method, body (objek → JSON), form (FormData) }
 */
export async function panggil(url, opsi = {}) {
    const method = opsi.method ?? (opsi.body !== undefined || opsi.form ? 'POST' : 'GET');
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
    const init = { method, headers, credentials: 'same-origin' };
    if (opsi.form) init.body = opsi.form;
    else if (opsi.body !== undefined) {
        init.body = JSON.stringify(opsi.body);
        headers['Content-Type'] = 'application/json';
    }
    let res;
    try {
        res = await fetch(url, init);
    } catch {
        throw new Error('Koneksi terputus. Periksa jaringan lalu coba lagi.');
    }
    let json = null;
    try {
        json = await res.json();
    } catch {
        /* bukan JSON */
    }
    if (res.status === 401) window.location.href = '/masuk?sesi=1';
    if (res.status === 419) throw new Error('Halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    if (!res.ok || !json?.sukses) throw new Error(json?.pesan || `Permintaan gagal (${res.status}).`);
    return json;
}

const fmt = new Intl.NumberFormat('id-ID');
export const rupiah = (n) => 'Rp' + fmt.format(Math.round(Number(n) || 0));
export const angka = (n) => fmt.format(Number(n) || 0);

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

/** "2026-11-08" → "8 Nov 2026" (opsional dengan nama hari). */
export function tanggal(s, denganHari = false) {
    if (!s) return '-';
    const [y, m, d] = String(s).slice(0, 10).split('-').map(Number);
    if (!y || !m || !d) return s;
    const hari = HARI[new Date(Date.UTC(y, m - 1, d)).getUTCDay()];
    return `${denganHari ? hari + ', ' : ''}${d} ${BULAN[m - 1]} ${y}`;
}

/** "2026-11-08 14:05:00" → "8 Nov 2026 14.05". */
export function waktu(s) {
    if (!s) return '-';
    const t = String(s).replace('T', ' ');
    const jam = t.slice(11, 16).replace(':', '.');
    return `${tanggal(t)}${jam ? ' ' + jam : ''}`;
}

export function nomorWa(no) {
    let d = String(no || '').replace(/\D/g, '');
    if (d.startsWith('0')) d = '62' + d.slice(1);
    else if (d.startsWith('8')) d = '62' + d;
    return d;
}

/** Tanggal WIB hari ini di browser ("YYYY-MM-DD"). */
export function hariIni() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}

export function geserHari(t, n) {
    const d = new Date(`${t}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
}
