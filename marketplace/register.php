<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';

if (is_login()) {
    redirect('index.php');
}

$judul  = 'Daftar Akun';
$errors = [];
$old    = ['nama' => '', 'email' => '', 'no_hp' => '', 'alamat' => '', 'role' => 'pembeli'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['nama']   = trim($_POST['nama'] ?? '');
    $old['email']  = strtolower(trim($_POST['email'] ?? ''));
    $old['no_hp']  = trim($_POST['no_hp'] ?? '');
    $old['alamat'] = trim($_POST['alamat'] ?? '');
    $old['role']   = ($_POST['role'] ?? '') === 'penjual' ? 'penjual' : 'pembeli';
    $password      = $_POST['password'] ?? '';
    $konfirmasi    = $_POST['konfirmasi'] ?? '';

    if ($old['nama'] === '' || mb_strlen($old['nama']) > 100) {
        $errors[] = 'Nama wajib diisi (maksimal 100 karakter).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
        $errors[] = 'Format email tidak valid.';
    }
    if ($old['no_hp'] !== '' && !preg_match('/^[0-9+\- ]{8,20}$/', $old['no_hp'])) {
        $errors[] = 'Nomor HP hanya boleh berisi angka (8-20 karakter).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if ($password !== $konfirmasi) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $stmt = $db->prepare('SELECT 1 FROM pengguna WHERE email = ?');
        $stmt->bind_param('s', $old['email']);
        $stmt->execute();
        $sudahAda = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();

        if ($sudahAda) {
            $errors[] = 'Email sudah terdaftar. Silakan login.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            'INSERT INTO pengguna (nama, email, password, no_hp, alamat, role) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssss', $old['nama'], $old['email'], $hash, $old['no_hp'], $old['alamat'], $old['role']);
        $stmt->execute();
        $stmt->close();

        flash('success', 'Pendaftaran berhasil. Silakan login.');
        redirect('login.php');
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Daftar Akun Baru</h1>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama lengkap / nama toko</label>
                        <input type="text" id="nama" name="nama" class="form-control" maxlength="100"
                               value="<?= e($old['nama']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="150"
                               value="<?= e($old['email']) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="konfirmasi" class="form-label">Ulangi password</label>
                            <input type="password" id="konfirmasi" name="konfirmasi" class="form-control" minlength="8" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="no_hp" class="form-label">No. HP</label>
                        <input type="text" id="no_hp" name="no_hp" class="form-control" maxlength="20"
                               value="<?= e($old['no_hp']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="2" class="form-control"><?= e($old['alamat']) ?></textarea>
                    </div>
                    <div class="mb-4">
                        <span class="form-label d-block">Daftar sebagai</span>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="role" id="rolePembeli" value="pembeli"
                                   <?= $old['role'] === 'pembeli' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="rolePembeli">Pembeli</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="role" id="rolePenjual" value="penjual"
                                   <?= $old['role'] === 'penjual' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="rolePenjual">Penjual</label>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">Daftar</button>
                    </div>
                </form>
                <p class="text-center mt-3 mb-0 small">
                    Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk di sini</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
