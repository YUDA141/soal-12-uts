<?php
/** Pesanan masuk untuk penjual: menampilkan item milik penjual + ubah status pesanan. */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/functions.php';

wajib_login('penjual');

$idPenjual   = user()['id'];
$statusValid = ['menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];

// ---------- Ubah status ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $idPesanan = (int) ($_POST['id_pesanan'] ?? 0);
    $status    = $_POST['status'] ?? '';

    if (in_array($status, $statusValid, true)) {
        // Hanya boleh mengubah pesanan yang memuat produk milik penjual ini
        $stmt = $db->prepare(
            'UPDATE pesanan SET status = ?
              WHERE id_pesanan = ?
                AND EXISTS (SELECT 1 FROM detail_pesanan d JOIN produk p ON p.id_produk = d.id_produk
                             WHERE d.id_pesanan = pesanan.id_pesanan AND p.id_penjual = ?)'
        );
        $stmt->bind_param('sii', $status, $idPesanan, $idPenjual);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Status pesanan diperbarui.');
    }
    redirect('produk/pesanan_masuk.php');
}

// ---------- Ambil item pesanan milik penjual ----------
$stmt = $db->prepare(
    'SELECT o.id_pesanan, o.kode_pesanan, o.tanggal_pesanan, o.status, o.nama_penerima,
            o.no_hp_penerima, o.alamat_pengiriman, o.metode_pembayaran,
            p.nama_produk, d.jumlah, d.harga_satuan, d.subtotal
       FROM detail_pesanan d
       JOIN produk  p ON p.id_produk  = d.id_produk
       JOIN pesanan o ON o.id_pesanan = d.id_pesanan
      WHERE p.id_penjual = ?
      ORDER BY o.tanggal_pesanan DESC, o.id_pesanan DESC'
);
$stmt->bind_param('i', $idPenjual);
$stmt->execute();
$baris = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Kelompokkan per pesanan
$pesanan = [];
foreach ($baris as $b) {
    $pesanan[$b['id_pesanan']]['info']    = $b;
    $pesanan[$b['id_pesanan']]['items'][] = $b;
    $pesanan[$b['id_pesanan']]['total']   = ($pesanan[$b['id_pesanan']]['total'] ?? 0) + (float) $b['subtotal'];
}

$judul = 'Pesanan Masuk';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-inbox"></i> Pesanan Masuk</h1>

<?php if (!$pesanan): ?>
    <div class="alert alert-secondary">Belum ada pesanan untuk produk Anda.</div>
<?php else: ?>
    <div class="row g-3">
    <?php foreach ($pesanan as $id => $row): $i = $row['info']; ?>
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <strong><?= e($i['kode_pesanan']) ?></strong>
                        <span class="text-muted small ms-2"><?= e(date('d M Y H:i', strtotime($i['tanggal_pesanan']))) ?></span>
                    </div>
                    <?= label_status($i['status']) ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <ul class="list-unstyled mb-2">
                                <?php foreach ($row['items'] as $it): ?>
                                    <li class="d-flex justify-content-between border-bottom py-1">
                                        <span><?= e($it['nama_produk']) ?> <span class="text-muted">&times; <?= (int) $it['jumlah'] ?></span></span>
                                        <span><?= e(rupiah($it['subtotal'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="d-flex justify-content-between fw-semibold">
                                <span>Total item Anda</span><span class="harga"><?= e(rupiah($row['total'])) ?></span>
                            </div>
                        </div>
                        <div class="col-md-5 mt-3 mt-md-0 small">
                            <div><strong>Penerima:</strong> <?= e($i['nama_penerima']) ?> (<?= e($i['no_hp_penerima']) ?>)</div>
                            <div><strong>Alamat:</strong> <?= e($i['alamat_pengiriman']) ?></div>
                            <div><strong>Pembayaran:</strong> <?= $i['metode_pembayaran'] === 'cod' ? 'COD' : 'Transfer bank' ?></div>

                            <form method="post" class="d-flex gap-2 mt-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_pesanan" value="<?= (int) $id ?>">
                                <label class="visually-hidden" for="status<?= (int) $id ?>">Status</label>
                                <select name="status" id="status<?= (int) $id ?>" class="form-select form-select-sm">
                                    <?php foreach ($statusValid as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $i['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-success btn-sm">Simpan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
