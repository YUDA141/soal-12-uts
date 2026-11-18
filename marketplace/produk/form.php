<?php
/**
 * Formulir tambah / ubah produk (CREATE & UPDATE).
 * Tanpa ?id= => tambah produk baru.  Dengan ?id= => ubah produk milik penjual yang login.
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/functions.php';

wajib_login('penjual');

$idPenjual = user()['id'];
$id        = (int) ($_GET['id'] ?? 0);
$edit      = $id > 0;
$errors    = [];

$old = ['nama_produk' => '', 'id_kategori' => 0, 'harga' => '', 'stok' => '', 'deskripsi' => '', 'gambar' => null];

if ($edit) {
    // Pastikan produk ini milik penjual yang sedang login
    $stmt = $db->prepare('SELECT * FROM produk WHERE id_produk = ? AND id_penjual = ?');
    $stmt->bind_param('ii', $id, $idPenjual);
    $stmt->execute();
    $produk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$produk) {
        flash('danger', 'Produk tidak ditemukan atau bukan milik Anda.');
        redirect('produk/index.php');
    }
    $old = array_merge($old, $produk);
}

$daftarKategori = $db->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')
                     ->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['nama_produk'] = trim($_POST['nama_produk'] ?? '');
    $old['id_kategori'] = (int) ($_POST['id_kategori'] ?? 0);
    $old['harga']       = trim($_POST['harga'] ?? '');
    $old['stok']        = trim($_POST['stok'] ?? '');
    $old['deskripsi']   = trim($_POST['deskripsi'] ?? '');

    if ($old['nama_produk'] === '' || mb_strlen($old['nama_produk']) > 150) {
        $errors[] = 'Nama produk wajib diisi (maksimal 150 karakter).';
    }
    $idKategoriValid = array_map('intval', array_column($daftarKategori, 'id_kategori'));
    if (!in_array($old['id_kategori'], $idKategoriValid, true)) {
        $errors[] = 'Pilih kategori yang valid.';
    }
    if (!is_numeric($old['harga']) || (float) $old['harga'] < 100 || (float) $old['harga'] > 999999999) {
        $errors[] = 'Harga harus berupa angka minimal Rp 100.';
    }
    if (!ctype_digit($old['stok']) || (int) $old['stok'] > 1000000) {
        $errors[] = 'Stok harus berupa bilangan bulat 0 atau lebih.';
    }

    $gambarBaru = null;
    if (!$errors) {
        try {
            $gambarBaru = simpan_gambar($_FILES['gambar'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $harga = (float) $old['harga'];
        $stok  = (int) $old['stok'];

        if ($edit) {
            $gambarFinal = $gambarBaru ?? $old['gambar'];
            $stmt = $db->prepare(
                'UPDATE produk SET id_kategori = ?, nama_produk = ?, deskripsi = ?, harga = ?, stok = ?, gambar = ?
                  WHERE id_produk = ? AND id_penjual = ?'
            );
            $stmt->bind_param('issdisii', $old['id_kategori'], $old['nama_produk'], $old['deskripsi'],
                              $harga, $stok, $gambarFinal, $id, $idPenjual);
            $stmt->execute();
            $stmt->close();

            if ($gambarBaru !== null && !empty($produk['gambar'])) {
                hapus_gambar($produk['gambar']); // buang gambar lama yang sudah diganti
            }
            flash('success', 'Produk berhasil diperbarui.');
        } else {
            $stmt = $db->prepare(
                'INSERT INTO produk (id_penjual, id_kategori, nama_produk, deskripsi, harga, stok, gambar)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iissdis', $idPenjual, $old['id_kategori'], $old['nama_produk'],
                              $old['deskripsi'], $harga, $stok, $gambarBaru);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Produk baru berhasil ditambahkan.');
        }
        redirect('produk/index.php');
    }
}

$judul = $edit ? 'Edit Produk' : 'Tambah Produk';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3"><?= e($judul) ?></h1>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="nama_produk" class="form-label">Nama produk</label>
                        <input type="text" id="nama_produk" name="nama_produk" class="form-control"
                               maxlength="150" value="<?= e($old['nama_produk']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="id_kategori" class="form-label">Kategori</label>
                        <select id="id_kategori" name="id_kategori" class="form-select" required>
                            <option value="">-- Pilih kategori --</option>
                            <?php foreach ($daftarKategori as $k): ?>
                                <option value="<?= (int) $k['id_kategori'] ?>"
                                    <?= (int) $old['id_kategori'] === (int) $k['id_kategori'] ? 'selected' : '' ?>>
                                    <?= e($k['nama_kategori']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="harga" class="form-label">Harga (Rp)</label>
                            <input type="number" id="harga" name="harga" class="form-control" min="100" step="100"
                                   value="<?= e($old['harga'] !== '' ? (int) $old['harga'] : '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stok" class="form-label">Stok</label>
                            <input type="number" id="stok" name="stok" class="form-control" min="0"
                                   value="<?= e($old['stok']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" rows="4" class="form-control"><?= e($old['deskripsi']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="gambar" class="form-label">Gambar produk (JPG/PNG/WEBP, maks. 2 MB)</label>
                        <?php if ($edit && !empty($old['gambar'])): ?>
                            <div class="mb-2">
                                <img src="<?= e(url_gambar($old['gambar'])) ?>" class="img-thumb" alt="Gambar saat ini">
                                <small class="text-muted ms-2">Unggah file baru untuk mengganti.</small>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="gambar" name="gambar" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success"><?= $edit ? 'Simpan Perubahan' : 'Simpan Produk' ?></button>
                        <a href="<?= e(url('produk/index.php')) ?>" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
