<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT p.*, k.nama_kategori, u.nama AS nama_penjual, u.alamat AS alamat_penjual
       FROM produk p
       JOIN kategori k ON k.id_kategori = p.id_kategori
       JOIN pengguna u ON u.id_pengguna = p.id_penjual
      WHERE p.id_produk = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) {
    http_response_code(404);
    $judul = 'Produk tidak ditemukan';
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">Produk tidak ditemukan. '
       . '<a href="' . e(url('index.php')) . '">Kembali ke daftar produk</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$judul = $p['nama_produk'];
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>">Produk</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('index.php?kategori=' . (int) $p['id_kategori'])) ?>"><?= e($p['nama_kategori']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($p['nama_produk']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-md-6">
        <img src="<?= e(url_gambar($p['gambar'])) ?>" alt="<?= e($p['nama_produk']) ?>" class="img-detail shadow-sm">
    </div>
    <div class="col-md-6">
        <span class="badge text-bg-success mb-2"><?= e($p['nama_kategori']) ?></span>
        <h1 class="h3"><?= e($p['nama_produk']) ?></h1>
        <div class="display-6 harga mb-3"><?= e(rupiah($p['harga'])) ?></div>

        <dl class="row mb-3">
            <dt class="col-4">Stok</dt>
            <dd class="col-8">
                <?php if ((int) $p['stok'] > 0): ?>
                    <?= (int) $p['stok'] ?> tersedia
                <?php else: ?>
                    <span class="text-danger">Habis</span>
                <?php endif; ?>
            </dd>
            <dt class="col-4">Penjual</dt>
            <dd class="col-8"><i class="bi bi-shop"></i> <?= e($p['nama_penjual']) ?></dd>
            <?php if (!empty($p['alamat_penjual'])): ?>
                <dt class="col-4">Lokasi</dt>
                <dd class="col-8"><?= e($p['alamat_penjual']) ?></dd>
            <?php endif; ?>
        </dl>

        <h2 class="h6">Deskripsi</h2>
        <p class="text-muted"><?= nl2br(e($p['deskripsi'] ?: 'Belum ada deskripsi.')) ?></p>

        <?php if ((int) $p['stok'] > 0): ?>
            <form method="post" action="<?= e(url('cart.php')) ?>" class="d-flex flex-wrap gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tambah">
                <input type="hidden" name="id_produk" value="<?= (int) $p['id_produk'] ?>">
                <label for="jumlah" class="visually-hidden">Jumlah</label>
                <input type="number" id="jumlah" name="jumlah" class="form-control input-qty"
                       value="1" min="1" max="<?= (int) $p['stok'] ?>" required>
                <button type="submit" class="btn btn-success"><i class="bi bi-cart-plus"></i> Tambah ke Keranjang</button>
                <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary">Lanjut belanja</a>
            </form>
        <?php else: ?>
            <button class="btn btn-secondary" disabled>Stok habis</button>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
