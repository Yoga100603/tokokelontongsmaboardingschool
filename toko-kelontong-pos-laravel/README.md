# TokoKasir — Aplikasi Manajemen Toko & Kasir UMKM (Laravel)

Implementasi PRD *Proposal GTNIC 2026 – Aplikasi Manajemen Toko dan Kasir Berbasis Web*.
Stack: **Laravel 12 + Blade + Alpine.js + Tailwind CSS v4 + MySQL 8** (monolit).
Konversi dari versi Next.js (`../toko-kelontong-pos/`) — fitur, alur, dan bentuk API dipertahankan.

## Menjalankan (Laragon / lokal)

```bash
composer install
npm install && npm run build          # dev: npm run dev
cp .env.example .env && php artisan key:generate   # sekali (lalu atur DB_* ke MySQL)
mysql -uroot -e "CREATE DATABASE toko_kasir CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed            # skema + data demo
php artisan serve                     # http://127.0.0.1:8000
```

Akun demo (dibuat `DemoSeeder`, matikan dengan `SEED_DEMO=0`):

| Peran   | Email            | Sandi    |
|---------|------------------|----------|
| Pemilik | pemilik@demo.id  | demo1234 |
| Kasir   | kasir@demo.id    | demo1234 |

Katalog demo: `/toko/toko-berkah`. Ulang dari awal: `php artisan migrate:fresh --seed`.

Test: `php artisan test` (SQLite in-memory, tidak menyentuh MySQL).

## Struktur

| Bagian | Lokasi |
|---|---|
| Skema 12 tabel (nama tabel/kolom sama dengan versi Next.js) | `database/migrations/2026_11_09_*` |
| Model + isolasi toko (global scope `toko`, `toko_id` dari sesi) | `app/Models`, `app/Models/Concerns/MilikToko.php` |
| Satu-satunya pintu perubahan stok (`lockForUpdate`, wajib dalam transaksi) | `app/Services/MutasiStokService.php` |
| Rekap / struk / upload gambar | `app/Services/{Rekap,Struk,Berkas}Service.php` |
| Route per modul (halaman + JSON `/api/*`) | `routes/modul/*.php` (login), `routes/publik/*.php` |
| Format respons API `{sukses, pesan, data}` + galat JSON | `app/Http/Controllers/Controller.php`, `bootstrap/app.php` |
| Layout, komponen Blade | `resources/views/components/` |
| Helper JS `window.TK` (panggil API, format rupiah, toast, suara) | `resources/js/app.js`, `resources/js/lib/` |

## Keamanan & integritas

- Sandi di-hash **bcrypt** (bawaan Laravel). Sesi Laravel (database driver) + `versi_sesi` di tabel pengguna,
  divalidasi ulang ke DB tiap request (`CekSesiPengguna`): kasir dinonaktifkan / ganti sandi → sesi lama keluar.
- `toko_id` selalu dari sesi (global scope + auto-fill), tidak pernah dari request.
- Harga & total transaksi dihitung ulang di server; transaksi + stok + mutasi + hutang dalam satu `DB::transaction`
  dengan row lock.
- Hak akses per peran di server (`peran:pemilik`). CSRF bawaan Laravel (token di `<meta>`, dikirim `TK.panggil`).
- Rate-limit login (5×/15 menit per IP+email), daftar, lupa sandi. Validasi upload dari magic bytes. CSV aman dari formula injection.

## Catatan teknis (penting saat mengubah kode)

- **Antre per toko**: penulisan kritis (transaksi kasir, tutup buku, mulai/setujui opname) memanggil
  `Toko::kunci($tokoId)` sebagai statement pertama di `DB::transaction` — pengganti kunci tulis global versi Next.js.
  Barang di keranjang dikunci berurutan id (`ksort`) agar tidak deadlock.
- **MySQL `NO_UNSIGNED_SUBTRACTION`** (`config/database.php` → `modes`): kolom uang/stok `UNSIGNED`, tapi selisih
  (laba saat jual di bawah modal, selisih opname) boleh negatif. Tanpa mode ini MySQL error 1690. Test memakai SQLite
  sehingga TIDAK menangkap masalah ini — uji juga di MySQL untuk query yang mengurangkan kolom.
- Kolom `tanggal` (transaksi, tutup_buku) selalu disimpan `Y-m-d` lewat mutator model.
- Membuat fixture toko lain di test saat sedang login → `toko_id` ikut terisi toko yang login (perilaku `MilikToko`);
  buat fixture sebelum login atau `Auth::logout()` dulu.

## Perbedaan dari versi Next.js

- **Sandi lama (scrypt) tidak kompatibel** — data `data/toko.db` lama tidak dimigrasi otomatis; data demo di-seed ulang.
- Tabel `users`/`password_reset_tokens` bawaan Laravel tidak dipakai (akun = `pengguna`, token reset = kolom `token_reset`).
- **Email reset sandi belum dikirim** (SMTP belum dikonfigurasi): tautan ditulis ke `storage/logs/laravel.log` (`[lupa-sandi]`).
- Gambar upload disimpan di `storage/app/private/uploads`, disajikan via `GET /api/berkas/{nama}`.
