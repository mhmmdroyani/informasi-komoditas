<?php
// admin/hapus_data.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/admin_activity.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
require_csrf();

$action = $_POST['action'] ?? 'single';

if ($action === 'batch_ids') {
    $jenis = $_POST['jenis_kegiatan'] ?? 'domestik_masuk';
    if (!in_array($jenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'])) {
        $jenis = 'domestik_masuk';
    }
    $kategori = $_POST['kategori'] ?? 'hewan';
    if (!in_array($kategori, ['hewan', 'ikan', 'tumbuhan'])) {
        $kategori = 'hewan';
    }

    $ids = isset($_POST['published_ids']) && is_array($_POST['published_ids']) ? array_map('intval', $_POST['published_ids']) : [];
    foreach (isset($_POST['published_groups']) && is_array($_POST['published_groups']) ? $_POST['published_groups'] : [] as $groupIds) {
        foreach (explode(',', (string)$groupIds) as $groupId) {
            $ids[] = (int)$groupId;
        }
    }
    $ids = array_values(array_unique(array_filter($ids, static function ($id) {
        return $id > 0;
    })));
    $periode = trim($_POST['periode'] ?? '');
    if (!empty($ids)) {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            http_response_code(400);
            exit('Periode data tidak valid.');
        }
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "DELETE FROM ekspor_komoditas
             WHERE id IN ($idPlaceholders)
               AND jenis_kegiatan = ?
               AND kategori = ?
               AND periode = ?"
        );
        $stmt->execute(array_merge($ids, [$jenis, $kategori, $periode]));
        log_admin_activity($pdo, 'hapus_data', sprintf('Menghapus %d baris data %s/%s periode %s.', $stmt->rowCount(), $kategori, $jenis, $periode));
    }

    $redirectParams = [
        'jenis' => $jenis,
        'kategori' => $kategori,
        'periode_a' => trim($_POST['periode_a'] ?? ''),
        'periode_b' => trim($_POST['periode_b'] ?? ''),
        'msg' => 'hapus_sukses',
    ];
    header('Location: kelola_data.php?' . http_build_query($redirectParams));
    exit;
}

$jenis = $_POST['jenis'] ?? $_POST['jenis_kegiatan'] ?? 'domestik_masuk';
if (!in_array($jenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'])) {
    $jenis = 'domestik_masuk';
}

$kategori = $_POST['kategori'] ?? 'hewan';
if (!in_array($kategori, ['hewan', 'ikan', 'tumbuhan'])) {
    $kategori = 'hewan';
}

$periode = $_POST['periode'] ?? '';
$periodeA = trim($_POST['periode_a'] ?? $periode);
$periodeB = trim($_POST['periode_b'] ?? $periode);

if ($action === 'group_ids') {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))),
        static function ($id) {
            return $id > 0;
        }
    )));
    if ($ids !== []) {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            http_response_code(400);
            exit('Periode data tidak valid.');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "DELETE FROM ekspor_komoditas
             WHERE id IN ($placeholders)
               AND jenis_kegiatan = ?
               AND kategori = ?
               AND periode = ?"
        );
        $stmt->execute(array_merge($ids, [$jenis, $kategori, $periode]));
        log_admin_activity($pdo, 'hapus_data', sprintf('Menghapus %d baris data %s/%s periode %s.', $stmt->rowCount(), $kategori, $jenis, $periode));
    }
} elseif ($action === 'single') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM ekspor_komoditas WHERE id = ?");
        $stmt->execute([$id]);
        log_admin_activity($pdo, 'hapus_data', sprintf('Menghapus data komoditas ID %d.', $id));
    }
} elseif ($action === 'batch_periode' && !empty($periode)) {
    $stmt = $pdo->prepare("DELETE FROM ekspor_komoditas WHERE jenis_kegiatan = ? AND kategori = ? AND periode = ?");
    $stmt->execute([$jenis, $kategori, $periode]);
    log_admin_activity($pdo, 'hapus_data', sprintf('Menghapus %d baris data %s/%s periode %s.', $stmt->rowCount(), $kategori, $jenis, $periode));
} elseif ($action === 'all_kategori') {
    $stmt = $pdo->prepare("DELETE FROM ekspor_komoditas WHERE jenis_kegiatan = ? AND kategori = ?");
    $stmt->execute([$jenis, $kategori]);
    log_admin_activity($pdo, 'hapus_data', sprintf('Menghapus seluruh data %s/%s (%d baris).', $kategori, $jenis, $stmt->rowCount()));
}

$redirectParams = [
    'jenis' => $jenis,
    'kategori' => $kategori,
    'periode_a' => $periodeA,
    'periode_b' => $periodeB,
    'msg' => 'hapus_sukses',
];
header('Location: kelola_data.php?' . http_build_query($redirectParams));
exit;
