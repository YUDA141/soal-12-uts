# Dokumen Rancangan REST API PasarKita (Soal 12)

REST API ini dibangun di atas database marketplace dari Soal 11 (PHP Native + MySQLi), tanpa framework.
Semua endpoint ada di folder `api/`. Respons selalu berformat JSON (UTF-8).

---

## A.a Fungsi Web Service/API dan Alasan Diperlukan

**Web Service/API** adalah antarmuka yang membuat data dan fungsi sebuah aplikasi dapat dipakai aplikasi lain
lewat protokol standar (HTTP) dengan format data standar (JSON), tanpa aplikasi lain itu perlu tahu isi
database atau kode program di baliknya.

Dalam marketplace, API berfungsi sebagai **pintu resmi** untuk membaca dan mengubah data produk, kategori,
dan pesanan. Alasan API diperlukan untuk komunikasi antar-aplikasi:

1. **Memisahkan data dari tampilan.** Website PHP menampilkan HTML, sedangkan aplikasi mobile (Android/iOS)
   atau aplikasi pihak ketiga butuh data mentah. API menyediakan data itu dalam JSON.
2. **Satu sumber data untuk banyak klien.** Web, aplikasi mobile, dan marketplace mitra membaca database yang
   sama lewat API yang sama, sehingga data selalu konsisten.
3. **Keamanan.** Aplikasi lain tidak diberi akses langsung ke database (yang berisi password hash dan data
   pelanggan). Mereka hanya bisa melakukan operasi yang disediakan API, dan operasi sensitif dilindungi autentikasi.
4. **Interoperabilitas.** HTTP dan JSON dipahami semua bahasa pemrograman, jadi klien tidak harus memakai PHP.
5. **Kemudahan pengembangan.** Backend dan frontend dapat dikembangkan terpisah oleh tim berbeda selama
   kontrak API (endpoint dan format JSON) disepakati.

---

## A.b Rancangan Endpoint

Base URL lokal (XAMPP): `http://localhost/marketplace/api`

| No | Method | Endpoint | Fungsi | Auth | Kode sukses |
|----|--------|----------|--------|------|-------------|
| 1 | GET | `/api/` | Daftar endpoint tersedia | - | 200 |
| 2 | GET | `/api/kategori.php` | Daftar kategori + jumlah produk | - | 200 |
| 3 | GET | `/api/kategori.php?id=1` | Detail satu kategori | - | 200 |
| 4 | GET | `/api/produk.php` | Daftar produk (`q`, `kategori`, `page`, `limit`) | - | 200 |
| 5 | GET | `/api/produk.php?id=1` | Detail satu produk | - | 200 |
| 6 | POST | `/api/produk.php` | Tambah produk | Penjual | 201 |
| 7 | PUT | `/api/produk.php?id=1` | Ubah produk milik sendiri | Penjual | 200 |
| 8 | DELETE | `/api/produk.php?id=1` | Hapus produk milik sendiri | Penjual | 200 |
| 9 | GET | `/api/pesanan.php` | Daftar pesanan milik pembeli | Pembeli | 200 |
| 10 | GET | `/api/pesanan.php?id=1` | Detail pesanan milik pembeli | Pembeli | 200 |
| 11 | POST | `/api/pesanan.php` | Buat pesanan baru (transaksi DB) | Pembeli | 201 |

### Autentikasi

Endpoint yang mengubah data atau membaca data pribadi memakai **HTTP Basic Auth**: email dan password akun
yang sama dengan login website. Di Postman: tab *Authorization* → *Basic Auth*. Password dicek dengan
`password_verify()` terhadap hash di database. (Untuk produksi, gunakan HTTPS agar kredensial terenkripsi.)

Akun demo (password `password123`): penjual `penjual@demo.com`, pembeli `pembeli@demo.com`.

### Parameter daftar produk

| Parameter | Contoh | Keterangan |
|-----------|--------|------------|
| `q` | `?q=cabai` | Cari pada nama dan deskripsi |
| `kategori` | `?kategori=1` | Filter id kategori |
| `page` | `?page=2` | Halaman, minimal 1 (default 1) |
| `limit` | `?limit=5` | Item per halaman, 1-100 (default 10) |

### Kode status HTTP

