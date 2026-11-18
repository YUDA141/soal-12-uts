<?php
/** GET /api/  - daftar endpoint yang tersedia (dokumentasi singkat dalam JSON). */
require_once __DIR__ . '/bootstrap.php';

izinkan_metode(['GET']);

$base = base_url_app() . '/api';

sukses([
    'nama'    => 'PasarKita REST API',
    'versi'   => '1.0',
    'format'  => 'application/json',
    'auth'    => 'HTTP Basic Auth (email + password) untuk endpoint bertanda [auth]',
    'endpoint' => [
        ['GET',    "$base/kategori.php",            'Daftar kategori'],
        ['GET',    "$base/kategori.php?id=1",       'Detail satu kategori'],
        ['GET',    "$base/produk.php",              'Daftar produk (q, kategori, page, limit)'],
        ['GET',    "$base/produk.php?id=1",         'Detail satu produk'],
        ['POST',   "$base/produk.php",              '[auth penjual] Tambah produk'],
        ['PUT',    "$base/produk.php?id=1",         '[auth penjual] Ubah produk milik sendiri'],
        ['DELETE', "$base/produk.php?id=1",         '[auth penjual] Hapus produk milik sendiri'],
        ['GET',    "$base/pesanan.php",             '[auth pembeli] Daftar pesanan milik sendiri'],
        ['GET',    "$base/pesanan.php?id=1",        '[auth pembeli] Detail satu pesanan milik sendiri'],
        ['POST',   "$base/pesanan.php",             '[auth pembeli] Buat pesanan baru'],
    ],
], 'Selamat datang di PasarKita REST API');
