<?php
/** Template header + navbar. Set $judul sebelum meng-include file ini. */
$judul    = $judul ?? 'Beranda';
$notif    = flash();
$jmlKeranjang = keranjang_jumlah_item();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> | <?= e(NAMA_TOKO) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>">
            <i class="bi bi-shop me-1"></i><?= e(NAMA_TOKO) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama"
                aria-controls="menuUtama" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuUtama">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>">Produk</a></li>
                <?php if (is_role('penjual')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('produk/index.php')) ?>">Kelola Produk</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('produk/pesanan_masuk.php')) ?>">Pesanan Masuk</a></li>
                <?php elseif (is_role('pembeli')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('pesanan.php')) ?>">Pesanan Saya</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url('cart.php')) ?>">
                        <i class="bi bi-cart3"></i> Keranjang
                        <?php if ($jmlKeranjang > 0): ?>
                            <span class="badge rounded-pill bg-warning text-dark"><?= (int) $jmlKeranjang ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php if (is_login()): ?>
                    <li class="nav-item">
                        <span class="navbar-text text-white-50 small">
                            <i class="bi bi-person-circle"></i> <?= e(user()['nama']) ?> (<?= e(user()['role']) ?>)
                        </span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('logout.php')) ?>">Keluar</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>">Masuk</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm" href="<?= e(url('register.php')) ?>">Daftar</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
<?php if ($notif): ?>
    <div class="alert alert-<?= e($notif['tipe']) ?> alert-dismissible fade show" role="alert">
        <?= e($notif['pesan']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>
