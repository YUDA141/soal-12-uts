<?php
/**
 * Inti REST API: respons JSON, penanganan error, CORS, koneksi database, autentikasi.
 * Di-include oleh setiap endpoint di folder api/.
 *
 * Format respons seragam:
 *   sukses : {"status":"success","message":"...","data":...,"meta":{...}}
 *   gagal  : {"status":"error","message":"...","errors":{...}}
 */

declare(strict_types=1);

// Semua error PHP tidak boleh bocor sebagai HTML ke klien API
ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

/* ------------------------------------------------------------------ */
/*  Respons                                                            */
/* ------------------------------------------------------------------ */

/** Kirim respons JSON lalu hentikan eksekusi. */
function kirim(int $kode, array $isi): void
{
    http_response_code($kode);
    echo json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function sukses($data = null, string $pesan = 'OK', int $kode = 200, ?array $meta = null): void
{
    $isi = ['status' => 'success', 'message' => $pesan, 'data' => $data];
    if ($meta !== null) {
        $isi['meta'] = $meta;
    }
    kirim($kode, $isi);
}

function gagal(int $kode, string $pesan, ?array $errors = null): void
{
    $isi = ['status' => 'error', 'message' => $pesan];
    if ($errors) {
        $isi['errors'] = $errors;
    }
    kirim($kode, $isi);
}

// Error / exception yang tidak tertangani => 500 berformat JSON (detail hanya ke log server)
set_exception_handler(function (Throwable $ex): void {
    error_log('API error: ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    gagal(500, 'Terjadi kesalahan pada server.');
});
set_error_handler(function (int $no, string $msg, string $file, int $line): bool {
    // Peringatan ringan (notice/deprecated) tidak boleh menggagalkan request
    if (in_array($no, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return true;
    }
    throw new ErrorException($msg, 0, $no, $file, $line);
});

/* ------------------------------------------------------------------ */
/*  Request                                                            */
/* ------------------------------------------------------------------ */

/** Preflight CORS dijawab langsung; metode di luar daftar ditolak dengan 405. */
function izinkan_metode(array $diizinkan): string
{
    $metode = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($metode === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if (!in_array($metode, $diizinkan, true)) {
        header('Allow: ' . implode(', ', $diizinkan));
        gagal(405, 'Metode ' . $metode . ' tidak diizinkan. Gunakan: ' . implode(', ', $diizinkan) . '.');
    }
    return $metode;
}

/** Baca body JSON. Kembali array; berhenti dengan 400 bila bukan JSON objek yang valid. */
function body_json(): array
{
    $mentah = file_get_contents('php://input');
    if ($mentah === false || trim($mentah) === '') {
        gagal(400, 'Body request kosong. Kirim data dalam format JSON (Content-Type: application/json).');
    }
    $data = json_decode($mentah, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        gagal(400, 'Body request bukan JSON yang valid: ' . json_last_error_msg() . '.');
    }
    return $data;
}

/** Ambil parameter id dari query string; null bila tidak dikirim, 400 bila tidak valid. */
function param_id(string $nama = 'id'): ?int
{
    if (!isset($_GET[$nama]) || $_GET[$nama] === '') {
        return null;
    }
    $nilai = filter_var($_GET[$nama], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($nilai === false) {
        gagal(400, "Parameter '$nama' harus berupa bilangan bulat positif.");
    }
    return $nilai;
}

/* ------------------------------------------------------------------ */
/*  Database (koneksi dibuat saat pertama kali dibutuhkan)             */
/* ------------------------------------------------------------------ */

function db(): mysqli
{
    static $koneksi = null;
    if ($koneksi instanceof mysqli) {
        return $koneksi;
    }

    $berkas = __DIR__ . '/../config/database.php';
    if (!is_file($berkas)) {
        error_log('API: config/database.php belum dibuat.');
        gagal(500, 'Konfigurasi database server belum dibuat.');
    }
    require_once $berkas;

    try {
        $koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $koneksi->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $ex) {
        error_log('API: koneksi DB gagal: ' . $ex->getMessage());
        gagal(500, 'Koneksi ke database gagal.');
    }
    return $koneksi;
}

/* ------------------------------------------------------------------ */
/*  Autentikasi: HTTP Basic Auth memakai email + password akun         */
/* ------------------------------------------------------------------ */

/**
 * Wajib login. Kembali array pengguna ['id','nama','email','role'].
 * Bila $role diisi, hanya peran itu yang boleh lewat (403 untuk peran lain).
 */
function wajib_auth(?string $role = null): array
{
    $email = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass  = $_SERVER['PHP_AUTH_PW'] ?? null;

    // Sebagian konfigurasi Apache/CGI tidak mengisi PHP_AUTH_*; baca header mentah
    if ($email === null) {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (stripos($header, 'Basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded !== false && strpos($decoded, ':') !== false) {
                [$email, $pass] = explode(':', $decoded, 2);
            }
        }
    }

    if ($email === null || $pass === null || $email === '') {
        header('WWW-Authenticate: Basic realm="PasarKita API"');
        gagal(401, 'Autentikasi diperlukan. Gunakan Basic Auth dengan email dan password akun.');
    }

    $stmt = db()->prepare('SELECT id_pengguna, nama, email, password, role FROM pengguna WHERE email = ?');
    $emailKecil = strtolower(trim($email));
    $stmt->bind_param('s', $emailKecil);
    $stmt->execute();
    $akun = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$akun || !password_verify($pass, $akun['password'])) {
        header('WWW-Authenticate: Basic realm="PasarKita API"');
        gagal(401, 'Email atau password salah.');
    }
    if ($role !== null && $akun['role'] !== $role) {
        gagal(403, 'Akses ditolak. Endpoint ini khusus akun dengan peran ' . $role . '.');
    }

    return [
        'id'    => (int) $akun['id_pengguna'],
        'nama'  => $akun['nama'],
        'email' => $akun['email'],
        'role'  => $akun['role'],
    ];
}

/* ------------------------------------------------------------------ */
/*  Pembantu data                                                      */
/* ------------------------------------------------------------------ */

/** URL absolut aplikasi (tanpa slash akhir), mis. http://localhost/marketplace */
function base_url_app(): string
{
    $skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $folder = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x.php'))), '/');
    return $skema . '://' . $host . $folder;
}

function url_gambar_api(?string $nama): ?string
{
    if (!$nama || !preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $nama)) {
        return null;
    }
    return base_url_app() . '/assets/uploads/' . $nama;
}

/** Ubah satu baris produk dari database menjadi struktur JSON yang rapi dengan tipe data benar. */
function format_produk(array $b): array
{
    return [
        'id_produk'   => (int) $b['id_produk'],
        'nama_produk' => $b['nama_produk'],
        'deskripsi'   => $b['deskripsi'],
        'harga'       => (float) $b['harga'],
        'stok'        => (int) $b['stok'],
        'gambar_url'  => url_gambar_api($b['gambar'] ?? null),
        'kategori'    => [
            'id_kategori'   => (int) $b['id_kategori'],
            'nama_kategori' => $b['nama_kategori'],
        ],
        'penjual'     => [
            'id_penjual'   => (int) $b['id_penjual'],
            'nama_penjual' => $b['nama_penjual'],
        ],
        'created_at'  => $b['created_at'],
    ];
}

/** Kueri dasar produk lengkap dengan kategori dan penjual. */
const SQL_PRODUK = 'SELECT p.id_produk, p.id_penjual, p.id_kategori, p.nama_produk, p.deskripsi,
                           p.harga, p.stok, p.gambar, p.created_at,
                           k.nama_kategori, u.nama AS nama_penjual
                      FROM produk p
                      JOIN kategori k ON k.id_kategori = p.id_kategori
                      JOIN pengguna u ON u.id_pengguna = p.id_penjual';
