<?php
// admin/kelola_data.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/period_aggregation.php';
require_admin();

$jenis = $_GET['jenis'] ?? 'domestik_masuk';
if (!in_array($jenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'])) {
    $jenis = 'domestik_masuk';
}

$kategori = $_GET['kategori'] ?? 'hewan';
if (!in_array($kategori, ['hewan', 'ikan', 'tumbuhan'])) {
    $kategori = 'hewan';
}

$periodeFilter = '';

// Ambil setting saat ini sesuai jenis & kategori
$stmtSet = $pdo->prepare("SELECT * FROM ekspor_settings WHERE jenis_kegiatan = ? AND kategori = ? LIMIT 1");
$stmtSet->execute([$jenis, $kategori]);
$setting = $stmtSet->fetch() ?: [
    'top_n' => 5,
    'urutkan_berdasarkan' => 'nilai_ekspor',
    'filter_mode' => 'tahun',
    'tanggal_awal' => '2026-01-01',
    'tanggal_akhir' => '2026-12-31',
    'tahun_awal' => 2026,
    'tahun_akhir' => 2026,
    'periode_label' => 'TAHUN 2026'
];

$filterMode = 'bulan';

// Ambil input kalender dari URL atau default setting
$calInput = $_GET;
if (!isset($_GET['thn_awal']) && !isset($_GET['tahun_awal']) && !isset($_GET['bln_awal']) && !isset($_GET['tgl_awal'])) {
    $calInput['thn_awal'] = $setting['tahun_awal'] ?? 2026;
    $calInput['thn_akhir'] = $setting['tahun_akhir'] ?? 2026;
    $calInput['tgl_awal'] = $setting['tanggal_awal'] ?? '2026-01-01';
    $calInput['tgl_akhir'] = $setting['tanggal_akhir'] ?? '2026-12-31';
    if (!empty($setting['tanggal_awal'])) {
        $calInput['bln_awal'] = date('Y-m', strtotime($setting['tanggal_awal']));
    }
    if (!empty($setting['tanggal_akhir'])) {
        $calInput['bln_akhir'] = date('Y-m', strtotime($setting['tanggal_akhir']));
    }
}

$cal = parse_calendar_filter($filterMode, $calInput);

$periodeStmt = $pdo->prepare("SELECT DISTINCT periode FROM ekspor_komoditas WHERE jenis_kegiatan = ? AND kategori = ? ORDER BY periode ASC");
$periodeStmt->execute([$jenis, $kategori]);
$periodeOptions = $periodeStmt->fetchAll(PDO::FETCH_COLUMN);

// Backward compatibility: a single `periode` value selects that month for both endpoints.
$legacyPeriod = trim($_GET['periode'] ?? '');
$requestedPeriodA = trim($_GET['periode_a'] ?? $legacyPeriod);
$requestedPeriodB = trim($_GET['periode_b'] ?? $legacyPeriod);
$validPeriods = array_values(array_filter($periodeOptions, static function ($period) {
    return is_string($period)
        && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)
        && checkdate((int)substr($period, 5, 2), 1, (int)substr($period, 0, 4));
}));
$periodSet = array_fill_keys($validPeriods, true);
$calendarStart = (string)($setting['tanggal_awal'] ?? '');
$calendarEnd = (string)($setting['tanggal_akhir'] ?? '');
if (
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $calendarStart)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $calendarEnd)
) {
    $monthStart = substr($calendarStart, 0, 7);
    $monthEnd = substr($calendarEnd, 0, 7);
    if (
        preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthStart)
        && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthEnd)
        && checkdate((int)substr($monthStart, 5, 2), 1, (int)substr($monthStart, 0, 4))
        && checkdate((int)substr($monthEnd, 5, 2), 1, (int)substr($monthEnd, 0, 4))
        && $monthStart <= $monthEnd
    ) {
        $month = new DateTimeImmutable($monthStart . '-01');
        $lastMonth = new DateTimeImmutable($monthEnd . '-01');
        $monthCount = 0;
        while ($month <= $lastMonth && $monthCount < 2400) {
            $periodSet[$month->format('Y-m')] = true;
            $month = $month->modify('+1 month');
            $monthCount++;
        }
    }
}
$validPeriods = array_keys($periodSet);
sort($validPeriods, SORT_STRING);
$defaultPeriodeB = (new DateTimeImmutable('first day of this month'))
    ->modify('-1 month')
    ->format('Y-m');
