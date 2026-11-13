<?php
// admin/index.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_admin();

$activeJenis = $_GET['jenis'] ?? 'domestik_masuk';
if (!in_array($activeJenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'])) {
    $activeJenis = 'domestik_masuk';
}

// 1. Ringkasan Eksekutif Semua 4 Jenis Kegiatan
$summaryAll = [];
$allJenisList = ['domestik_masuk', 'domestik_keluar', 'ekspor', 'impor'];
foreach ($allJenisList as $j) {
    $stmtSummary = $pdo->prepare("SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published_items,
        SUM(CASE WHEN is_published = 1 THEN nilai_ekspor ELSE 0 END) as total_nilai,
        SUM(CASE WHEN is_published = 1 THEN volume ELSE 0 END) as total_volume
        FROM ekspor_komoditas WHERE jenis_kegiatan = ?");
    $stmtSummary->execute([$j]);
    $summaryAll[$j] = $stmtSummary->fetch() ?: [
        'total_items' => 0,
        'published_items' => 0,
        'total_nilai' => 0,
        'total_volume' => 0
    ];
}

// 2. Ambil statistik per kategori untuk jenis kegiatan aktif
$kategoris = ['hewan', 'ikan', 'tumbuhan'];
$stats = [];

foreach ($kategoris as $k) {
    // Total & Published
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published_items,
        SUM(CASE WHEN is_published = 1 THEN nilai_ekspor ELSE 0 END) as total_nilai,
        SUM(CASE WHEN is_published = 1 THEN volume ELSE 0 END) as total_volume,
        COUNT(DISTINCT periode) as count_periode
        FROM ekspor_komoditas WHERE jenis_kegiatan = ? AND kategori = ?");
    $stmt->execute([$activeJenis, $k]);
    $st = $stmt->fetch();

    // Setting
    $stmtSet = $pdo->prepare("SELECT * FROM ekspor_settings WHERE jenis_kegiatan = ? AND kategori = ? LIMIT 1");
    $stmtSet->execute([$activeJenis, $k]);
    $setting = $stmtSet->fetch();

    $stats[$k] = array_merge($st ?: [], [
        'setting' => $setting ?: [
            'top_n' => 5,
            'urutkan_berdasarkan' => 'nilai_ekspor',
            'periode_label' => 'Belum Diatur'
        ]
    ]);
}

$labelKegiatanAktif = get_label_kegiatan($activeJenis);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Lalu Lintas Komoditas Karantina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
</head>
<body>

    <?php require 'navbar.php'; ?>

    <div class="admin-main">

        <!-- Welcome -->
        <div class="card card-pad" style="margin-bottom: 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px;">
            <div>
                <span class="eyebrow"><span class="dot dot-brand"></span> Panel Kontrol Administrator Karantina</span>
                <h1 class="page-title">Sistem Infografis Komoditas Karantina</h1>
                <p class="page-desc">
                    Kelola data lalu lintas komoditas hewan, ikan, dan tumbuhan untuk <strong>Domestik Masuk</strong>, <strong>Domestik Keluar</strong>, <strong>Ekspor</strong>, dan <strong>Impor</strong>. Data yang dipublish otomatis terhubung ke board publik.
                </p>
            </div>
            <a href="../index.php" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                <span>Lihat Board Publik</span>
            </a>
        </div>

        <!-- Ringkasan 4 Jenis Lalu Lintas -->
        <div style="margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                <h2 style="font-size: 13px; font-weight: 700; color: var(--ink-soft);">Ringkasan 4 Jenis Lalu Lintas Karantina</h2>
                <span style="font-size: 11px; color: var(--ink-muted);">Klik salah satu kartu untuk mengelola</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                <!-- 1. DOMESTIK MASUK -->
                <a href="index.php?jenis=domestik_masuk" class="card card-pad" style="text-decoration: none; display: flex; flex-direction: column; gap: 10px; <?= $activeJenis === 'domestik_masuk' ? 'border-color: var(--brand); box-shadow: 0 0 0 2px rgba(15,118,110,0.15);' : '' ?>">
                    <span class="badge <?= $activeJenis === 'domestik_masuk' ? 'badge-brand' : 'badge-neutral' ?>">Antar-Area</span>
                    <div>
                        <div style="font-weight: 700; color: var(--ink); font-size: 14px;">Domestik Masuk</div>
                        <div style="font-size: 11px; color: var(--ink-muted); margin-top: 2px;"><?= (int)$summaryAll['domestik_masuk']['published_items'] ?> / <?= (int)$summaryAll['domestik_masuk']['total_items'] ?> komoditas publish</div>
                        <div style="margin-top: 6px; font-weight: 700; color: var(--brand);"><?= format_rupiah_singkat($summaryAll['domestik_masuk']['total_nilai']) ?></div>
                    </div>
                </a>

                <!-- 2. DOMESTIK KELUAR -->
                <a href="index.php?jenis=domestik_keluar" class="card card-pad" style="text-decoration: none; display: flex; flex-direction: column; gap: 10px; <?= $activeJenis === 'domestik_keluar' ? 'border-color: var(--brand); box-shadow: 0 0 0 2px rgba(15,118,110,0.15);' : '' ?>">
                    <span class="badge <?= $activeJenis === 'domestik_keluar' ? 'badge-brand' : 'badge-neutral' ?>">Antar-Area</span>
                    <div>
                        <div style="font-weight: 700; color: var(--ink); font-size: 14px;">Domestik Keluar</div>
                        <div style="font-size: 11px; color: var(--ink-muted); margin-top: 2px;"><?= (int)$summaryAll['domestik_keluar']['published_items'] ?> / <?= (int)$summaryAll['domestik_keluar']['total_items'] ?> komoditas publish</div>
                        <div style="margin-top: 6px; font-weight: 700; color: var(--brand);"><?= format_rupiah_singkat($summaryAll['domestik_keluar']['total_nilai']) ?></div>
                    </div>
                </a>

                <!-- 3. EKSPOR -->
                <a href="index.php?jenis=ekspor" class="card card-pad" style="text-decoration: none; display: flex; flex-direction: column; gap: 10px; <?= $activeJenis === 'ekspor' ? 'border-color: var(--brand); box-shadow: 0 0 0 2px rgba(15,118,110,0.15);' : '' ?>">
                    <span class="badge <?= $activeJenis === 'ekspor' ? 'badge-brand' : 'badge-neutral' ?>">Luar Negeri</span>
                    <div>
                        <div style="font-weight: 700; color: var(--ink); font-size: 14px;">Ekspor</div>
                        <div style="font-size: 11px; color: var(--ink-muted); margin-top: 2px;"><?= (int)$summaryAll['ekspor']['published_items'] ?> / <?= (int)$summaryAll['ekspor']['total_items'] ?> komoditas publish</div>
                        <div style="margin-top: 6px; font-weight: 700; color: var(--brand);"><?= format_rupiah_singkat($summaryAll['ekspor']['total_nilai']) ?></div>
                    </div>
                </a>

                <!-- 4. IMPOR -->
                <a href="index.php?jenis=impor" class="card card-pad" style="text-decoration: none; display: flex; flex-direction: column; gap: 10px; <?= $activeJenis === 'impor' ? 'border-color: var(--brand); box-shadow: 0 0 0 2px rgba(15,118,110,0.15);' : '' ?>">
                    <span class="badge <?= $activeJenis === 'impor' ? 'badge-brand' : 'badge-neutral' ?>">Luar Negeri</span>
                    <div>
                        <div style="font-weight: 700; color: var(--ink); font-size: 14px;">Impor</div>
                        <div style="font-size: 11px; color: var(--ink-muted); margin-top: 2px;"><?= (int)$summaryAll['impor']['published_items'] ?> / <?= (int)$summaryAll['impor']['total_items'] ?> komoditas publish</div>
                        <div style="margin-top: 6px; font-weight: 700; color: var(--brand);"><?= format_rupiah_singkat($summaryAll['impor']['total_nilai']) ?></div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Rincian Kategori -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
            <h2 style="font-size: 15px; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 8px;">
                <span class="dot dot-brand"></span>
                <span>Rincian Kategori untuk <?= htmlspecialchars($labelKegiatanAktif) ?></span>
            </h2>
            <a href="kelola_data.php?jenis=<?= $activeJenis ?>" style="font-size: 12px; color: var(--brand); font-weight: 600; text-decoration: none;">
                Kelola Semua Data &rarr;
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <!-- HEWAN -->
            <div class="card card-pad" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="dot dot-hewan"></span>
                            <h3 style="font-weight: 700; color: var(--ink); font-size: 15px;">Karantina Hewan (KH)</h3>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Total Komoditas</span>
                            <span style="font-weight: 600;"><?= (int)$stats['hewan']['total_items'] ?> komoditas</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Status Tayang</span>
                            <span style="font-weight: 600; color: var(--brand);"><?= (int)$stats['hewan']['published_items'] ?> komoditas</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Total Nilai Published</span>
                            <span style="font-weight: 600;"><?= format_rupiah_singkat($stats['hewan']['total_nilai']) ?></span>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 14px; border-top: 1px solid var(--line); display: flex; gap: 8px;">
                    <a href="upload_hewan.php?jenis=<?= $activeJenis ?>" class="btn btn-secondary" style="flex: 1; justify-content: center;">Upload Excel</a>
                    <a href="kelola_data.php?jenis=<?= $activeJenis ?>&kategori=hewan" class="btn btn-primary" style="flex: 1; justify-content: center;">Kelola Data &rarr;</a>
                </div>
            </div>

            <!-- IKAN -->
            <div class="card card-pad" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="dot dot-ikan"></span>
                            <h3 style="font-weight: 700; color: var(--ink); font-size: 15px;">Karantina Ikan (KI)</h3>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Total Komoditas</span>
                            <span style="font-weight: 600;"><?= (int)$stats['ikan']['total_items'] ?> komoditas</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Status Tayang</span>
                            <span style="font-weight: 600; color: var(--brand);"><?= (int)$stats['ikan']['published_items'] ?> komoditas</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Total Nilai Published</span>
                            <span style="font-weight: 600;"><?= format_rupiah_singkat($stats['ikan']['total_nilai']) ?></span>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 14px; border-top: 1px solid var(--line); display: flex; gap: 8px;">
                    <a href="upload_ikan.php?jenis=<?= $activeJenis ?>" class="btn btn-secondary" style="flex: 1; justify-content: center;">Upload Excel</a>
                    <a href="kelola_data.php?jenis=<?= $activeJenis ?>&kategori=ikan" class="btn btn-primary" style="flex: 1; justify-content: center;">Kelola Data &rarr;</a>
                </div>
            </div>

            <!-- TUMBUHAN -->
            <div class="card card-pad" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="dot dot-tumbuhan"></span>
                            <h3 style="font-weight: 700; color: var(--ink); font-size: 15px;">Karantina Tumbuhan (KT)</h3>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Total Komoditas</span>
                            <span style="font-weight: 600;"><?= (int)$stats['tumbuhan']['total_items'] ?> komoditas</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Status Tayang</span>
                            <span style="font-weight: 600; color: var(--brand);"><?= (int)$stats['tumbuhan']['published_items'] ?> komoditas</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid var(--line);">
                            <span style="color: var(--ink-muted);">Total Nilai Published</span>
                            <span style="font-weight: 600;"><?= format_rupiah_singkat($stats['tumbuhan']['total_nilai']) ?></span>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 14px; border-top: 1px solid var(--line); display: flex; gap: 8px;">
                    <a href="upload_tumbuhan.php?jenis=<?= $activeJenis ?>" class="btn btn-secondary" style="flex: 1; justify-content: center;">Upload Excel</a>
                    <a href="kelola_data.php?jenis=<?= $activeJenis ?>&kategori=tumbuhan" class="btn btn-primary" style="flex: 1; justify-content: center;">Kelola Data &rarr;</a>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
