<?php
/** Hapus produk (DELETE) - hanya lewat POST + CSRF, hanya milik penjual yang login. */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/functions.php';

wajib_login('penjual');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('produk/index.php');
}
csrf_verify();

$id        = (int) ($_POST['id_produk'] ?? 0);
$idPenjual = user()['id'];

$stmt = $db->prepare('SELECT gambar FROM produk WHERE id_produk = ? AND id_penjual = ?');
$stmt->bind_param('ii', $id, $idPenjual);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produk) {
    flash('danger', 'Produk tidak ditemukan atau bukan milik Anda.');
    redirect('produk/index.php');
}

try {
    $stmt = $db->prepare('DELETE FROM produk WHERE id_produk = ? AND id_penjual = ?');
    $stmt->bind_param('ii', $id, $idPenjual);
    $stmt->execute();
    $stmt->close();

    hapus_gambar($produk['gambar']);
    unset($_SESSION['keranjang'][$id]);
    flash('success', 'Produk berhasil dihapus.');
} catch (mysqli_sql_exception $ex) {
    // Error 1451 = masih direferensikan oleh detail_pesanan (riwayat transaksi harus tetap utuh)
    if ($ex->getCode() === 1451) {
        flash('warning', 'Produk tidak dapat dihapus karena sudah pernah dipesan. Ubah stok menjadi 0 agar tidak dijual lagi.');
    } else {
        error_log('Hapus produk gagal: ' . $ex->getMessage());
        flash('danger', 'Terjadi kesalahan saat menghapus produk.');
    }
}

redirect('produk/index.php');
