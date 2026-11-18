<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';

wajib_login('pembeli');

$idPembeli = user()['id'];
$id        = (int) ($_GET['id'] ?? 0);

// ================= DETAIL SATU PESANAN =================
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM pesanan WHERE id_pesanan = ? AND id_pembeli = ?');
    $stmt->bind_param('ii', $id, $idPembeli);
    $stmt->execute();
    $pesanan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pesanan) {
        http_response_code(404);
        $judul = 'Pesanan tidak ditemukan';
        require __DIR__ . '/includes/header.php';
        echo '<div class="alert alert-warning">Pesanan tidak ditemukan. '
           . '<a href="' . e(url('pesanan.php')) . '">Lihat semua pesanan</a></div>';
        require __DIR__ . '/includes/footer.php';
        exit;
    }

    $stmt = $db->prepare(
        'SELECT d.jumlah, d.harga_satuan, d.subtotal, p.nama_produk, p.gambar
           FROM detail_pesanan d JOIN produk p ON p.id_produk = d.id_produk
          WHERE d.id_pesanan = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $judul = 'Pesanan ' . $pesanan['kode_pesanan'];
    require __DIR__ . '/includes/header.php';
    ?>
    <a href="<?= e(url('pesanan.php')) ?>" class="btn btn-link px-0 mb-2">&laquo; Semua pesanan</a>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Pesanan <?= e($pesanan['kode_pesanan']) ?></h1>
        <?= label_status($pesanan['status']) ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-responsive bg-white rounded shadow-sm">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Produk</th><th class="text-end">Harga</th><th class="text-center">Jumlah</th><th class="text-end">Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <img src="<?= e(url_gambar($it['gambar'])) ?>" class="img-thumb me-2" alt="">
                                <?= e($it['nama_produk']) ?>
                            </td>
                            <td class="text-end"><?= e(rupiah($it['harga_satuan'])) ?></td>
                            <td class="text-center"><?= (int) $it['jumlah'] ?></td>
                            <td class="text-end"><?= e(rupiah($it['subtotal'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end harga"><?= e(rupiah($pesanan['total_harga'])) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6">Informasi Pesanan</h2>
                    <dl class="mb-0 small">
                        <dt>Tanggal</dt><dd><?= e(date('d M Y H:i', strtotime($pesanan['tanggal_pesanan']))) ?></dd>
                        <dt>Penerima</dt><dd><?= e($pesanan['nama_penerima']) ?> (<?= e($pesanan['no_hp_penerima']) ?>)</dd>
                        <dt>Alamat</dt><dd><?= nl2br(e($pesanan['alamat_pengiriman'])) ?></dd>
                        <dt>Pembayaran</dt><dd><?= $pesanan['metode_pembayaran'] === 'cod' ? 'Bayar di tempat (COD)' : 'Transfer bank' ?></dd>
                        <?php if (!empty($pesanan['catatan'])): ?>
                            <dt>Catatan</dt><dd><?= e($pesanan['catatan']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ================= DAFTAR PESANAN =================
$stmt = $db->prepare(
    'SELECT id_pesanan, kode_pesanan, tanggal_pesanan, total_harga, status
       FROM pesanan WHERE id_pembeli = ? ORDER BY tanggal_pesanan DESC, id_pesanan DESC'
);
$stmt->bind_param('i', $idPembeli);
$stmt->execute();
$daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$judul = 'Pesanan Saya';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-receipt"></i> Pesanan Saya</h1>

<?php if (!$daftar): ?>
    <div class="text-center py-5 bg-white rounded shadow-sm">
        <p class="lead mb-3">Anda belum memiliki pesanan.</p>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-success">Mulai belanja</a>
    </div>
<?php else: ?>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Kode</th><th>Tanggal</th><th class="text-end">Total</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $d): ?>
                <tr>
                    <td class="fw-semibold"><?= e($d['kode_pesanan']) ?></td>
                    <td><?= e(date('d M Y H:i', strtotime($d['tanggal_pesanan']))) ?></td>
                    <td class="text-end"><?= e(rupiah($d['total_harga'])) ?></td>
                    <td><?= label_status($d['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-outline-success btn-sm"
                           href="<?= e(url('pesanan.php?id=' . (int) $d['id_pesanan'])) ?>">Detail</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
