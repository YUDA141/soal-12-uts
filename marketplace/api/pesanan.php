<?php
/**
 * Endpoint pesanan (semua wajib Basic Auth sebagai pembeli).
 *   GET  /api/pesanan.php        => daftar pesanan milik pembeli yang login
 *   GET  /api/pesanan.php?id=1   => detail pesanan (hanya milik sendiri)
 *   POST /api/pesanan.php        => buat pesanan baru
 *
 * Contoh body POST:
 * {
 *   "nama_penerima": "Pembeli Demo",
 *   "no_hp_penerima": "085212345678",
 *   "alamat_pengiriman": "Jl. Raya No. 10, Bengkulu",
 *   "metode_pembayaran": "transfer",          // transfer | cod
 *   "catatan": "Tolong dibungkus rapi",       // opsional
 *   "items": [ {"id_produk": 1, "jumlah": 2}, {"id_produk": 3, "jumlah": 1} ]
 * }
 */
require_once __DIR__ . '/bootstrap.php';

$metode = izinkan_metode(['GET', 'POST']);
$user   = wajib_auth('pembeli');
$db     = db();

/** Ambil header pesanan milik pembeli (null bila tidak ada / bukan miliknya). */
function ambil_pesanan(int $idPesanan, int $idPembeli): ?array
{
    $stmt = db()->prepare('SELECT * FROM pesanan WHERE id_pesanan = ? AND id_pembeli = ?');
    $stmt->bind_param('ii', $idPesanan, $idPembeli);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $b ?: null;
}

function ambil_item(int $idPesanan): array
{
    $stmt = db()->prepare(
        'SELECT d.id_produk, p.nama_produk, d.jumlah, d.harga_satuan, d.subtotal
           FROM detail_pesanan d JOIN produk p ON p.id_produk = d.id_produk
          WHERE d.id_pesanan = ? ORDER BY d.id_detail'
    );
    $stmt->bind_param('i', $idPesanan);
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static fn(array $d): array => [
        'id_produk'    => (int) $d['id_produk'],
        'nama_produk'  => $d['nama_produk'],
        'jumlah'       => (int) $d['jumlah'],
        'harga_satuan' => (float) $d['harga_satuan'],
        'subtotal'     => (float) $d['subtotal'],
    ], $baris);
}

function format_pesanan(array $p, bool $denganItem): array
{
    $hasil = [
        'id_pesanan'        => (int) $p['id_pesanan'],
        'kode_pesanan'      => $p['kode_pesanan'],
        'tanggal_pesanan'   => $p['tanggal_pesanan'],
        'status'            => $p['status'],
        'total_harga'       => (float) $p['total_harga'],
        'nama_penerima'     => $p['nama_penerima'],
        'no_hp_penerima'    => $p['no_hp_penerima'],
        'alamat_pengiriman' => $p['alamat_pengiriman'],
        'metode_pembayaran' => $p['metode_pembayaran'],
        'catatan'           => $p['catatan'],
    ];
    if ($denganItem) {
        $hasil['items'] = ambil_item((int) $p['id_pesanan']);
    }
    return $hasil;
}

/* ================================================================ */
/*  GET                                                               */
/* ================================================================ */
if ($metode === 'GET') {
    $id = param_id();

    if ($id !== null) {
        $p = ambil_pesanan($id, $user['id']);
        if (!$p) {
            gagal(404, "Pesanan dengan id $id tidak ditemukan.");
        }
        sukses(format_pesanan($p, true), 'Detail pesanan berhasil diambil');
    }

    $stmt = $db->prepare('SELECT * FROM pesanan WHERE id_pembeli = ? ORDER BY tanggal_pesanan DESC, id_pesanan DESC');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    sukses(
        array_map(static fn(array $p): array => format_pesanan($p, false), $baris),
        'Daftar pesanan berhasil diambil',
        200,
        ['total' => count($baris)]
    );
}

/* ================================================================ */
/*  POST - buat pesanan                                               */
/* ================================================================ */
$in  = body_json();
$err = [];

$namaPenerima = is_string($in['nama_penerima'] ?? null) ? trim($in['nama_penerima']) : '';
$noHp         = is_string($in['no_hp_penerima'] ?? null) ? trim($in['no_hp_penerima']) : '';
$alamat       = is_string($in['alamat_pengiriman'] ?? null) ? trim($in['alamat_pengiriman']) : '';
$pembayaran   = $in['metode_pembayaran'] ?? 'transfer';
$catatan      = isset($in['catatan']) && is_string($in['catatan']) ? trim($in['catatan']) : '';

