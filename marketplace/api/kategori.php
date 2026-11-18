<?php
/**
 * GET /api/kategori.php        => daftar kategori (beserta jumlah produk)
 * GET /api/kategori.php?id=1   => detail satu kategori
 */
require_once __DIR__ . '/bootstrap.php';

izinkan_metode(['GET']);

$id = param_id();
$db = db();

if ($id !== null) {
    $stmt = $db->prepare(
        'SELECT k.id_kategori, k.nama_kategori, COUNT(p.id_produk) AS jumlah_produk
           FROM kategori k LEFT JOIN produk p ON p.id_kategori = k.id_kategori
          WHERE k.id_kategori = ?
          GROUP BY k.id_kategori, k.nama_kategori'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $k = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$k) {
        gagal(404, "Kategori dengan id $id tidak ditemukan.");
    }
    sukses([
        'id_kategori'   => (int) $k['id_kategori'],
        'nama_kategori' => $k['nama_kategori'],
        'jumlah_produk' => (int) $k['jumlah_produk'],
    ], 'Detail kategori berhasil diambil');
}

$hasil = $db->query(
    'SELECT k.id_kategori, k.nama_kategori, COUNT(p.id_produk) AS jumlah_produk
       FROM kategori k LEFT JOIN produk p ON p.id_kategori = k.id_kategori
      GROUP BY k.id_kategori, k.nama_kategori
      ORDER BY k.nama_kategori'
);
$data = [];
while ($k = $hasil->fetch_assoc()) {
    $data[] = [
        'id_kategori'   => (int) $k['id_kategori'],
        'nama_kategori' => $k['nama_kategori'],
        'jumlah_produk' => (int) $k['jumlah_produk'],
    ];
}

sukses($data, 'Daftar kategori berhasil diambil', 200, ['total' => count($data)]);
