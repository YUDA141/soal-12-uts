<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';

wajib_login('pembeli');

$data = keranjang_detail($db);
if (!$data['item']) {
    flash('warning', 'Keranjang masih kosong.');
    redirect('cart.php');
}

$judul  = 'Checkout';
$errors = [];

// Isi awal formulir dari profil pembeli
$stmt = $db->prepare('SELECT nama, no_hp, alamat FROM pengguna WHERE id_pengguna = ?');
$idPembeli = user()['id'];
$stmt->bind_param('i', $idPembeli);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc();
$stmt->close();

$old = [
    'nama_penerima'     => $profil['nama'] ?? '',
    'no_hp_penerima'    => $profil['no_hp'] ?? '',
    'alamat_pengiriman' => $profil['alamat'] ?? '',
    'metode_pembayaran' => 'transfer',
    'catatan'           => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (array_keys($old) as $k) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    if (!in_array($old['metode_pembayaran'], ['transfer', 'cod'], true)) {
        $old['metode_pembayaran'] = 'transfer';
    }

    if ($old['nama_penerima'] === '' || mb_strlen($old['nama_penerima']) > 100) {
        $errors[] = 'Nama penerima wajib diisi.';
    }
    if (!preg_match('/^[0-9+\- ]{8,20}$/', $old['no_hp_penerima'])) {
        $errors[] = 'Nomor HP penerima tidak valid.';
    }
    if ($old['alamat_pengiriman'] === '') {
        $errors[] = 'Alamat pengiriman wajib diisi.';
    }
    if (mb_strlen($old['catatan']) > 255) {
        $errors[] = 'Catatan maksimal 255 karakter.';
    }

    if (!$errors) {
        // ===== TRANSAKSI DATABASE: semua langkah berhasil, atau tidak sama sekali =====
        try {
            $db->begin_transaction();

            $total = 0.0;
            $baris = [];

            // 1) Kunci baris produk (FOR UPDATE), validasi stok & ambil harga TERBARU dari DB
            $cekStok = $db->prepare('SELECT nama_produk, harga, stok FROM produk WHERE id_produk = ? FOR UPDATE');
            foreach (keranjang() as $idProduk => $jumlah) {
                $idProduk = (int) $idProduk;
                $jumlah   = (int) $jumlah;
                $cekStok->bind_param('i', $idProduk);
                $cekStok->execute();
                $p = $cekStok->get_result()->fetch_assoc();

                if (!$p) {
                    throw new RuntimeException('Ada produk di keranjang yang sudah tidak tersedia.');
                }
                if ($jumlah > (int) $p['stok']) {
                    throw new RuntimeException('Stok "' . $p['nama_produk'] . '" tidak mencukupi (tersisa ' . (int) $p['stok'] . ').');
                }
                $sub    = $jumlah * (float) $p['harga'];
                $total += $sub;
                $baris[] = [$idProduk, $jumlah, (float) $p['harga'], $sub];
            }
            $cekStok->close();

            // 2) Simpan header pesanan
            $kode = 'INV' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            $ins  = $db->prepare(
                'INSERT INTO pesanan (kode_pesanan, id_pembeli, total_harga, nama_penerima, no_hp_penerima,
                                      alamat_pengiriman, metode_pembayaran, catatan)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->bind_param(
                'sidsssss',
                $kode, $idPembeli, $total, $old['nama_penerima'], $old['no_hp_penerima'],
                $old['alamat_pengiriman'], $old['metode_pembayaran'], $old['catatan']
            );
            $ins->execute();
            $idPesanan = $db->insert_id;
            $ins->close();

            // 3) Simpan detail pesanan + kurangi stok
            $insDetail = $db->prepare(
                'INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_satuan, subtotal)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $kurangi = $db->prepare('UPDATE produk SET stok = stok - ? WHERE id_produk = ?');
            foreach ($baris as [$idProduk, $jumlah, $harga, $sub]) {
                $insDetail->bind_param('iiidd', $idPesanan, $idProduk, $jumlah, $harga, $sub);
                $insDetail->execute();
                $kurangi->bind_param('ii', $jumlah, $idProduk);
                $kurangi->execute();
            }
            $insDetail->close();
            $kurangi->close();

            $db->commit();

            unset($_SESSION['keranjang']);
            flash('success', 'Pesanan berhasil dibuat dengan kode ' . $kode . '.');
            redirect('pesanan.php?id=' . $idPesanan);
        } catch (Throwable $ex) {
            $db->rollback();
            if ($ex instanceof RuntimeException) {
                $errors[] = $ex->getMessage();
            } else {
                error_log('Checkout gagal: ' . $ex->getMessage());
                $errors[] = 'Terjadi kesalahan saat menyimpan pesanan. Silakan coba lagi.';
            }
            $data = keranjang_detail($db); // segarkan tampilan ringkasan
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-bag-check"></i> Checkout</h1>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Data Pengiriman</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="nama_penerima" class="form-label">Nama penerima</label>
                        <input type="text" id="nama_penerima" name="nama_penerima" class="form-control"
                               maxlength="100" value="<?= e($old['nama_penerima']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="no_hp_penerima" class="form-label">No. HP penerima</label>
                        <input type="text" id="no_hp_penerima" name="no_hp_penerima" class="form-control"
                               maxlength="20" value="<?= e($old['no_hp_penerima']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="alamat_pengiriman" class="form-label">Alamat pengiriman</label>
                        <textarea id="alamat_pengiriman" name="alamat_pengiriman" rows="3"
                                  class="form-control" required><?= e($old['alamat_pengiriman']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="metode_pembayaran" class="form-label">Metode pembayaran</label>
                        <select id="metode_pembayaran" name="metode_pembayaran" class="form-select">
                            <option value="transfer" <?= $old['metode_pembayaran'] === 'transfer' ? 'selected' : '' ?>>Transfer bank</option>
                            <option value="cod" <?= $old['metode_pembayaran'] === 'cod' ? 'selected' : '' ?>>Bayar di tempat (COD)</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="catatan" class="form-label">Catatan untuk penjual (opsional)</label>
                        <input type="text" id="catatan" name="catatan" class="form-control" maxlength="255"
                               value="<?= e($old['catatan']) ?>">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">Buat Pesanan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Ringkasan Pesanan</h2>
                <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($data['item'] as $i): ?>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span><?= e($i['nama_produk']) ?> <span class="text-muted">&times; <?= (int) $i['jumlah'] ?></span></span>
                            <span><?= e(rupiah($i['subtotal'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">Total bayar</span>
                    <span class="harga fs-5"><?= e(rupiah($data['total'])) ?></span>
                </div>
                <a href="<?= e(url('cart.php')) ?>" class="btn btn-link px-0 mt-2">&laquo; Ubah keranjang</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
