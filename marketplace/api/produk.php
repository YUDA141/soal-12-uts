<?php
/**
 * Endpoint produk.
 *   GET    /api/produk.php                => daftar produk (?q=, ?kategori=, ?page=, ?limit=)
 *   GET    /api/produk.php?id=1           => detail produk
 *   POST   /api/produk.php                => tambah produk        [Basic Auth: penjual]
 *   PUT    /api/produk.php?id=1           => ubah produk sendiri  [Basic Auth: penjual]
 *   DELETE /api/produk.php?id=1           => hapus produk sendiri [Basic Auth: penjual]
 */
require_once __DIR__ . '/bootstrap.php';

$metode = izinkan_metode(['GET', 'POST', 'PUT', 'DELETE']);

/* ---------------------------------------------------------------- */
/*  Validasi data produk (dipakai POST dan PUT)                       */
/* ---------------------------------------------------------------- */
/**
 * @param array $in    data mentah dari JSON
 * @param bool  $semua true = semua field wajib (POST); false = hanya field yang dikirim divalidasi (PUT)
 * @return array [data bersih, daftar error]
 */
function validasi_produk(array $in, bool $semua): array
{
    $err   = [];
    $bersih = [];

    if ($semua || array_key_exists('nama_produk', $in)) {
        $v = is_string($in['nama_produk'] ?? null) ? trim($in['nama_produk']) : '';
        if ($v === '' || mb_strlen($v) > 150) {
            $err['nama_produk'] = 'Wajib diisi, maksimal 150 karakter.';
        } else {
            $bersih['nama_produk'] = $v;
        }
    }

    if ($semua || array_key_exists('id_kategori', $in)) {
        $v = filter_var($in['id_kategori'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($v === false) {
            $err['id_kategori'] = 'Wajib berupa id kategori yang valid.';
        } else {
            $stmt = db()->prepare('SELECT 1 FROM kategori WHERE id_kategori = ?');
            $stmt->bind_param('i', $v);
            $stmt->execute();
            $ada = (bool) $stmt->get_result()->fetch_row();
            $stmt->close();
            if (!$ada) {
                $err['id_kategori'] = "Kategori dengan id $v tidak ditemukan.";
            } else {
                $bersih['id_kategori'] = $v;
            }
        }
    }

    if ($semua || array_key_exists('harga', $in)) {
        $v = $in['harga'] ?? null;
        if (!is_numeric($v) || (float) $v < 100 || (float) $v > 999999999) {
            $err['harga'] = 'Wajib berupa angka antara 100 dan 999.999.999.';
        } else {
            $bersih['harga'] = (float) $v;
        }
    }

    if ($semua || array_key_exists('stok', $in)) {
        $v = filter_var($in['stok'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
        if ($v === false) {
            $err['stok'] = 'Wajib berupa bilangan bulat 0 sampai 1.000.000.';
        } else {
            $bersih['stok'] = $v;
        }
    }

    if (array_key_exists('deskripsi', $in)) {
        if ($in['deskripsi'] !== null && !is_string($in['deskripsi'])) {
            $err['deskripsi'] = 'Harus berupa teks.';
        } else {
            $bersih['deskripsi'] = $in['deskripsi'] === null ? null : trim($in['deskripsi']);
        }
    }

    return [$bersih, $err];
}

function ambil_produk(int $id): ?array
{
    $stmt = db()->prepare(SQL_PRODUK . ' WHERE p.id_produk = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $b ?: null;
}

$id = param_id();

/* ================================================================ */
/*  GET                                                               */
/* ================================================================ */
if ($metode === 'GET') {

    // ---- Detail satu produk ----
    if ($id !== null) {
        $b = ambil_produk($id);
        if (!$b) {
            gagal(404, "Produk dengan id $id tidak ditemukan.");
        }
        sukses(format_produk($b), 'Detail produk berhasil diambil');
    }

    // ---- Daftar produk (filter + pagination) ----
    $cari     = trim((string) ($_GET['q'] ?? ''));
    $kategori = isset($_GET['kategori']) && $_GET['kategori'] !== ''
        ? filter_var($_GET['kategori'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : null;
    if ($kategori === false) {
        gagal(400, "Parameter 'kategori' harus berupa bilangan bulat positif.");
    }
    $halaman = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $limit   = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if ($halaman === false || $limit === false) {
        gagal(400, "Parameter 'page' harus >= 1 dan 'limit' harus antara 1 dan 100.");
    }

    $kondisi = [];
    $tipe    = '';
    $param   = [];
    if ($cari !== '') {
        $kondisi[] = '(p.nama_produk LIKE ? OR p.deskripsi LIKE ?)';
        $like = '%' . $cari . '%';
        $tipe .= 'ss';
        array_push($param, $like, $like);
    }
    if ($kategori !== null) {
        $kondisi[] = 'p.id_kategori = ?';
        $tipe .= 'i';
        $param[] = $kategori;
    }
    $where = $kondisi ? ' WHERE ' . implode(' AND ', $kondisi) : '';

    $db = db();

    $stmt = $db->prepare('SELECT COUNT(*) FROM produk p' . $where);
    if ($param) {
        $stmt->bind_param($tipe, ...$param);
    }
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $offset = ($halaman - 1) * $limit;
    $stmt = $db->prepare(SQL_PRODUK . $where . ' ORDER BY p.created_at DESC, p.id_produk DESC LIMIT ? OFFSET ?');
    $stmt->bind_param($tipe . 'ii', ...array_merge($param, [$limit, $offset]));
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    sukses(
        array_map('format_produk', $baris),
        'Daftar produk berhasil diambil',
        200,
        [
            'total'       => $total,
            'halaman'     => $halaman,
            'per_halaman' => $limit,
            'total_halaman' => max(1, (int) ceil($total / $limit)),
        ]
    );
}

/* ================================================================ */
/*  POST - tambah produk                                              */
/* ================================================================ */
if ($metode === 'POST') {
    if ($id !== null) {
        gagal(400, 'POST tidak memakai parameter id. Gunakan PUT untuk mengubah produk.');
    }
    $user = wajib_auth('penjual');
    [$data, $err] = validasi_produk(body_json(), true);
    if ($err) {
        gagal(422, 'Data produk tidak valid.', $err);
    }

    $deskripsi = $data['deskripsi'] ?? null;
    $stmt = db()->prepare(
        'INSERT INTO produk (id_penjual, id_kategori, nama_produk, deskripsi, harga, stok) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iissdi', $user['id'], $data['id_kategori'], $data['nama_produk'],
                      $deskripsi, $data['harga'], $data['stok']);
    $stmt->execute();
    $idBaru = $stmt->insert_id;
    $stmt->close();

    header('Location: ' . base_url_app() . '/api/produk.php?id=' . $idBaru);
    sukses(format_produk(ambil_produk($idBaru)), 'Produk berhasil ditambahkan', 201);
}

/* ================================================================ */
/*  PUT / DELETE - wajib id dan wajib pemilik                         */
/* ================================================================ */
if ($id === null) {
    gagal(400, "Parameter 'id' wajib diisi untuk $metode.");
}
$user   = wajib_auth('penjual');
$produk = ambil_produk($id);
if (!$produk) {
    gagal(404, "Produk dengan id $id tidak ditemukan.");
}
if ((int) $produk['id_penjual'] !== $user['id']) {
    gagal(403, 'Anda hanya dapat mengubah atau menghapus produk milik Anda sendiri.');
}

if ($metode === 'PUT') {
    $in = body_json();
    [$data, $err] = validasi_produk($in, false);
    if ($err) {
        gagal(422, 'Data produk tidak valid.', $err);
    }
    if (!$data) {
        gagal(422, 'Tidak ada field yang diubah. Kirim minimal satu dari: nama_produk, id_kategori, harga, stok, deskripsi.');
    }

    $akhir = array_merge(
        [
            'nama_produk' => $produk['nama_produk'],
            'id_kategori' => (int) $produk['id_kategori'],
            'harga'       => (float) $produk['harga'],
            'stok'        => (int) $produk['stok'],
            'deskripsi'   => $produk['deskripsi'],
        ],
        $data
    );

    $stmt = db()->prepare(
        'UPDATE produk SET id_kategori = ?, nama_produk = ?, deskripsi = ?, harga = ?, stok = ?
          WHERE id_produk = ? AND id_penjual = ?'
    );
    $stmt->bind_param('issdiii', $akhir['id_kategori'], $akhir['nama_produk'], $akhir['deskripsi'],
                      $akhir['harga'], $akhir['stok'], $id, $user['id']);
    $stmt->execute();
    $stmt->close();

    sukses(format_produk(ambil_produk($id)), 'Produk berhasil diperbarui');
}

// DELETE
try {
    $stmt = db()->prepare('DELETE FROM produk WHERE id_produk = ? AND id_penjual = ?');
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $ex) {
    // 1451 = masih direferensikan detail_pesanan (riwayat transaksi harus utuh)
    if ($ex->getCode() === 1451) {
        gagal(409, 'Produk tidak dapat dihapus karena sudah pernah dipesan. Ubah stok menjadi 0 agar tidak dijual lagi.');
    }
    throw $ex;
}

if (!empty($produk['gambar']) && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $produk['gambar'])) {
    $path = __DIR__ . '/../assets/uploads/' . $produk['gambar'];
    if (is_file($path)) {
        unlink($path);
    }
}

sukses(['id_produk' => $id], 'Produk berhasil dihapus');