if ($namaPenerima === '' || mb_strlen($namaPenerima) > 100) {
    $err['nama_penerima'] = 'Wajib diisi, maksimal 100 karakter.';
}
if (!preg_match('/^[0-9+\- ]{8,20}$/', $noHp)) {
    $err['no_hp_penerima'] = 'Wajib diisi, 8-20 karakter berisi angka, spasi, + atau -.';
}
if ($alamat === '') {
    $err['alamat_pengiriman'] = 'Wajib diisi.';
}
if (!in_array($pembayaran, ['transfer', 'cod'], true)) {
    $err['metode_pembayaran'] = "Harus 'transfer' atau 'cod'.";
}
if (mb_strlen($catatan) > 255) {
    $err['catatan'] = 'Maksimal 255 karakter.';
}

// ---- Validasi item: gabungkan id produk yang dobel ----
$gabung = [];
if (!isset($in['items']) || !is_array($in['items']) || !$in['items']) {
    $err['items'] = 'Wajib berupa array minimal 1 item: [{"id_produk":1,"jumlah":2}].';
} else {
    foreach ($in['items'] as $no => $item) {
        $idp = is_array($item) ? filter_var($item['id_produk'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $jml = is_array($item) ? filter_var($item['jumlah'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]) : false;
        if ($idp === false || $jml === false) {
            $err["items[$no]"] = "Harus memuat 'id_produk' (bilangan bulat positif) dan 'jumlah' (bilangan bulat >= 1).";
            continue;
        }
        $gabung[$idp] = ($gabung[$idp] ?? 0) + $jml;
    }
}

if ($err) {
    gagal(422, 'Data pesanan tidak valid.', $err);
}

// ---- Transaksi database: semua langkah berhasil, atau tidak sama sekali ----
try {
    $db->begin_transaction();

    $total = 0.0;
    $baris = [];
    $cek   = $db->prepare('SELECT nama_produk, harga, stok FROM produk WHERE id_produk = ? FOR UPDATE');

    ksort($gabung); // urutan tetap => mengurangi risiko deadlock antar-request
    foreach ($gabung as $idProduk => $jumlah) {
        $cek->bind_param('i', $idProduk);
        $cek->execute();
        $p = $cek->get_result()->fetch_assoc();

        if (!$p) {
            throw new DomainException("Produk dengan id $idProduk tidak ditemukan.|404");
        }
        if ($jumlah > (int) $p['stok']) {
            throw new DomainException('Stok "' . $p['nama_produk'] . '" tidak mencukupi (tersisa ' . (int) $p['stok'] . ').|409');
        }
        $sub     = $jumlah * (float) $p['harga'];
        $total  += $sub;
        $baris[] = [$idProduk, $jumlah, (float) $p['harga'], $sub];
    }
    $cek->close();

    $kode = 'INV' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    $ins  = $db->prepare(
        'INSERT INTO pesanan (kode_pesanan, id_pembeli, total_harga, nama_penerima, no_hp_penerima,
                              alamat_pengiriman, metode_pembayaran, catatan)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->bind_param('sidsssss', $kode, $user['id'], $total, $namaPenerima, $noHp, $alamat, $pembayaran, $catatan);
    $ins->execute();
    $idPesanan = $db->insert_id;
    $ins->close();

    $insDetail = $db->prepare(
        'INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)'
    );
    $kurangi = $db->prepare('UPDATE produk SET stok = stok - ? WHERE id_produk = ?');
    foreach ($baris as [$idProduk, $jumlah, $harga, $sub]) {
        $insDetail->bind_param('iiidd', $idPesanan, $idProduk, $jumlah, $harga, $sub);
        $insDetail->execute();
        $kurangi->bind_param('ii', $jumlah, $idProduk);
        $kurangi->execute();
    }
    $insDetail->close();
    $kurangi->close();

    $db->commit();
} catch (DomainException $ex) {
    $db->rollback();
    [$pesan, $kodeHttp] = explode('|', $ex->getMessage()) + [1 => '409'];
    gagal((int) $kodeHttp, $pesan);
} catch (Throwable $ex) {
    $db->rollback();
    throw $ex; // ditangani handler global => 500 JSON
}

header('Location: ' . base_url_app() . '/api/pesanan.php?id=' . $idPesanan);
sukses(format_pesanan(ambil_pesanan($idPesanan, $user['id']), true), 'Pesanan berhasil dibuat', 201);
