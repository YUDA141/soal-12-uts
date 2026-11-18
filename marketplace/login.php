<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';

if (is_login()) {
    redirect('index.php');
}

$judul = 'Masuk';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT id_pengguna, nama, email, password, role FROM pengguna WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $akun = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Pesan error sengaja dibuat sama agar tidak membocorkan email yang terdaftar
    if (!$akun || !password_verify($password, $akun['password'])) {
        $error = 'Email atau password salah.';
    } else {
        session_regenerate_id(true); // cegah session fixation
        $_SESSION['user'] = [
            'id'    => (int) $akun['id_pengguna'],
            'nama'  => $akun['nama'],
            'email' => $akun['email'],
            'role'  => $akun['role'],
        ];

        // Kembali ke halaman tujuan semula bila ada (hanya path internal)
        $tujuan = $_SESSION['kembali_ke'] ?? '';
        unset($_SESSION['kembali_ke']);
        if ($tujuan !== '' && $tujuan[0] === '/' && strpos($tujuan, '//') !== 0) {
            header('Location: ' . $tujuan);
            exit;
        }
        redirect($akun['role'] === 'penjual' ? 'produk/index.php' : 'index.php');
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Masuk ke Akun</h1>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= e($email) ?>" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">Masuk</button>
                    </div>
                </form>
                <p class="text-center mt-3 mb-0 small">
                    Belum punya akun? <a href="<?= e(url('register.php')) ?>">Daftar sekarang</a>
                </p>
            </div>
        </div>
        <div class="alert alert-light border small mt-3">
            <strong>Akun demo</strong> (password: <code>password123</code>)<br>
            Penjual: <code>penjual@demo.com</code><br>
            Pembeli: <code>pembeli@demo.com</code>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
