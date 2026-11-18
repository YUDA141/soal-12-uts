<?php
/**
 * Fungsi bantu: sesi, keamanan (CSRF, escaping), autentikasi, keranjang, upload.
 * Wajib di-include SETELAH config/koneksi.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- Umum ---------- */

/** Escape output HTML (cegah XSS). */
function e($teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

/** Format angka ke Rupiah. */
function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Pesan sekali tampil (flash message). */
function flash(?string $tipe = null, ?string $pesan = null): ?array
{
    if ($tipe !== null && $pesan !== null) {
        $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
        return null;
    }
    $data = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $data;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Hentikan request POST bila token CSRF tidak valid. */
function csrf_verify(): void
{
    $kirim = $_POST['csrf'] ?? '';
    if (!is_string($kirim) || !hash_equals($_SESSION['csrf'] ?? '', $kirim)) {
        http_response_code(419);
        exit('Sesi formulir tidak valid atau sudah kedaluwarsa. Silakan muat ulang halaman.');
    }
}

/* ---------- Autentikasi ---------- */

function is_login(): bool
{
    return !empty($_SESSION['user']);
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_role(string $role): bool
{
    return is_login() && $_SESSION['user']['role'] === $role;
}

/** Paksa login (dan peran tertentu bila diisi). */
function wajib_login(?string $role = null): void
{
    if (!is_login()) {
        flash('warning', 'Silakan login terlebih dahulu.');
        $_SESSION['kembali_ke'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    if ($role !== null && !is_role($role)) {
        http_response_code(403);
        exit('Akses ditolak: halaman ini khusus ' . e($role) . '.');
    }
}

/* ---------- Keranjang (disimpan di session: [id_produk => jumlah]) ---------- */

function keranjang(): array
{
    return $_SESSION['keranjang'] ?? [];
}

function keranjang_jumlah_item(): int
{
    return array_sum(keranjang());
}

/**
 * Ambil isi keranjang lengkap dengan data produk terbaru dari database.
 * @return array{item: array, total: float}
 */
function keranjang_detail(mysqli $db): array
{
    $isi = keranjang();
    if (!$isi) {
        return ['item' => [], 'total' => 0.0];
    }

    $ids = array_map('intval', array_keys($isi));
    $placeholder = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT p.id_produk, p.nama_produk, p.harga, p.stok, p.gambar, u.nama AS nama_penjual
           FROM produk p JOIN pengguna u ON u.id_pengguna = p.id_penjual
          WHERE p.id_produk IN ($placeholder)"
    );
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $hasil = $stmt->get_result();

    $item  = [];
    $total = 0.0;
    $ada   = [];
    while ($baris = $hasil->fetch_assoc()) {
        $jumlah = (int) $isi[$baris['id_produk']];
        $baris['jumlah']   = $jumlah;
        $baris['subtotal'] = $jumlah * (float) $baris['harga'];
        $total += $baris['subtotal'];
        $item[] = $baris;
        $ada[$baris['id_produk']] = true;
    }
    $stmt->close();

    // Buang produk yang sudah dihapus penjual dari keranjang
    foreach ($ids as $id) {
        if (!isset($ada[$id])) {
            unset($_SESSION['keranjang'][$id]);
        }
    }
    return ['item' => $item, 'total' => $total];
}

/* ---------- Upload gambar ---------- */

/**
 * Validasi & simpan gambar produk.
 * @return string|null nama file tersimpan, null bila tidak ada file diunggah
 * @throws RuntimeException bila file tidak valid
 */
function simpan_gambar(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gagal mengunggah gambar.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar maksimal 2 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $izin = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($izin[$mime])) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $nama = bin2hex(random_bytes(8)) . '.' . $izin[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $nama)) {
        throw new RuntimeException('Gagal menyimpan gambar di server.');
    }
    return $nama;
}

function hapus_gambar(?string $nama): void
{
    if ($nama && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $nama)) {
        $path = UPLOAD_DIR . $nama;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function url_gambar(?string $nama): string
{
    if ($nama && is_file(UPLOAD_DIR . $nama)) {
        return UPLOAD_URL . rawurlencode($nama);
    }
    return url('assets/img/placeholder.svg');
}

/* ---------- Lain-lain ---------- */

function label_status(string $status): string
{
    $peta = [
        'menunggu'   => 'warning text-dark',
        'diproses'   => 'info text-dark',
        'dikirim'    => 'primary',
        'selesai'    => 'success',
        'dibatalkan' => 'danger',
    ];
    return '<span class="badge bg-' . ($peta[$status] ?? 'secondary') . '">'
         . e(ucfirst($status)) . '</span>';
}
