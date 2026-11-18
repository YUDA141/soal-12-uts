<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/functions.php';

wajib_login('penjual');

$idPenjual = user()['id'];

$stmt = $db->prepare(
    'SELECT p.id_produk, p.nama_produk, p.harga, p.stok, p.gambar, k.nama_kategori
       FROM produk p JOIN kategori k ON k.id_kategori = p.id_kategori
      WHERE p.id_penjual = ?
      ORDER BY p.created_at DESC, p.id_produk DESC'
);
$stmt->bind_param('i', $idPenjual);
$stmt->execute();
$daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$judul = 'Kelola Produk';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-box-seam"></i> Kelola Produk</h1>
    <a href="<?= e(url('produk/form.php')) ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tambah Produk</a>
</div>

<?php if (!$daftar): ?>
    <div class="text-center py-5 bg-white rounded shadow-sm">
        <p class="lead mb-3">Anda belum memiliki produk.</p>
        <a href="<?= e(url('produk/form.php')) ?>" class="btn btn-success">Tambah produk pertama</a>
    </div>
<?php else: ?>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">#</th>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th class="text-end">Harga</th>
                    <th class="text-center">Stok</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $no => $p): ?>
                <tr>
                    <td><?= $no + 1 ?></td>
                    <td>
                        <img src="<?= e(url_gambar($p['gambar'])) ?>" class="img-thumb me-2" alt="">
                        <a class="text-decoration-none" href="<?= e(url('detail.php?id=' . (int) $p['id_produk'])) ?>"><?= e($p['nama_produk']) ?></a>
                    </td>
                    <td><?= e($p['nama_kategori']) ?></td>
                    <td class="text-end"><?= e(rupiah($p['harga'])) ?></td>
                    <td class="text-center">
                        <?php if ((int) $p['stok'] === 0): ?>
                            <span class="badge bg-danger">Habis</span>
                        <?php else: ?>
                            <?= (int) $p['stok'] ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-outline-primary btn-sm"
                           href="<?= e(url('produk/form.php?id=' . (int) $p['id_produk'])) ?>"><i class="bi bi-pencil"></i> Edit</a>
                        <form method="post" action="<?= e(url('produk/hapus.php')) ?>" class="d-inline"
                              onsubmit="return confirm('Hapus produk &quot;<?= e(addslashes($p['nama_produk'])) ?>&quot;?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_produk" value="<?= (int) $p['id_produk'] ?>">
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
