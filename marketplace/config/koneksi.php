<?php
/**
 * Koneksi database (MySQLi) + konfigurasi dasar aplikasi.
 * Kredensial dibaca dari config/database.php yang TIDAK ikut ke GitHub.
 */

$fileKonfigurasi = __DIR__ . '/database.php';

if (!file_exists($fileKonfigurasi)) {
    http_response_code(500);
    exit('Konfigurasi database belum dibuat. Salin config/database.example.php '
       . 'menjadi config/database.php lalu isi kredensial database Anda.');
}

require_once $fileKonfigurasi;

// Deteksi otomatis URL dasar aplikasi bila tidak didefinisikan manual
if (!defined('BASE_URL')) {
    $root = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
    $app  = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
    $base = ($root !== '' && strpos($app, $root) === 0) ? substr($app, strlen($root)) : '';
    define('BASE_URL', rtrim($base, '/'));
}

define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');
define('NAMA_TOKO', 'PasarKita');

// Lempar exception agar error SQL mudah ditangani (tidak menampilkan detail ke pengunjung)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    error_log('Koneksi DB gagal: ' . $e->getMessage());
    exit('Maaf, koneksi ke database gagal. Periksa konfigurasi config/database.php.');
}
