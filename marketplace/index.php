<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';

$judul = 'Daftar Produk';

// ---- Parameter pencarian, filter, halaman ----
$cari     = trim($_GET['q'] ?? '');
$kategori = (int) ($_GET['kategori'] ?? 0);
$halaman  = max(1, (int) ($_GET['hal'] ?? 1));
$perHal   = 8;

// ---- Bangun kondisi WHERE secara dinamis (tetap memakai prepared statement) ----
$kondisi = [];
$tipe    = '';
$param   = [];

if ($cari !== '') {
    $kondisi[] = '(p.nama_produk LIKE ? OR p.deskripsi LIKE ?)';
    $like      = '%' . $cari . '%';
    $tipe     .= 'ss';
    array_push($param, $like, $like);
}
if ($kategori > 0) {
    $kondisi[] = 'p.id_kategori = ?';
    $tipe     .= 'i';
    $param[]   = $kategori;
}
$where = $kondisi ? 'WHERE ' . implode(' AND ', $kondisi) : '';

// ---- Hitung total untuk pagination ----
$stmt = $db->prepare("SELECT COUNT(*) FROM produk p $where");
if ($param) {
    $stmt->bind_param($tipe, ...$param);
}
$stmt->execute();
$totalData = (int) $stmt->get_result()->fetch_row()[0];
$stmt->close();

$totalHal = max(1, (int) ceil($totalData / $perHal));
$halaman  = min($halaman, $totalHal);
$offset   = ($halaman - 1) * $perHal;

// ---- Ambil data produk ----
$sql = "SELECT p.id_produk, p.nama_produk, p.harga, p.stok, p.gambar,
               k.nama_kategori, u.nama AS nama_penjual
          FROM produk p
          JOIN kategori k ON k.id_kategori = p.id_kategori
          JOIN pengguna u ON u.id_pengguna = p.id_penjual
          $where
         ORDER BY p.created_at DESC, p.id_produk DESC
         LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$tipe2  = $tipe . 'ii';
$param2 = array_merge($param, [$perHal, $offset]);
$stmt->bind_param($tipe2, ...$param2);
$stmt->execute();
$produk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$daftarKategori = $db->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')
                     ->fetch_all(MYSQLI_ASSOC);

/** Bangun query string pagination dengan mempertahankan filter. */
function link_halaman(int $hal): string
{
    $q = $_GET;
    $q['hal'] = $hal;
    return '?' . http_build_query($q);
}

require __DIR__ . '/includes/header.php';
?>

<section class="hero p-4 p-md-5 mb-4">
    <h1 class="fw-bold mb-2">Belanja Produk Lokal, Langsung dari Penjualnya</h1>
    <p class="mb-3 opacity-75">Sayur, sembako, jajanan, dan kerajinan khas daerah dalam satu tempat.</p>
    <form class="row g-2" method="get" action="">
        <div class="col-12 col-md-6">
            <input type="search" name="q" value="<?= e($cari) ?>" class="form-control form-control-lg"
                   placeholder="Cari produk..." aria-label="Cari produk">
        </div>
        <div class="col-8 col-md-4">
            <select name="kategori" class="form-select form-select-lg" aria-label="Filter kategori">
                <option value="0">Semua kategori</option>
                <?php foreach ($daftarKategori as $k): ?>
                    <option value="<?= (int) $k['id_kategori'] ?>" <?= $kategori === (int) $k['id_kategori'] ? 'selected' : '' ?>>
                        <?= e($k['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4 col-md-2 d-grid">
            <button class="btn btn-warning btn-lg" type="submit"><i class="bi bi-search"></i> Cari</button>
        </div>
    </form>
</section>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Produk <span class="text-muted fw-normal">(<?= $totalData ?> ditemukan)</span></h2>
    <?php if ($cari !== '' || $kategori > 0): ?>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary btn-sm">Reset filter</a>
    <?php endif; ?>
</div>

<?php if (!$produk): ?>
    <div class="alert alert-secondary">Produk tidak ditemukan. Coba kata kunci atau kategori lain.</div>
<?php else: ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
        <?php foreach ($produk as $p): ?>
            <div class="col">
                <div class="card card-produk h-100 shadow-sm">
                    <a href="<?= e(url('detail.php?id=' . (int) $p['id_produk'])) ?>">
                        <img src="<?= e(url_gambar($p['gambar'])) ?>" class="card-img-top img-produk"
                             alt="<?= e($p['nama_produk']) ?>" loading="lazy">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <span class="badge text-bg-light align-self-start mb-1"><?= e($p['nama_kategori']) ?></span>
                        <h3 class="h6 nama-produk">
                            <a class="text-decoration-none text-dark"
                               href="<?= e(url('detail.php?id=' . (int) $p['id_produk'])) ?>"><?= e($p['nama_produk']) ?></a>
                        </h3>
                        <div class="harga mb-1"><?= e(rupiah($p['harga'])) ?></div>
                        <small class="text-muted mb-2"><i class="bi bi-shop"></i> <?= e($p['nama_penjual']) ?></small>

                        <div class="mt-auto">
                            <?php if ((int) $p['stok'] > 0): ?>
                                <form method="post" action="<?= e(url('cart.php')) ?>" class="d-grid">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="tambah">
                                    <input type="hidden" name="id_produk" value="<?= (int) $p['id_produk'] ?>">
                                    <input type="hidden" name="jumlah" value="1">
                                    <button class="btn btn-success btn-sm" type="submit">
                                        <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm w-100" disabled>Stok habis</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalHal > 1): ?>
        <nav class="mt-4" aria-label="Navigasi halaman">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $halaman <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(link_halaman($halaman - 1)) ?>">&laquo;</a>
                </li>
                <?php for ($i = 1; $i <= $totalHal; $i++): ?>
                    <li class="page-item <?= $i === $halaman ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(link_halaman($i)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $halaman >= $totalHal ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(link_halaman($halaman + 1)) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
