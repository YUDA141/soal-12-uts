-- ==========================================================
--  Skema Database Marketplace Tradisional
--  Engine  : InnoDB (mendukung foreign key & transaksi)
--  Charset : utf8mb4
--  Cara pakai: import file ini lewat phpMyAdmin pada database
--  yang sudah dibuat di hosting (InfinityFree tidak mengizinkan
--  CREATE DATABASE, jadi buat database lewat panel hosting dulu).
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS detail_pesanan;
DROP TABLE IF EXISTS pesanan;
DROP TABLE IF EXISTS produk;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS pengguna;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------
-- 1. PENGGUNA (penjual dan pembeli)
-- ----------------------------------------------------------
CREATE TABLE pengguna (
    id_pengguna INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    no_hp       VARCHAR(20)  DEFAULT NULL,
    alamat      TEXT         DEFAULT NULL,
    role        ENUM('penjual','pembeli') NOT NULL DEFAULT 'pembeli',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pengguna),
    UNIQUE KEY uq_pengguna_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. KATEGORI
-- ----------------------------------------------------------
CREATE TABLE kategori (
    id_kategori   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama_kategori VARCHAR(80)  NOT NULL,
    PRIMARY KEY (id_kategori),
    UNIQUE KEY uq_kategori_nama (nama_kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. PRODUK (dimiliki oleh satu penjual, masuk satu kategori)
-- ----------------------------------------------------------
CREATE TABLE produk (
    id_produk   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_penjual  INT UNSIGNED NOT NULL,
    id_kategori INT UNSIGNED NOT NULL,
    nama_produk VARCHAR(150) NOT NULL,
    deskripsi   TEXT         DEFAULT NULL,
    harga       DECIMAL(12,2) NOT NULL,
    stok        INT UNSIGNED NOT NULL DEFAULT 0,
    gambar      VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_produk),
    KEY idx_produk_penjual (id_penjual),
    KEY idx_produk_kategori (id_kategori),
    CONSTRAINT fk_produk_penjual  FOREIGN KEY (id_penjual)
        REFERENCES pengguna (id_pengguna) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_produk_kategori FOREIGN KEY (id_kategori)
        REFERENCES kategori (id_kategori) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. PESANAN (header transaksi, dibuat oleh pembeli)
-- ----------------------------------------------------------
CREATE TABLE pesanan (
    id_pesanan        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode_pesanan      VARCHAR(20)  NOT NULL,
    id_pembeli        INT UNSIGNED NOT NULL,
    tanggal_pesanan   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_harga       DECIMAL(14,2) NOT NULL,
    status            ENUM('menunggu','diproses','dikirim','selesai','dibatalkan')
                      NOT NULL DEFAULT 'menunggu',
    nama_penerima     VARCHAR(100) NOT NULL,
    no_hp_penerima    VARCHAR(20)  NOT NULL,
    alamat_pengiriman TEXT         NOT NULL,
    metode_pembayaran ENUM('transfer','cod') NOT NULL DEFAULT 'transfer',
    catatan           VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id_pesanan),
    UNIQUE KEY uq_pesanan_kode (kode_pesanan),
    KEY idx_pesanan_pembeli (id_pembeli),
    CONSTRAINT fk_pesanan_pembeli FOREIGN KEY (id_pembeli)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. DETAIL_PESANAN (item per pesanan; harga disalin saat checkout)
-- ----------------------------------------------------------
CREATE TABLE detail_pesanan (
    id_detail    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pesanan   INT UNSIGNED NOT NULL,
    id_produk    INT UNSIGNED NOT NULL,
    jumlah       INT UNSIGNED NOT NULL,
    harga_satuan DECIMAL(12,2) NOT NULL,
    subtotal     DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (id_detail),
    KEY idx_detail_pesanan (id_pesanan),
    KEY idx_detail_produk (id_produk),
    CONSTRAINT fk_detail_pesanan FOREIGN KEY (id_pesanan)
        REFERENCES pesanan (id_pesanan) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detail_produk  FOREIGN KEY (id_produk)
        REFERENCES produk (id_produk) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
--  DATA CONTOH (akun demo, password keduanya: password123)
--  Ganti / hapus akun demo ini setelah pengujian selesai.
-- ==========================================================
INSERT INTO pengguna (nama, email, password, no_hp, alamat, role) VALUES
('Toko Sumber Rejeki', 'penjual@demo.com',
 '$2y$10$HX5E4zNIwxi9XJxFVnv2M.ijvtyeRT2o9B7EIjh6suFCsFEY86M22',
 '081234567890', 'Pasar Minggu, Kota Bengkulu', 'penjual'),
('Warung Bu Siti', 'penjual2@demo.com',
 '$2y$10$HX5E4zNIwxi9XJxFVnv2M.ijvtyeRT2o9B7EIjh6suFCsFEY86M22',
 '081298765432', 'Pasar Panorama, Kota Bengkulu', 'penjual'),
('Pembeli Demo', 'pembeli@demo.com',
 '$2y$10$HX5E4zNIwxi9XJxFVnv2M.ijvtyeRT2o9B7EIjh6suFCsFEY86M22',
 '085212345678', 'Jl. Raya No. 10, Kota Bengkulu', 'pembeli');

INSERT INTO kategori (nama_kategori) VALUES
('Sayur & Buah'),
('Sembako'),
('Makanan & Jajanan'),
('Kerajinan Tangan'),
('Pakaian');

INSERT INTO produk (id_penjual, id_kategori, nama_produk, deskripsi, harga, stok) VALUES
(1, 1, 'Cabai Merah Keriting 500 gr', 'Cabai merah keriting segar langsung dari petani lokal, dipetik pagi hari.', 25000, 40),
(1, 1, 'Tomat Segar 1 kg', 'Tomat merah matang, cocok untuk sambal, sayur, dan jus.', 12000, 60),
(1, 2, 'Beras Pulen 5 kg', 'Beras pulen kualitas premium, tidak berbau apek, kemasan 5 kg.', 72000, 25),
(1, 2, 'Minyak Goreng 2 Liter', 'Minyak goreng kemasan pouch 2 liter, jernih dan tidak mudah berbusa.', 36000, 50),
(2, 3, 'Keripik Singkong Pedas', 'Keripik singkong renyah dengan bumbu pedas, kemasan 250 gr.', 15000, 80),
(2, 3, 'Kue Bay Tat Bengkulu', 'Kue khas Bengkulu dengan rasa manis dan tekstur lembut, isi 10 buah.', 45000, 30),
(2, 4, 'Anyaman Tikar Pandan', 'Tikar anyaman pandan buatan pengrajin lokal, ukuran 1,5 x 2 meter.', 85000, 12),
(2, 5, 'Kain Batik Besurek', 'Kain batik besurek motif kaligrafi khas Bengkulu, ukuran 2 meter.', 150000, 15);