| Kode | Arti | Contoh kasus |
|------|------|--------------|
| 200 | OK | Data berhasil dibaca/diubah/dihapus |
| 201 | Created | Produk atau pesanan berhasil dibuat |
| 204 | No Content | Preflight CORS (`OPTIONS`) |
| 400 | Bad Request | `id` bukan angka, body bukan JSON valid, `id` kosong untuk PUT/DELETE |
| 401 | Unauthorized | Tanpa kredensial, atau email/password salah |
| 403 | Forbidden | Peran salah (pembeli mengakses endpoint penjual) atau mengubah produk milik orang lain |
| 404 | Not Found | Produk, kategori, atau pesanan tidak ada |
| 405 | Method Not Allowed | Metode HTTP tidak didukung endpoint (header `Allow` diisi) |
| 409 | Conflict | Stok tidak cukup; produk tidak bisa dihapus karena sudah pernah dipesan |
| 422 | Unprocessable Entity | Data valid JSON tapi isinya tidak lolos validasi |
| 500 | Internal Server Error | Kesalahan server/database (detail hanya ditulis ke log, tidak dikirim ke klien) |

---

## A.c Struktur JSON Response

Format umum: sukses memuat `status`, `message`, `data`, dan (untuk daftar) `meta`. Gagal memuat `status: "error"`,
`message`, dan opsional `errors` per-field.

### Daftar produk — `GET /api/produk.php?limit=2`

```json
{
    "status": "success",
    "message": "Daftar produk berhasil diambil",
    "data": [
        {
            "id_produk": 8,
            "nama_produk": "Kain Batik Besurek",
            "deskripsi": "Kain batik besurek motif kaligrafi khas Bengkulu, ukuran 2 meter.",
            "harga": 150000,
            "stok": 15,
            "gambar_url": null,
            "kategori": { "id_kategori": 5, "nama_kategori": "Pakaian" },
            "penjual": { "id_penjual": 2, "nama_penjual": "Warung Bu Siti" },
            "created_at": "2026-11-12 10:52:00"
        },
        {
            "id_produk": 7,
            "nama_produk": "Anyaman Tikar Pandan",
            "deskripsi": "Tikar anyaman pandan buatan pengrajin lokal, ukuran 1,5 x 2 meter.",
            "harga": 85000,
            "stok": 12,
            "gambar_url": null,
            "kategori": { "id_kategori": 4, "nama_kategori": "Kerajinan Tangan" },
            "penjual": { "id_penjual": 2, "nama_penjual": "Warung Bu Siti" },
            "created_at": "2026-11-12 10:52:00"
        }
    ],
    "meta": { "total": 8, "halaman": 1, "per_halaman": 2, "total_halaman": 4 }
}
```

### Detail produk — `GET /api/produk.php?id=1`

```json
{
    "status": "success",
    "message": "Detail produk berhasil diambil",
    "data": {
        "id_produk": 1,
        "nama_produk": "Cabai Merah Keriting 500 gr",
        "deskripsi": "Cabai merah keriting segar langsung dari petani lokal, dipetik pagi hari.",
        "harga": 25000,
        "stok": 40,
        "gambar_url": null,
        "kategori": { "id_kategori": 1, "nama_kategori": "Sayur & Buah" },
        "penjual": { "id_penjual": 1, "nama_penjual": "Toko Sumber Rejeki" },
        "created_at": "2026-11-12 10:52:00"
    }
}
```

> Dua contoh di atas hanya ilustrasi struktur. Untuk laporan, gunakan respons asli dari API Anda
> (lihat bagian pengujian di bawah). `gambar_url` bernilai URL penuh bila produk punya gambar.

### Pesanan baru — `POST /api/pesanan.php`

Body request:

```json
{
    "nama_penerima": "Pembeli Demo",
    "no_hp_penerima": "085212345678",
    "alamat_pengiriman": "Jl. Raya No. 10, Kota Bengkulu",
    "metode_pembayaran": "transfer",
    "catatan": "Tolong dibungkus rapi",
    "items": [
        { "id_produk": 1, "jumlah": 2 },
        { "id_produk": 3, "jumlah": 1 }
    ]
}
```

Respons `201 Created` (bentuk):

```json
{
    "status": "success",
    "message": "Pesanan berhasil dibuat",
    "data": {
        "id_pesanan": 1,
        "kode_pesanan": "INV261114A1B2C3",
        "status": "menunggu",
        "total_harga": 122000,
        "items": [
            { "id_produk": 1, "nama_produk": "Cabai Merah Keriting 500 gr", "jumlah": 2, "harga_satuan": 25000, "subtotal": 50000 },
            { "id_produk": 3, "nama_produk": "Beras Pulen 5 kg", "jumlah": 1, "harga_satuan": 72000, "subtotal": 72000 }
        ]
    }
}
```

### Contoh respons error

```json
{ "status": "error", "message": "Produk dengan id 999 tidak ditemukan." }
```

```json
{
    "status": "error",
    "message": "Data pesanan tidak valid.",
    "errors": {
        "no_hp_penerima": "Wajib diisi, 8-20 karakter berisi angka, spasi, + atau -.",
        "items": "Wajib berupa array minimal 1 item: [{\"id_produk\":1,\"jumlah\":2}]."
    }
}
```

