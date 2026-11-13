<?php
// admin/edit_data.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/admin_activity.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: kelola_data.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM ekspor_komoditas WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: kelola_data.php?error=' . urlencode('Data tidak ditemukan.'));
    exit;
}

$error = '';
$labelNegara = in_array($row['jenis_kegiatan'], ['domestik_keluar', 'domestik_masuk'], true)
    ? 'Negara / Perusahaan (opsional)'
    : get_label_arah($row['jenis_kegiatan']) . ' / Perusahaan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $namaKomoditas = trim($_POST['nama_komoditas'] ?? '');
    $namaKomoditasLokal = trim($_POST['nama_komoditas_lokal'] ?? '');
    $klasifikasi = trim($_POST['klasifikasi'] ?? '');
    $frekuensi = (int)clean_number($_POST['frekuensi'] ?? 0);
    $volume = clean_number($_POST['volume'] ?? 0);
    $satuan = trim($_POST['satuan'] ?? '') ?: 'Kilogram';
    $nilaiEkspor = clean_number($_POST['nilai_ekspor'] ?? 0);
    $daerahAsal = trim($_POST['daerah_asal'] ?? '');
    $daerahTujuan = trim($_POST['daerah_tujuan'] ?? '');
    $negaraTujuan = trim($_POST['negara_tujuan'] ?? '');
    $tanggalMulai = trim($_POST['tanggal_mulai'] ?? '');
    $tanggalSelesai = trim($_POST['tanggal_selesai'] ?? '');

    if (empty($namaKomoditas)) {
        $error = 'Nama komoditas wajib diisi!';
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE ekspor_komoditas SET
            klasifikasi = ?, nama_komoditas = ?, nama_komoditas_lokal = ?, frekuensi = ?, volume = ?, satuan = ?,
            nilai_ekspor = ?, daerah_asal = ?, daerah_tujuan = ?, negara_tujuan = ?, tanggal_mulai = ?, tanggal_selesai = ?
            WHERE id = ?");
        $stmtUpdate->execute([
            $klasifikasi ?: null,
            $namaKomoditas,
            $namaKomoditasLokal ?: null,
            $frekuensi,
            $volume,
            $satuan,
            $nilaiEkspor,
            $daerahAsal ?: null,
            $daerahTujuan ?: null,
            $negaraTujuan ?: null,
            $tanggalMulai ?: null,
            $tanggalSelesai ?: null,
            $id
        ]);

        log_admin_activity(
            $pdo,
            'edit_data',
            sprintf('Mengubah data komoditas ID %d (%s/%s).', $id, $row['kategori'], $row['jenis_kegiatan'])
        );
        header('Location: kelola_data.php?jenis=' . urlencode($row['jenis_kegiatan']) . '&kategori=' . urlencode($row['kategori']) . '&msg=edit_sukses');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Komoditas - Admin Karantina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
</head>
<body>
    <?php require 'navbar.php'; ?>

    <div class="admin-main" style="max-width: 720px;">
        <div class="page-header">
            <div>
                <span class="eyebrow"><span class="dot dot-brand"></span> <?= htmlspecialchars(get_label_kegiatan($row['jenis_kegiatan'])) ?> &bull; <?= ucfirst($row['kategori']) ?></span>
                <h1 class="page-title">Edit Data Komoditas</h1>
                <p class="page-desc">Perbarui data komoditas, klasifikasi, lokasi asal/tujuan, dan periode tanpa perlu mengunggah ulang file Excel.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 16px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <div class="card card-pad">
            <form method="POST" action="" style="display: flex; flex-direction: column; gap: 18px;">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                <div>
                    <label class="field-label">Klasifikasi</label>
                    <input type="text" name="klasifikasi" value="<?= htmlspecialchars($row['klasifikasi'] ?? '') ?>" class="field-input">
                </div>

                <div>
                    <label class="field-label">Nama Komoditas <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="nama_komoditas" required value="<?= htmlspecialchars($row['nama_komoditas']) ?>" class="field-input">
                </div>

                <?php if ($row['kategori'] === 'ikan'): ?>
                    <div>
                        <label class="field-label">Nama Komoditas (Lokal)</label>
                        <input type="text" name="nama_komoditas_lokal" value="<?= htmlspecialchars($row['nama_komoditas_lokal'] ?? '') ?>" class="field-input">
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px;">
                    <div>
                        <label class="field-label">Frekuensi</label>
                        <input type="text" name="frekuensi" value="<?= htmlspecialchars($row['frekuensi']) ?>" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Volume</label>
                        <input type="text" name="volume" value="<?= htmlspecialchars($row['volume']) ?>" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Satuan</label>
                        <input type="text" name="satuan" value="<?= htmlspecialchars($row['satuan']) ?>" class="field-input">
                    </div>
                </div>

                <div>
                    <label class="field-label">Nilai Komoditas (Rp)</label>
                    <input type="text" name="nilai_ekspor" value="<?= htmlspecialchars($row['nilai_ekspor']) ?>" class="field-input">
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                    <div>
                        <label class="field-label">Daerah Asal</label>
                        <input type="text" name="daerah_asal" value="<?= htmlspecialchars($row['daerah_asal'] ?? '') ?>" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Daerah Tujuan</label>
                        <input type="text" name="daerah_tujuan" value="<?= htmlspecialchars($row['daerah_tujuan'] ?? '') ?>" class="field-input">
                    </div>
                </div>

                <div>
                    <label class="field-label"><?= htmlspecialchars($labelNegara) ?></label>
                    <input type="text" name="negara_tujuan" value="<?= htmlspecialchars($row['negara_tujuan'] ?? '') ?>" class="field-input">
                    <p class="field-hint">Pisahkan dengan koma jika lebih dari satu.</p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px;">
                    <div>
                        <label class="field-label">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" value="<?= htmlspecialchars($row['tanggal_mulai'] ?? '') ?>" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" value="<?= htmlspecialchars($row['tanggal_selesai'] ?? '') ?>" class="field-input">
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--line);">
                    <a href="kelola_data.php?jenis=<?= urlencode($row['jenis_kegiatan']) ?>&kategori=<?= urlencode($row['kategori']) ?>" style="font-size: 13px; color: var(--ink-muted); text-decoration: none;">Batal / Kembali</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