if (!in_array($defaultPeriodeB, $validPeriods, true)) {
    $availablePreviousPeriods = array_values(array_filter(
        $validPeriods,
        static function ($period) use ($defaultPeriodeB) {
            return $period <= $defaultPeriodeB;
        }
    ));
    $defaultPeriodeB = $availablePreviousPeriods !== []
        ? end($availablePreviousPeriods)
        : ($validPeriods[count($validPeriods) - 1] ?? '');
}
$periodeA = in_array($requestedPeriodA, $validPeriods, true)
    ? $requestedPeriodA
    : $defaultPeriodeB;
$periodeB = in_array($requestedPeriodB, $validPeriods, true)
    ? $requestedPeriodB
    : $defaultPeriodeB;
$periodeFilter = $periodeB;
$aggregateMode = $periodeA !== '' && $periodeB !== '' && $periodeA !== $periodeB;
$rangeStart = $periodeA !== '' && $periodeB !== '' ? min($periodeA, $periodeB) : '';
$rangeEnd = $periodeA !== '' && $periodeB !== '' ? max($periodeA, $periodeB) : '';
$rangePeriodLabel = $rangeStart === $rangeEnd
    ? (format_label_periode_kode($rangeStart) ?: $rangeStart)
    : ((format_label_periode_kode($rangeStart) ?: $rangeStart) . ' - ' . (format_label_periode_kode($rangeEnd) ?: $rangeEnd));

if ($periodeFilter !== '') {
    $stmtPeriodSet = $pdo->prepare("SELECT * FROM ekspor_settings WHERE jenis_kegiatan = ? AND kategori = ? AND periode = ? LIMIT 1");
    $stmtPeriodSet->execute([$jenis, $kategori, $periodeFilter]);
    $periodSetting = $stmtPeriodSet->fetch();
    if ($periodSetting) {
        $setting = array_merge($setting, $periodSetting);
    }
}

// Query the selected month or the inclusive period range.
$sql = "SELECT * FROM ekspor_komoditas WHERE jenis_kegiatan = :jenis AND kategori = :kategori";
$params = [':jenis' => $jenis, ':kategori' => $kategori];

if ($periodeA !== '' && $periodeB !== '') {
    $sql .= " AND periode >= :periode_awal AND periode <= :periode_akhir AND periode REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'";
    $params[':periode_awal'] = $rangeStart;
    $params[':periode_akhir'] = $rangeEnd;
} else {
    $sql .= " AND 1 = 0";
}

$urutanKolom = ($setting['urutkan_berdasarkan'] === 'frekuensi') ? 'frekuensi' : 'nilai_ekspor';
$sql .= " ORDER BY is_published DESC, {$urutanKolom} DESC";

$stmtData = $pdo->prepare($sql);
$stmtData->execute($params);
$dataItems = aggregate_commodity_rows($stmtData->fetchAll(PDO::FETCH_ASSOC));
usort($dataItems, static function ($left, $right) use ($urutanKolom) {
    return ($right[$urutanKolom] <=> $left[$urutanKolom])
        ?: strcasecmp($left['nama_komoditas'], $right['nama_komoditas']);
});

// Hitung statistik
$totalItem = count($dataItems);
$totalPublished = count(array_filter($dataItems, fn($r) => $r['is_published'] == 1));
$totalDraft = $totalItem - $totalPublished;

// Alert messages
$msg = $_GET['msg'] ?? '';
$err = $_GET['error'] ?? '';
$pubCount = $_GET['pub'] ?? 0;

$labelKegiatan = get_label_kegiatan($jenis);
$labelArah = get_label_arah($jenis);