---

## Keamanan yang Diterapkan

- Prepared statement MySQLi di semua query (anti SQL injection).
- Password diverifikasi dengan `password_verify()`; hash tidak pernah dikirim ke klien.
- Pemeriksaan peran dan kepemilikan: penjual hanya bisa mengubah/menghapus produknya sendiri; pembeli hanya melihat pesanannya sendiri.
- Pesanan dibuat dalam transaksi database (`BEGIN`/`COMMIT`/`ROLLBACK`) dengan `SELECT ... FOR UPDATE` agar stok konsisten. Harga selalu diambil dari database, bukan dari request.
- Detail error server tidak dikirim ke klien (hanya ke log).

---

## B. Menjalankan di XAMPP

1. Jalankan **Apache** dan **MySQL** di XAMPP Control Panel.
2. Salin folder proyek ke `C:\xampp\htdocs\marketplace`.
3. Buka `http://localhost/phpmyadmin`, buat database `marketplace`, lalu **Import** `database/schema.sql`.
4. Salin `config/database.example.php` menjadi `config/database.php` dan isi:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'marketplace');
   ```
5. Buka `http://localhost/marketplace/api/` — harus muncul JSON daftar endpoint.
6. Buka `http://localhost/marketplace/api/produk.php` — harus muncul JSON 8 produk.

## C. Pengujian

Gunakan **Postman**: *Import* → pilih `postman/PasarKita-API.postman_collection.json`. Variabel `base_url`
sudah berisi `http://localhost/marketplace/api`. Folder `Penjual` dan `Pembeli` sudah memakai Basic Auth
akun demo.

### Skenario uji dan hasil yang diharapkan

| No | Request | Hasil yang diharapkan |
|----|---------|-----------------------|
| 1 | `GET /api/produk.php` | 200, array `data` berisi semua produk + `meta` |
| 2 | `GET /api/produk.php?id=1` | 200, objek satu produk |
| 3 | `GET /api/produk.php?id=999` | 404, `"Produk dengan id 999 tidak ditemukan."` |
| 4 | `GET /api/produk.php?id=abc` | 400, pesan parameter harus bilangan bulat |
| 5 | `GET /api/kategori.php` | 200, daftar kategori |
| 6 | `POST /api/pesanan.php` tanpa Authorization | 401 |
| 7 | `POST /api/pesanan.php` pembeli, body valid | 201, kode `INV...`; cek tabel `pesanan`, `detail_pesanan`, dan stok `produk` berkurang di phpMyAdmin |
| 8 | `POST /api/pesanan.php` dengan `jumlah` melebihi stok | 409, pesanan tidak tersimpan (rollback) |
| 9 | `POST /api/pesanan.php` body tanpa `items` | 422 + rincian `errors` |
| 10 | `POST /api/produk.php` penjual | 201, produk baru muncul di tabel `produk` |
| 11 | `POST /api/produk.php` dengan akun pembeli | 403 |
| 12 | `PUT /api/produk.php?id=1` penjual pemilik | 200, data berubah |
| 13 | `DELETE /api/produk.php?id=<produk baru>` | 200 |
| 14 | `DELETE` produk yang sudah pernah dipesan | 409 |

### Contoh uji lewat curl (opsional, selain Postman)

```bash
curl http://localhost/marketplace/api/produk.php
curl "http://localhost/marketplace/api/produk.php?id=1"
curl -u pembeli@demo.com:password123 -X POST http://localhost/marketplace/api/pesanan.php \
  -H "Content-Type: application/json" \
  -d '{"nama_penerima":"Pembeli Demo","no_hp_penerima":"085212345678","alamat_pengiriman":"Jl. Raya No. 10","metode_pembayaran":"cod","items":[{"id_produk":1,"jumlah":2}]}'
```

(Di Windows PowerShell, gunakan `curl.exe` dan escape tanda kutip, atau cukup pakai Postman.)

### Screenshot untuk laporan (butir C.h)

- [ ] Browser: URL `http://localhost/marketplace/api/produk.php` beserta JSON-nya
- [ ] Browser: URL `...produk.php?id=1` beserta JSON-nya
- [ ] Postman: GET daftar produk (tampilkan URL, method, status 200, body JSON)
- [ ] Postman: GET detail produk
- [ ] Postman: POST pesanan (tampilkan tab Body request dan response 201)
- [ ] Postman: salah satu kasus error (404, 401, atau 409)
- [ ] phpMyAdmin: isi tabel `pesanan` dan `detail_pesanan` setelah POST, serta stok `produk` yang berkurang
- [ ] XAMPP Control Panel: Apache dan MySQL berstatus Running
