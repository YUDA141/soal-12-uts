<?php
/**
 * CONTOH konfigurasi database.
 *
 * Cara pakai:
 *   1. Salin file ini menjadi  config/database.php
 *   2. Isi dengan data database dari panel InfinityFree
 *      (menu "MySQL Databases": hostname, nama DB, username, password).
 *   3. JANGAN pernah meng-commit config/database.php ke GitHub
 *      (sudah dimasukkan ke .gitignore).
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'marketplace');

// Opsional: isi bila aplikasi berada di subfolder dan URL tidak terdeteksi otomatis
// define('BASE_URL', '/marketplace');
