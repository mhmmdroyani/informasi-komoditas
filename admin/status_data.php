<?php
// admin/status_data.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/period_aggregation.php';
require_once __DIR__ . '/../includes/admin_activity.php';
require_admin();

$jenis = (string)($_POST['jenis'] ?? $_GET['jenis'] ?? '');
$kategori = (string)($_POST['kategori'] ?? $_GET['kategori'] ?? '');
$periodeA = (string)($_POST['periode_a'] ?? $_GET['periode_a'] ?? '');
$periodeB = (string)($_POST['periode_b'] ?? $_GET['periode_b'] ?? '');
$selectionKey = (string)($_POST['selection_key'] ?? $_GET['selection_key'] ?? '');
$selectedId = (int)($_POST['id'] ?? 0);
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$returnParams = [
    'jenis' => $jenis,
    'kategori' => $kategori,
    'periode_a' => $periodeA,
    'periode_b' => $periodeB,
];
$redirectToList = static function (string $error) use ($returnParams): void {
    header('Location: kelola_data.php?' . http_build_query($returnParams + ['error' => $error]));
    exit;
};

$isValidPeriod = static function (string $period): bool {
    return preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $matches)
        && checkdate((int)$matches[2], 1, (int)$matches[1]);
};

if (
    !in_array($jenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'], true)
    || !in_array($kategori, ['hewan', 'ikan', 'tumbuhan'], true)
    || !$isValidPeriod($periodeA)
    || !$isValidPeriod($periodeB)
    || !preg_match('/^[a-f0-9]{64}$/', $selectionKey)
) {
    $redirectToList('Filter atau pilihan data tidak valid.');
}

$rangeStart = min($periodeA, $periodeB);
$rangeEnd = max($periodeA, $periodeB);
$stmtRange = $pdo->prepare(
    "SELECT * FROM ekspor_komoditas
     WHERE jenis_kegiatan = ? AND kategori = ? AND periode >= ? AND periode <= ?
       AND periode REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'
     ORDER BY periode ASC, nama_komoditas ASC"
);
$stmtRange->execute([$jenis, $kategori, $rangeStart, $rangeEnd]);
$rangeRows = $stmtRange->fetchAll(PDO::FETCH_ASSOC);
$sourceIds = [];
$commodityName = '';
foreach (aggregate_commodity_rows($rangeRows) as $group) {
    if (hash_equals($group['_selection_key'], $selectionKey)) {
        $sourceIds = $group['_source_ids'];
        $commodityName = $group['nama_komoditas'];
        break;
    }
}

if ($sourceIds === []) {
    $redirectToList('Data komoditas tidak ditemukan pada rentang periode tersebut.');
}

$sourceRows = array_values(array_filter(
    $rangeRows,
    static fn(array $row): bool => in_array((int)$row['id'], $sourceIds, true)
));
usort($sourceRows, static function (array $left, array $right): int {
    return ((int)$left['is_published'] <=> (int)$right['is_published'])
        ?: strcmp((string)$left['periode'], (string)$right['periode'])
        ?: strcasecmp((string)$left['nama_komoditas'], (string)$right['nama_komoditas']);
});

if ($isPost) {
    require_csrf();
    $targetStatus = (string)($_POST['is_published'] ?? '');
    if (!in_array($targetStatus, ['0', '1'], true) || !in_array($selectedId, $sourceIds, true)) {
        $redirectToList('Record yang dipilih tidak sesuai dengan kelompok data.');
    }

    $currentRow = null;
    foreach ($sourceRows as $sourceRow) {
        if ((int)$sourceRow['id'] === $selectedId) {
            $currentRow = $sourceRow;
            break;
        }
    }
    if ($currentRow === null) {
        $redirectToList('Record tidak ditemukan pada rentang periode tersebut.');
    }

    $pdo->beginTransaction();
    try {
        $stmtUpdate = $pdo->prepare(
            'UPDATE ekspor_komoditas
             SET is_published = ?
             WHERE id = ? AND jenis_kegiatan = ? AND kategori = ? AND periode >= ? AND periode <= ?'
        );
        $stmtUpdate->execute([
            (int)$targetStatus,
            $selectedId,
            $jenis,
            $kategori,
            $rangeStart,
            $rangeEnd,
        ]);

        if ($stmtUpdate->rowCount() > 0) {
            log_admin_activity(
                $pdo,
                'publish_data',
                sprintf(
                    '%s data komoditas ID %d (%s/%s, periode %s).',
                    $targetStatus === '1' ? 'Mempublikasikan' : 'Meng-unpublish',
                    $selectedId,
                    $kategori,
                    $jenis,
                    $currentRow['periode']
                )
            );
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    header('Location: status_data.php?' . http_build_query($returnParams + [
        'selection_key' => $selectionKey,
        'msg' => 'status_diperbarui',
    ]));
    exit;
}

$msg = (string)($_GET['msg'] ?? '');
$labelRange = $rangeStart === $rangeEnd
    ? (format_label_periode_kode($rangeStart) ?: $rangeStart)
    : ((format_label_periode_kode($rangeStart) ?: $rangeStart) . ' - ' . (format_label_periode_kode($rangeEnd) ?: $rangeEnd));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Status Tayang - Admin Karantina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
</head>
<body>
    <?php require 'navbar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <div>
                <span class="eyebrow"><span class="dot dot-brand"></span> <?= htmlspecialchars(get_label_kegiatan($jenis)) ?> &bull; <?= ucfirst($kategori) ?></span>
                <h1 class="page-title">Kelola Status Tayang</h1>
                <p class="page-desc">
                    Pilih record <?= htmlspecialchars($commodityName) ?> yang ingin diubah statusnya. Rentang periode: <?= htmlspecialchars($labelRange) ?>.
                    Perubahan hanya berlaku pada record yang dipilih.
                </p>
            </div>
        </div>

        <?php if ($msg === 'status_diperbarui'): ?>
            <div class="alert alert-success" role="status" style="margin-bottom: 16px;">
                Status tayang berhasil diperbarui.
            </div>
        <?php endif; ?>

        <section class="card" style="overflow: hidden;">
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Nama Record</th>
                            <th>Klasifikasi</th>
                            <th style="text-align: right;">Frekuensi</th>
                            <th style="text-align: right;">Volume</th>
                            <th style="text-align: right;">Nilai (Rp)</th>
                            <th>Daerah Asal</th>
                            <th>Daerah Tujuan</th>
                            <th>Negara Tujuan</th>
                            <th style="text-align: center;">Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sourceRows as $sourceRow): ?>
                            <?php $isPublished = (int)$sourceRow['is_published'] === 1; ?>
                            <tr>
                                <td><?= htmlspecialchars(format_label_periode_kode($sourceRow['periode']) ?: $sourceRow['periode']) ?></td>
                                <td><?= htmlspecialchars($sourceRow['nama_komoditas']) ?></td>
                                <td><?= htmlspecialchars($sourceRow['klasifikasi'] ?? '-') ?></td>
                                <td style="text-align: right;"><?= number_format((int)$sourceRow['frekuensi'], 0, ',', '.') ?> kali</td>
                                <td style="text-align: right;"><?= number_format((float)$sourceRow['volume'], 2, ',', '.') ?> <?= htmlspecialchars($sourceRow['satuan'] ?? '') ?></td>
                                <td style="text-align: right; white-space: nowrap;">Rp <?= number_format((float)$sourceRow['nilai_ekspor'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($sourceRow['daerah_asal'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($sourceRow['daerah_tujuan'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($sourceRow['negara_tujuan'] ?? '-') ?></td>
                                <td style="text-align: center;">
                                    <span class="badge <?= $isPublished ? 'badge-brand' : 'badge-neutral' ?>"><?= $isPublished ? 'Published' : 'Draft' ?></span>
                                </td>
                                <td>
                                    <form method="POST" action="status_data.php" style="display: inline;" <?= $isPublished ? 'data-confirm="Unpublish data ' . htmlspecialchars($sourceRow['nama_komoditas'], ENT_QUOTES, 'UTF-8') . ' pada periode ' . htmlspecialchars(format_label_periode_kode($sourceRow['periode']) ?: $sourceRow['periode'], ENT_QUOTES, 'UTF-8') . '?"' : '' ?>>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="jenis" value="<?= htmlspecialchars($jenis) ?>">
                                        <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>">
                                        <input type="hidden" name="periode_a" value="<?= htmlspecialchars($periodeA) ?>">
                                        <input type="hidden" name="periode_b" value="<?= htmlspecialchars($periodeB) ?>">
                                        <input type="hidden" name="selection_key" value="<?= htmlspecialchars($selectionKey) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$sourceRow['id'] ?>">
                                        <input type="hidden" name="is_published" value="<?= $isPublished ? '0' : '1' ?>">
                                        <button type="submit" class="btn <?= $isPublished ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
                                            <?= $isPublished ? 'Unpublish' : 'Publish' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <a href="kelola_data.php?<?= htmlspecialchars(http_build_query($returnParams), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="margin-top: 16px;">Kembali ke Kelola Data</a>
    </main>
</body>
</html>
