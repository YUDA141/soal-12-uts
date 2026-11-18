<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';

// ---------- Proses aksi keranjang (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int) ($_POST['id_produk'] ?? 0);

    if ($aksi === 'tambah') {
        $jumlah = max(1, (int) ($_POST['jumlah'] ?? 1));

        $stmt = $db->prepare('SELECT nama_produk, stok FROM produk WHERE id_produk = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$p) {
            flash('danger', 'Produk tidak ditemukan.');
        } else {
            $sudahAda = (int) ($_SESSION['keranjang'][$id] ?? 0);
            $baru     = $sudahAda + $jumlah;
            if ($baru > (int) $p['stok']) {
                flash('warning', 'Stok "' . $p['nama_produk'] . '" hanya ' . (int) $p['stok'] . ' item.');
            } else {
                $_SESSION['keranjang'][$id] = $baru;
                flash('success', '"' . $p['nama_produk'] . '" ditambahkan ke keranjang.');
            }
        }
        // Kembali ke halaman asal (hanya URL internal)
        $asal = $_SERVER['HTTP_REFERER'] ?? '';
        $host = parse_url($asal, PHP_URL_HOST);
        if ($asal !== '' && $host === ($_SERVER['HTTP_HOST'] ?? '') && strpos($asal, 'cart.php') === false) {
            header('Location: ' . $asal);
            exit;
        }
        redirect('cart.php');
    }

    if ($aksi === 'ubah') {
        $jumlah = (int) ($_POST['jumlah'] ?? 0);
        if (isset($_SESSION['keranjang'][$id])) {
            if ($jumlah <= 0) {
                unset($_SESSION['keranjang'][$id]);
            } else {
                $stmt = $db->prepare('SELECT stok FROM produk WHERE id_produk = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stok = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
                $stmt->close();

                if ($jumlah > $stok) {
                    $jumlah = $stok;
                    flash('warning', 'Jumlah disesuaikan dengan stok tersedia (' . $stok . ').');
                } else {
                    flash('success', 'Keranjang diperbarui.');
                }
                $_SESSION['keranjang'][$id] = $jumlah;
            }
        }
        redirect('cart.php');
    }

    if ($aksi === 'hapus') {
        unset($_SESSION['keranjang'][$id]);
        flash('info', 'Produk dihapus dari keranjang.');
        redirect('cart.php');
    }

    if ($aksi === 'kosongkan') {
        unset($_SESSION['keranjang']);
        flash('info', 'Keranjang dikosongkan.');
        redirect('cart.php');
    }

    redirect('cart.php');
}

// ---------- Tampilkan keranjang ----------
$judul = 'Keranjang Belanja';
$data  = keranjang_detail($db);
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-cart3"></i> Keranjang Belanja</h1>

<?php if (!$data['item']): ?>
    <div class="text-center py-5 bg-white rounded shadow-sm">
        <i class="bi bi-cart-x display-3 text-muted"></i>
        <p class="lead mt-2">Keranjang Anda masih kosong.</p>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-success">Mulai belanja</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-responsive bg-white rounded shadow-sm">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produk</th>
                            <th class="text-end">Harga</th>
                            <th style="width:150px">Jumlah</th>
                            <th class="text-end">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data['item'] as $i): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= e(url_gambar($i['gambar'])) ?>" class="img-thumb" alt="">
                                    <div>
                                        <a class="fw-semibold text-decoration-none"
                                           href="<?= e(url('detail.php?id=' . (int) $i['id_produk'])) ?>"><?= e($i['nama_produk']) ?></a>
                                        <div class="small text-muted"><?= e($i['nama_penjual']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end"><?= e(rupiah($i['harga'])) ?></td>
                            <td>
                                <form method="post" class="d-flex gap-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="ubah">
                                    <input type="hidden" name="id_produk" value="<?= (int) $i['id_produk'] ?>">
                                    <input type="number" name="jumlah" class="form-control form-control-sm"
                                           value="<?= (int) $i['jumlah'] ?>" min="0" max="<?= (int) $i['stok'] ?>"
                                           aria-label="Jumlah <?= e($i['nama_produk']) ?>">
                                    <button class="btn btn-outline-success btn-sm" title="Perbarui jumlah"><i class="bi bi-arrow-repeat"></i></button>
                                </form>
                            </td>
                            <td class="text-end fw-semibold"><?= e(rupiah($i['subtotal'])) ?></td>
                            <td class="text-end">
                                <form method="post" onsubmit="return confirm('Hapus produk ini dari keranjang?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id_produk" value="<?= (int) $i['id_produk'] ?>">
                                    <button class="btn btn-outline-danger btn-sm" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" class="mt-2" onsubmit="return confirm('Kosongkan seluruh keranjang?')">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="kosongkan">
                <button class="btn btn-link text-danger btn-sm p-0">Kosongkan keranjang</button>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Ringkasan Belanja</h2>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total item</span><span><?= (int) keranjang_jumlah_item() ?></span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2 mb-3">
                        <span class="fw-semibold">Total harga</span>
                        <span class="harga fs-5"><?= e(rupiah($data['total'])) ?></span>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="<?= e(url('checkout.php')) ?>" class="btn btn-success btn-lg">Checkout</a>
                        <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary">Lanjut belanja</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
