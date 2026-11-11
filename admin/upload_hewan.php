<?php
// admin/upload_hewan.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/raw_excel_import.php';
require_admin();

$message = '';
$error = '';

$currentJenis = $_GET['jenis'] ?? 'domestik_masuk';
if (!in_array($currentJenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'])) {
    $currentJenis = 'domestik_masuk';
}

if (!empty($_GET['msg'])) {
    $message = htmlspecialchars((string)$_GET['msg'], ENT_QUOTES, 'UTF-8');
}
if (!empty($_GET['error'])) {
    $error = (string)$_GET['error'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_raw_excel_upload($pdo, 'hewan', $databaseConfig);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Excel Komoditas Hewan (KH) - Admin Karantina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
</head>
<body>
    <?php require 'navbar.php'; ?>

    <div class="admin-main" style="max-width: 860px;">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Upload Excel Komoditas Hewan</h1>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success" style="margin-bottom: 16px; justify-content: space-between; flex-wrap: wrap;">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <div style="font-weight: 700;">Import Berhasil!</div>
                        <div style="font-size: 12px; margin-top: 2px;"><?= $message ?></div>
                    </div>
                </div>
                <a href="kelola_data.php?jenis=<?= $currentJenis ?>&kategori=hewan" class="btn btn-primary btn-sm">Lanjut Kelola & Publish Data &rarr;</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 16px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Upload -->
        <div class="card card-pad">
            <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 22px;">
                <?= csrf_field() ?>

                <!-- Pilihan Jenis Lalu Lintas -->
                <div>
                    <label class="field-label">Pilih Jenis Lalu Lintas Karantina <span style="color: var(--danger);">*</span></label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                        <label class="radio-card">
                            <input type="radio" name="jenis_kegiatan" value="domestik_masuk" onchange="window.location.href='?jenis='+this.value" <?= $currentJenis === 'domestik_masuk' ? 'checked' : '' ?>>
                            <div>
                                <div class="title">Domestik Masuk</div>
                                <div class="sub">Masuk Kalsel (Antar-Area)</div>
                            </div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="jenis_kegiatan" value="domestik_keluar" onchange="window.location.href='?jenis='+this.value" <?= $currentJenis === 'domestik_keluar' ? 'checked' : '' ?>>
                            <div>
                                <div class="title">Domestik Keluar</div>
                                <div class="sub">Keluar Kalsel (Antar-Area)</div>
                            </div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="jenis_kegiatan" value="ekspor" onchange="window.location.href='?jenis='+this.value" <?= $currentJenis === 'ekspor' ? 'checked' : '' ?>>
                            <div>
                                <div class="title">Ekspor</div>
                                <div class="sub">Ke Luar Negeri</div>
                            </div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="jenis_kegiatan" value="impor" onchange="window.location.href='?jenis='+this.value" <?= $currentJenis === 'impor' ? 'checked' : '' ?>>
                            <div>
                                <div class="title">Impor</div>
                                <div class="sub">Dari Luar Negeri</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Kategori Komoditas -->
                <div>
                    <label class="field-label">Kategori Komoditas</label>
                    <div class="tab-row grid-3">
                        <a href="upload_hewan.php?jenis=<?= $currentJenis ?>" class="tab-link active kategori-tab-link"><span class="dot dot-hewan"></span> Hewan (KH)</a>
                        <a href="upload_ikan.php?jenis=<?= $currentJenis ?>" class="tab-link kategori-tab-link"><span class="dot dot-ikan"></span> Ikan (KI)</a>
                        <a href="upload_tumbuhan.php?jenis=<?= $currentJenis ?>" class="tab-link kategori-tab-link"><span class="dot dot-tumbuhan"></span> Tumbuhan (KT)</a>
                    </div>
                </div>

                <script>
                document.querySelectorAll('input[name="jenis_kegiatan"]').forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        document.querySelectorAll('.kategori-tab-link').forEach(function (a) {
                            a.href = a.href.replace(/jenis=[^&]*/, 'jenis=' + radio.value);
                        });
                    });
                });
                </script>

                <!-- Kalender Pemilihan Bulan Data -->
                <div class="card card-pad" style="background: var(--bg);">
                    <label class="field-label" style="display: block; margin-bottom: 12px;">Pilih Bulan Data <span style="color: var(--danger);">*</span></label>
                    <input type="month" name="periode" value="<?= date('Y-m', strtotime('first day of last month')) ?>" required class="field-input">
                    <p class="field-hint">Satu file Excel mentah direkap untuk satu bulan yang dipilih.</p>
                </div>

                <!-- File Excel -->
                <div>
                    <label class="field-label">Pilih File Excel (.xlsx / .xls) <span style="color: var(--danger);">*</span></label>
                    <input type="file" name="file_excel" required accept=".xlsx, .xls, .csv" class="file-input">
                </div>

                <!-- Opsi Mode & Publish -->
                <div style="padding-top: 12px; border-top: 1px solid var(--line); display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
                    <div>
                        <label class="field-label">Mode Import Data</label>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                                <input type="radio" name="mode" value="append" checked>
                                <span>Tambahkan ke data yang ada (Append)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                                <input type="radio" name="mode" value="replace">
                                <span>Gantikan data lama pada jenis & periode ini (Replace)</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Status Setelah Upload</label>
                        <p class="field-hint" style="margin-top: 0;">Semua data masuk sebagai draft. Periksa dan publikasikan dari halaman Kelola Data.</p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div style="padding-top: 4px; display: flex; align-items: center; justify-content: space-between;">
                    <a href="kelola_data.php?jenis=<?= $currentJenis ?>&kategori=hewan" style="font-size: 13px; color: var(--ink-muted); text-decoration: none;">Batal / Ke Kelola Data</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"></path></svg>
                        <span>Import Excel</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
