# PasarKita - Web Marketplace Tradisional

Aplikasi marketplace sederhana untuk OAL 11: penjual menawarkan produk, pembeli memesan.
Dibangun dengan **PHP Native, MySQLi, dan Bootstrap 5**.

## Fitur

- Daftar produk dengan pencarian, filter kategori, dan pagination
- Detail produk
- CRUD produk oleh penjual (termasuk upload gambar)
- Keranjang belanja (tambah, ubah jumlah, hapus, kosongkan)
- Checkout yang menyimpan pesanan dan detail pesanan ke database (transaksi MySQLi dengan rollback)
- Riwayat pesanan pembeli dan pesanan masuk untuk penjual (dengan ubah status)
- Registrasi dan login dengan dua peran: `penjual` dan `pembeli`

## Struktur Folder

```
marketplace/
├── index.php              Daftar produk
├── detail.php             Detail produk
├── cart.php               Keranjang belanja
├── checkout.php           Checkout + simpan transaksi
├── pesanan.php            Riwayat & detail pesanan pembeli
├── login.php / register.php / logout.php
├── produk/                Area penjual
│   ├── index.php          Daftar produk milik penjual
│   ├── form.php           Tambah & edit produk
│   ├── hapus.php          Hapus produk
│   └── pesanan_masuk.php  Pesanan masuk + ubah status
├── config/
│   ├── koneksi.php        Koneksi MySQLi
│   └── database.example.php   Contoh kredensial (salin jadi database.php)
├── includes/              header, footer, fungsi bantu
├── assets/                css, gambar, folder upload
├── api/                   REST API (Soal 12): produk, kategori, pesanan
├── postman/               Koleksi Postman untuk pengujian API
├── database/schema.sql    Skema tabel + data contoh
└── docs/                  Dokumen perancangan (PERANCANGAN.md), rancangan API (API.md), mockup
```

## Menjalankan di Komputer Sendiri (XAMPP / Laragon)

1. Salin folder ini ke `htdocs/marketplace`.
2. Buka phpMyAdmin, buat database `marketplace`, lalu **Import** `database/schema.sql`.
3. Salin `config/database.example.php` menjadi `config/database.php` dan isi:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'marketplace');
   ```
4. Buka `http://localhost/marketplace/`.

Akun demo (password `password123`): penjual `penjual@demo.com`, pembeli `pembeli@demo.com`.

## Deployment ke InfinityFree

1. Daftar di https://infinityfree.com, lalu buat akun hosting baru (pilih subdomain gratis atau domain sendiri).
2. Di panel **Client Area → Control Panel**, buka **MySQL Databases**, buat database baru. Catat **hostname** (misalnya `sql306.infinityfree.com`), **nama database**, **username**, dan **password**.
3. Buka **phpMyAdmin** dari panel, pilih database tadi, tab **Import**, unggah `database/schema.sql`.
4. Buat file `config/database.php` di komputer Anda (salin dari `database.example.php`), isi dengan data dari langkah 2.
5. Unggah isi folder proyek ke folder **`htdocs`** hosting lewat **Online File Manager** atau FTP (FileZilla). Unggah **isi** folder, sehingga `index.php` berada langsung di `htdocs/`. Jangan lupa `config/database.php` dan folder `assets/uploads/`.
6. Buka URL subdomain Anda. Bila halaman tampil dan data produk muncul, deployment berhasil.

Catatan: hosting gratis kadang menampilkan halaman peringatan "iframe/cookie" saat pertama dibuka; lanjutkan saja. Perubahan DNS subdomain baru bisa memakan waktu beberapa menit.

## Mengunggah ke GitHub (tanpa membocorkan kredensial)

File `config/database.php` sudah masuk `.gitignore`, jadi tidak ikut ter-commit. Jalankan dari folder proyek:

```bash
git init
git add .
git status                      # pastikan config/database.php TIDAK ada di daftar
git commit -m "Aplikasi web marketplace tradisional"
git branch -M main
git remote add origin https://github.com/USERNAME/NAMA-REPO.git
git push -u origin main
```

Buat repository kosong dulu di https://github.com/new (tanpa README bawaan), lalu ganti `USERNAME/NAMA-REPO`. Pastikan kode yang diunggah sama dengan yang di-deploy di InfinityFree.

## Checklist Laporan (Bagian C.j)

- [ ] URL demo InfinityFree: `https://...`
- [ ] URL repository GitHub: `https://github.com/...`
- [ ] Screenshot: halaman daftar produk, detail produk, keranjang, checkout, riwayat pesanan, CRUD produk
- [ ] Screenshot struktur database di phpMyAdmin (kelima tabel + tab *Designer* untuk relasi)
- [ ] Screenshot hasil pengujian transaksi: isi tabel `pesanan` dan `detail_pesanan` setelah checkout, serta stok `produk` yang berkurang
- [ ] Lampirkan `docs/PERANCANGAN.md` (entitas, tabel, ERD, alur transaksi), `docs/mockup.html`, dan source code

## Skenario Pengujian Transaksi

| No | Langkah | Hasil yang diharapkan |
|----|---------|-----------------------|
| 1 | Login pembeli, tambah 2 produk ke keranjang, checkout | Pesanan tersimpan, kode `INV...` tampil, keranjang kosong |
| 2 | Cek tabel `pesanan` dan `detail_pesanan` di phpMyAdmin | Ada 1 baris pesanan dan 2 baris detail dengan subtotal benar |
| 3 | Cek tabel `produk` | Stok berkurang sesuai jumlah dipesan |
| 4 | Tambah jumlah melebihi stok | Muncul peringatan, pesanan tidak dibuat |
| 5 | Login penjual, buka Pesanan Masuk, ubah status | Status di tabel `pesanan` berubah |
| 6 | Hapus produk yang sudah pernah dipesan | Ditolak dengan pesan, riwayat tetap utuh |

## REST API (Soal 12)

API memakai database yang sama dengan aplikasi web dan berjalan di XAMPP: `http://localhost/marketplace/api/`.

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/kategori.php` | Daftar kategori |
| GET | `/api/produk.php` , `?id=1` | Daftar produk / detail produk |
| POST / PUT / DELETE | `/api/produk.php` | Tambah / ubah / hapus produk (Basic Auth penjual) |
| GET / POST | `/api/pesanan.php` | Riwayat / buat pesanan (Basic Auth pembeli) |

Dokumen lengkap (rancangan endpoint, contoh JSON, skenario uji, daftar screenshot): `docs/API.md`.
Import `postman/PasarKita-API.postman_collection.json` ke Postman untuk menguji semua endpoint.