// Label Nilai & Wilayah
$labelNilai = 'Nilai ' . $labelKegiatan;
if ($jenis === 'domestik_keluar' || $jenis === 'domestik_masuk') {
    $labelNilai = 'Nilai Komoditas';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data & Pengaturan Publik - Admin Karantina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
</head>
<body class="page-kelola-data">
    <?php require 'navbar.php'; ?>

    <div class="admin-main">
        <!-- Header -->
        <div class="page-header">
            <div>
                <span class="eyebrow"><span class="dot dot-brand"></span> <?= htmlspecialchars($labelKegiatan) ?> &bull; <?= ucfirst($kategori) ?></span>
                <h1 class="page-title">Kelola Data & Publikasi Board</h1>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                <a href="../embed/board.php?<?= htmlspecialchars(http_build_query(['jenis' => $jenis, 'kategori' => $kategori, 'periode_a' => $periodeA, 'periode_b' => $periodeB]), ENT_QUOTES, 'UTF-8') ?>" target="_self" class="btn btn-outline-brand">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    <span>Preview Board (<?= ucfirst($kategori) ?>)</span>
                </a>
                <a href="upload_<?= $kategori ?>.php?jenis=<?= $jenis ?>" class="btn btn-primary">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Upload Excel <?= ucfirst($kategori) ?></span>
                </a>
            </div>
        </div>

        <!-- Alert Banner -->
        <?php if ($msg === 'sukses'): ?>
            <div class="alert alert-success" style="justify-content: space-between; margin-bottom: 16px;">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span><strong>Tampilan board berhasil diperbarui!</strong> Sebanyak <strong><?= htmlspecialchars($pubCount) ?> data</strong> kini berstatus dipublikasikan.</span>
                </div>
                <a href="../embed/board.php?<?= htmlspecialchars(http_build_query(['jenis' => $jenis, 'kategori' => $kategori, 'periode_a' => $periodeA, 'periode_b' => $periodeB]), ENT_QUOTES, 'UTF-8') ?>" target="_self" style="font-size: 12px; font-weight: 600; white-space: nowrap;">Lihat Board &rarr;</a>
            </div>
        <?php elseif ($msg === 'hapus_sukses'): ?>
            <div class="alert alert-warning" style="margin-bottom: 16px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Data berhasil dihapus dari database.</span>
            </div>
        <?php elseif ($msg === 'edit_sukses'): ?>
            <div class="alert alert-success" style="margin-bottom: 16px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Data komoditas berhasil diperbarui.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($err)): ?>
            <div class="alert alert-danger" style="margin-bottom: 16px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>Terjadi kesalahan: <?= htmlspecialchars($err) ?></div>
            </div>
        <?php endif; ?>

        <!-- LEVEL 1 TABS: JENIS LALU LINTAS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 16px;">
            <a href="kelola_data.php?jenis=domestik_masuk&kategori=<?= $kategori ?>" class="radio-card <?= $jenis === 'domestik_masuk' ? 'checked' : '' ?>">
                <span class="fake-radio"></span>
                <div>
                    <div class="title">Domestik Masuk</div>
                    <div class="sub">Masuk Kalsel (Antar-Area)</div>
                </div>
            </a>
            <a href="kelola_data.php?jenis=domestik_keluar&kategori=<?= $kategori ?>" class="radio-card <?= $jenis === 'domestik_keluar' ? 'checked' : '' ?>">
                <span class="fake-radio"></span>
                <div>
                    <div class="title">Domestik Keluar</div>
                    <div class="sub">Keluar Kalsel (Antar-Area)</div>
                </div>
            </a>
            <a href="kelola_data.php?jenis=ekspor&kategori=<?= $kategori ?>" class="radio-card <?= $jenis === 'ekspor' ? 'checked' : '' ?>">
                <span class="fake-radio"></span>
                <div>
                    <div class="title">Ekspor</div>
                    <div class="sub">Ke Luar Negeri</div>
                </div>
            </a>
            <a href="kelola_data.php?jenis=impor&kategori=<?= $kategori ?>" class="radio-card <?= $jenis === 'impor' ? 'checked' : '' ?>">
                <span class="fake-radio"></span>
                <div>
                    <div class="title">Impor</div>
                    <div class="sub">Dari Luar Negeri</div>
                </div>
            </a>
        </div>

        <!-- LEVEL 2 TABS: KATEGORI KOMODITAS -->
        <div class="tab-row grid-3" style="margin-top: 10px;">
            <a href="kelola_data.php?jenis=<?= $jenis ?>&kategori=hewan" class="tab-link <?= $kategori === 'hewan' ? 'active' : '' ?>">
                <span class="dot dot-hewan"></span> Hewan (KH)
            </a>
            <a href="kelola_data.php?jenis=<?= $jenis ?>&kategori=ikan" class="tab-link <?= $kategori === 'ikan' ? 'active' : '' ?>">
                <span class="dot dot-ikan"></span> Ikan (KI)
            </a>
            <a href="kelola_data.php?jenis=<?= $jenis ?>&kategori=tumbuhan" class="tab-link <?= $kategori === 'tumbuhan' ? 'active' : '' ?>">
                <span class="dot dot-tumbuhan"></span> Tumbuhan (KT)
            </a>
        </div>

        <!-- FORM UTAMA: Pengaturan & Update Tampilan Publik -->
        <form method="POST" action="proses_update.php" id="formUpdatePublik">
            <?= csrf_field() ?>
            <input type="hidden" name="jenis_kegiatan" value="<?= htmlspecialchars($jenis) ?>">
            <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>">
            <input type="hidden" name="periode" value="<?= htmlspecialchars($periodeFilter) ?>">
            <input type="hidden" name="periode_a" value="<?= htmlspecialchars($periodeA) ?>">
            <input type="hidden" name="periode_b" value="<?= htmlspecialchars($periodeB) ?>">
            <input type="hidden" name="filter_mode" value="<?= htmlspecialchars($cal['mode']) ?>">
            <input type="hidden" name="thn_awal" value="<?= htmlspecialchars($cal['thn_awal']) ?>">
            <input type="hidden" name="thn_akhir" value="<?= htmlspecialchars($cal['thn_akhir']) ?>">
            <input type="hidden" name="bln_awal" value="<?= htmlspecialchars($cal['bln_awal']) ?>">
            <input type="hidden" name="bln_akhir" value="<?= htmlspecialchars($cal['bln_akhir']) ?>">
            <input type="hidden" name="tgl_awal" value="<?= htmlspecialchars($cal['tgl_awal']) ?>">
            <input type="hidden" name="tgl_akhir" value="<?= htmlspecialchars($cal['tgl_akhir']) ?>">
            <input type="hidden" name="top_n" value="<?= (int)($setting['top_n'] ?? 5) ?>">
            <input type="hidden" name="urutkan_berdasarkan" value="<?= htmlspecialchars(($setting['urutkan_berdasarkan'] ?? '') === 'frekuensi' ? 'frekuensi' : 'nilai_ekspor') ?>">

            <?php if (!$aggregateMode && !empty($dataItems)): ?>
                <div class="kelola-data-action-bar">
                    <div class="kelola-data-counts">
                        <span class="badge badge-neutral">Total: <strong><?= $totalItem ?></strong></span>
                        <span class="badge badge-brand">Publish: <strong><?= $totalPublished ?></strong></span>
                        <span class="badge badge-neutral">Draft: <strong><?= $totalDraft ?></strong></span>
                    </div>
                    <div class="kelola-data-actions" id="kelolaDataActions">
                        <button type="submit" form="formUpdatePublik" formaction="hapus_data.php" formmethod="POST" name="action" value="batch_ids" class="btn btn-danger btn-sm" data-confirm="Yakin ingin menghapus semua data yang dicentang? Tindakan ini tidak bisa dibatalkan." data-confirm-tone="danger">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 01-1 1v3M4 7h16"></path></svg>
                            <span>Hapus</span>
                        </button>
                        <button type="submit" form="formUpdatePublik" class="btn btn-primary btn-sm">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>Update</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

        </form>

            <!-- Tabel Data Komoditas -->
            <div class="kelola-data-print-header">
                <h1>Data Komoditas Karantina</h1>
                <p><?= htmlspecialchars($labelKegiatan) ?> &bull; <?= htmlspecialchars(ucfirst($kategori)) ?> &bull; <?= htmlspecialchars($rangePeriodLabel) ?></p>
            </div>
            <div class="card kelola-data-table-card" style="margin-top: 20px; overflow: hidden;">
                <!-- Toolbar Tabel -->
                 
                <div style="padding: 14px 18px; background: var(--bg); border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; font-size: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <div id="periodeAFilterWrap" style="display: flex; align-items: center; gap: 6px;">
                            <label for="periodeAFilter" style="font-weight: 600; color: var(--ink-soft);">Periode A:</label>
                            <select id="periodeAFilter" class="field-input" onchange="updatePeriodRange('a')" style="width: 175px; padding: 7px 10px; font-size: 12px;">
                                <option value="">Pilih periode</option>
                                <?php foreach ($validPeriods as $periodeOption): ?>
                                    <?php $periodeLabel = format_label_periode_kode($periodeOption) ?: $periodeOption; ?>
                                    <option value="<?= htmlspecialchars($periodeOption) ?>" <?= $periodeA === $periodeOption ? 'selected' : '' ?>><?= htmlspecialchars($periodeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="periodeBFilterWrap" style="display: flex; align-items: center; gap: 6px;">
                            <label for="periodeBFilter" style="font-weight: 600; color: var(--ink-soft);">Periode B:</label>
                            <select id="periodeBFilter" class="field-input" onchange="updatePeriodRange('b')" style="width: 175px; padding: 7px 10px; font-size: 12px;">
                                <option value="">Pilih periode</option>
                                <?php foreach ($validPeriods as $periodeOption): ?>
                                    <?php $periodeLabel = format_label_periode_kode($periodeOption) ?: $periodeOption; ?>
                                    <option value="<?= htmlspecialchars($periodeOption) ?>" <?= $periodeB === $periodeOption ? 'selected' : '' ?>><?= htmlspecialchars($periodeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="kelola-data-toolbar-right" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-left: auto;">
                        <a href="tambah_data.php?jenis=<?= urlencode($jenis) ?>&kategori=<?= urlencode($kategori) ?>" class="btn btn-primary btn-sm">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"></path></svg>
                            <span>Tambah Data</span>
                        </a>
                        <?php if ($periodeA !== '' && $periodeB !== ''): ?>
                            <a href="export_kelola_data.php?<?= htmlspecialchars(http_build_query(['jenis' => $jenis, 'kategori' => $kategori, 'periode_a' => $periodeA, 'periode_b' => $periodeB]), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-brand btn-sm">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3"></path></svg>
                                <span>Download Excel</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">
                                    <?php if (!$aggregateMode): ?>
                                        <input type="checkbox" id="checkAllHead" onchange="toggleAllCheckboxes(this.checked)">
                                    <?php endif; ?>
                                </th>
                                <th>Klasifikasi</th>
                                <th>Komoditas</th>
                                <th>Periode</th>
                                <th style="text-align: right;">Frekuensi</th>
                                <th style="text-align: right;">Volume</th>
                                <th>Satuan</th>
                                <th style="text-align: right;">Harga Barang (Rp)</th>
                                <th>Daerah Asal</th>
                                <th>Daerah Tujuan</th>
                                <th>Negara Tujuan</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dataItems)): ?>
                                <tr>
                                    <td colspan="13" style="padding: 48px 12px; text-align: center; color: var(--ink-muted);">
                                        <p style="font-weight: 600; color: var(--ink-soft); font-size: 13px;">Belum ada data <?= strtolower($labelKegiatan) ?> untuk komoditas <?= $kategori ?>.</p>
                                        <p style="font-size: 12px; margin: 4px 0 14px;">Silakan upload file Excel melalui form upload.</p>
                                        <a href="upload_<?= $kategori ?>.php?jenis=<?= $jenis ?>" class="btn btn-primary btn-sm">Upload Excel <?= ucfirst($kategori) ?> Sekarang</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dataItems as $idx => $row): ?>
                                    <tr class="<?= $row['is_published'] ? 'is-published' : '' ?>">
                                        <td style="text-align: center;">
                                            <?php if (!$aggregateMode): ?>
                                                <?php foreach ($row['_source_ids'] as $sourceId): ?>
                                                    <input type="hidden" form="formUpdatePublik" name="all_ids[]" value="<?= (int)$sourceId ?>">
                                                <?php endforeach; ?>
                                                <input type="checkbox" form="formUpdatePublik" name="published_groups[]" value="<?= htmlspecialchars(implode(',', $row['_source_ids']), ENT_QUOTES, 'UTF-8') ?>" <?= $row['is_published'] ? 'checked' : '' ?> class="item-checkbox">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['klasifikasi'] ?? '-') ?></td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--ink);"><?= htmlspecialchars($row['nama_komoditas']) ?></div>
                                            <?php if (!empty($row['nama_komoditas_lokal'])): ?>
                                                <div style="font-size: 11px; color: var(--brand); font-style: italic;"><?= htmlspecialchars($row['nama_komoditas_lokal']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <span class="badge badge-neutral"><?= htmlspecialchars($aggregateMode ? $rangePeriodLabel : (format_label_periode_kode($row['periode']) ?: $row['periode'])) ?></span>
                                            <?php if (!$aggregateMode && !empty($row['tanggal_mulai']) && !empty($row['tanggal_selesai'])): ?>
                                                <div style="font-size: 10px; color: var(--ink-muted); margin-top: 3px;"><?= date('d/m/Y', strtotime($row['tanggal_mulai'])) ?> s/d <?= date('d/m/Y', strtotime($row['tanggal_selesai'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;"><?= number_format($row['frekuensi'], 0, ',', '.') ?> <span style="color: var(--ink-muted); font-size: 10px;">kali</span></td>
                                        <td style="text-align: right;"><?= number_format($row['volume'], 2, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($row['satuan'] ?? '-') ?></td>
                                        <td style="text-align: right; color: var(--brand); font-weight: 600; white-space: nowrap;">Rp <?= number_format($row['nilai_ekspor'], 0, ',', '.') ?></td>
                                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['daerah_asal'] ?? '') ?>">
                                            <?= htmlspecialchars($row['daerah_asal'] ?? '-') ?>
                                        </td>
                                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['daerah_tujuan'] ?? '') ?>">
                                            <?= htmlspecialchars($row['daerah_tujuan'] ?? '-') ?>
                                        </td>
                                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['negara_tujuan'] ?? '') ?>">
                                            <?= htmlspecialchars($row['negara_tujuan'] ?? '-') ?>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <?php if ($row['publication_status'] === 'Campuran'): ?>
                                                <span class="badge badge-neutral" style="background: #fff4d6; color: #795400;">Campuran</span>
                                            <?php elseif ($row['is_published']): ?>
                                                <span class="badge badge-brand">Published</span>
                                            <?php else: ?>
                                                <span class="badge badge-neutral">Draft</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <?php if (!$aggregateMode): ?>
                                            <?php if (count($row['_source_ids']) === 1): ?>
                                            <a href="edit_data.php?id=<?= (int)$row['_source_ids'][0] ?>"
                                                style="color: var(--brand); display: inline-flex; padding: 4px;" title="Edit">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </a>
                                            <?php endif; ?>
                                            <form method="POST" action="hapus_data.php" style="display: inline;" data-confirm="Hapus komoditas ini?" data-confirm-tone="danger">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="group_ids">
                                                <input type="hidden" name="ids" value="<?= htmlspecialchars(implode(',', $row['_source_ids']), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="jenis" value="<?= htmlspecialchars($jenis) ?>">
                                                <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>">
                                                <input type="hidden" name="periode" value="<?= htmlspecialchars($row['periode'] ?? '') ?>">
                                                <input type="hidden" name="periode_a" value="<?= htmlspecialchars($periodeA) ?>">
                                                <input type="hidden" name="periode_b" value="<?= htmlspecialchars($periodeB) ?>">
                                                <button type="submit" style="color: var(--danger); background: none; border: 0; display: inline-flex; padding: 4px; cursor: pointer;" title="Hapus">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                                <span style="color: var(--ink-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
    </div>

    <script>
    function toggleAllCheckboxes(state) {
        document.querySelectorAll('.item-checkbox').forEach(cb => {
            cb.checked = state;
        });
        const head = document.getElementById('checkAllHead');
        if (head) head.checked = state;
    }

    function updatePeriodRange(changedPeriod) {
        const periodeA = document.getElementById('periodeAFilter').value;
        const periodeBFilter = document.getElementById('periodeBFilter');
        if (changedPeriod === 'a') {
            periodeBFilter.value = periodeA;
        }
        const periodeB = periodeBFilter.value;
        const params = new URLSearchParams({
            jenis: <?= json_encode($jenis) ?>,
            kategori: <?= json_encode($kategori) ?>,
            periode_a: periodeA,
            periode_b: periodeB
        });
        window.location.href = 'kelola_data.php?' + params.toString();
    }

    const kelolaDataActions = document.getElementById('kelolaDataActions');
    const kelolaDataToolbar = document.querySelector('.kelola-data-toolbar-right');
    if (kelolaDataActions && kelolaDataToolbar) {
        kelolaDataToolbar.insertBefore(kelolaDataActions, kelolaDataToolbar.firstChild);
    }

    </script>
</body>
</html>
