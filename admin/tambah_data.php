<?php
// admin/tambah_data.php - Input data komoditas secara manual (tanpa upload Excel)
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/admin_activity.php';
require_admin();

$message = '';
$error = '';

$currentJenis = $_GET['jenis'] ?? 'domestik_masuk';
if (!in_array($currentJenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'])) {
    $currentJenis = 'domestik_masuk';
}
$currentKategori = $_GET['kategori'] ?? 'hewan';
if (!in_array($currentKategori, ['hewan', 'ikan', 'tumbuhan'], true)) {
    $currentKategori = 'hewan';
}
$labelNegara = in_array($currentJenis, ['domestik_keluar', 'domestik_masuk'], true)
    ? 'Negara / Perusahaan (opsional)'
    : get_label_arah($currentJenis) . ' / Perusahaan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $jenis = $_POST['jenis_kegiatan'] ?? 'domestik_masuk';
    if (!in_array($jenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'])) {
        $jenis = 'domestik_masuk';
    }
    $currentJenis = $jenis;

    $kategori = $_POST['kategori'] ?? 'hewan';
    if (!in_array($kategori, ['hewan', 'ikan', 'tumbuhan'])) {
        $kategori = 'hewan';
    }
    $currentKategori = $kategori;

    $klasifikasi = trim($_POST['klasifikasi'] ?? '');
    $namaKomoditas = trim($_POST['nama_komoditas'] ?? '');
    $namaKomoditasLokal = trim($_POST['nama_komoditas_lokal'] ?? '');
    $frekuensi = (int)clean_number($_POST['frekuensi'] ?? 0);
    $volume = clean_number($_POST['volume'] ?? 0);
    $satuan = trim($_POST['satuan'] ?? '') ?: 'Kilogram';
    $nilaiEkspor = clean_number($_POST['nilai_ekspor'] ?? 0);
    $daerahAsal = trim($_POST['daerah_asal'] ?? '');
    $daerahTujuan = trim($_POST['daerah_tujuan'] ?? '');
    $negaraTujuan = trim($_POST['negara_tujuan'] ?? '');

    $cal = parse_calendar_filter('bulan', $_POST);
    $periode = $cal['bln_awal'] . '_' . $cal['bln_akhir'];
    $tanggalMulai = $cal['tgl_awal'];
    $tanggalSelesai = $cal['tgl_akhir'];
    $langsungPublish = isset($_POST['langsung_publish']) ? 1 : 0;

    if (empty($namaKomoditas)) {
        $error = 'Nama komoditas wajib diisi!';
    } else {
        $stmt = $pdo->prepare("INSERT INTO ekspor_komoditas
            (jenis_kegiatan, kategori, klasifikasi, nama_komoditas, nama_komoditas_lokal, frekuensi, volume, satuan, nilai_ekspor, daerah_asal, daerah_tujuan, negara_tujuan, periode, tanggal_mulai, tanggal_selesai, is_published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $jenis,
            $kategori,
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
            $periode,
            $tanggalMulai,
            $tanggalSelesai,
            $langsungPublish
        ]);

        log_admin_activity(
            $pdo,
            'tambah_data',
            sprintf('Menambahkan data komoditas %s/%s periode %s.', $kategori, $jenis, $periode)
        );
        $message = 'Data komoditas "' . htmlspecialchars($namaKomoditas) . '" berhasil ditambahkan.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Data Komoditas - Admin Karantina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
</head>
<body>
    <?php require 'navbar.php'; ?>

    <div class="admin-main" style="max-width: 860px;">
        <div class="page-header">
            <div>
                <span class="eyebrow"><span class="dot dot-brand"></span> Input Manual</span>
                <h1 class="page-title">Tambah Data Komoditas</h1>
                <p class="page-desc">Masukkan satu data komoditas secara manual tanpa perlu mengunggah file Excel. Data langsung tersimpan ke database dan otomatis tersedia di dashboard.</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success" style="margin-bottom: 16px; justify-content: space-between; flex-wrap: wrap;">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div><?= $message ?></div>
                </div>
                <a href="kelola_data.php?jenis=<?= urlencode($currentJenis) ?>&kategori=<?= urlencode($currentKategori) ?>" class="btn btn-primary btn-sm">Lihat di Kelola Data &rarr;</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 16px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <div class="card card-pad">
            <form action="" method="POST" data-confirm="Simpan data komoditas ini ke database?" style="display: flex; flex-direction: column; gap: 22px;">
                <?= csrf_field() ?>

                <div>
                    <label class="field-label">Pilih Jenis Lalu Lintas Karantina <span style="color: var(--danger);">*</span></label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                        <label class="radio-card">
                            <input type="radio" name="jenis_kegiatan" value="domestik_masuk" <?= $currentJenis === 'domestik_masuk' ? 'checked' : '' ?>>
                            <div><div class="title">Domestik Masuk</div><div class="sub">Masuk Kalsel (Antar-Area)</div></div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="jenis_kegiatan" value="domestik_keluar" <?= $currentJenis === 'domestik_keluar' ? 'checked' : '' ?>>
                            <div><div class="title">Domestik Keluar</div><div class="sub">Keluar Kalsel (Antar-Area)</div></div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="jenis_kegiatan" value="ekspor" <?= $currentJenis === 'ekspor' ? 'checked' : '' ?>>
                            <div><div class="title">Ekspor</div><div class="sub">Ke Luar Negeri</div></div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="jenis_kegiatan" value="impor" <?= $currentJenis === 'impor' ? 'checked' : '' ?>>
                            <div><div class="title">Impor</div><div class="sub">Dari Luar Negeri</div></div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="field-label">Kategori Komoditas <span style="color: var(--danger);">*</span></label>
                    <select name="kategori" id="selectKategori" class="field-input" onchange="toggleLokalField()">
                        <option value="hewan" <?= $currentKategori === 'hewan' ? 'selected' : '' ?>>Hewan (KH)</option>
                        <option value="ikan" <?= $currentKategori === 'ikan' ? 'selected' : '' ?>>Ikan (KI)</option>
                        <option value="tumbuhan" <?= $currentKategori === 'tumbuhan' ? 'selected' : '' ?>>Tumbuhan (KT)</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Klasifikasi</label>
                    <input type="text" name="klasifikasi" class="field-input" placeholder="Klasifikasi komoditas">
                </div>

                <div>
                    <label class="field-label">Nama Komoditas <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="nama_komoditas" required class="field-input" placeholder="Contoh: Sarang Burung Walet">
                </div>

                <div id="fieldLokal" style="display: none;">
                    <label class="field-label">Nama Komoditas (Lokal / Indonesia)</label>
                    <input type="text" name="nama_komoditas_lokal" class="field-input" placeholder="Khusus kategori Ikan">
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px;">
                    <div>
                        <label class="field-label">Frekuensi</label>
                        <input type="text" name="frekuensi" value="0" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Volume</label>
                        <input type="text" name="volume" value="0" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Satuan</label>
                        <select name="satuan" class="field-input">
                            <option value="ton">Ton</option>
                            <option value="kilogram">Kilogram</option>
                            <option value="ekor">Ekor</option>
                            <option value="meterkubik">Meter Kubik</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="field-label">Nilai Komoditas (Rp)</label>
                    <input type="text" name="nilai_ekspor" value="0" class="field-input">
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                    <div>
                        <label class="field-label">Daerah Asal</label>
                        <input type="text" name="daerah_asal" class="field-input" placeholder="Daerah asal komoditas">
                    </div>
                    <div>
                        <label class="field-label">Daerah Tujuan</label>
                        <input type="text" name="daerah_tujuan" class="field-input" placeholder="Daerah tujuan komoditas">
                    </div>
                </div>

                <div>
                    <label class="field-label" id="countryFieldLabel"><?= htmlspecialchars($labelNegara) ?></label>
                    <input type="text" name="negara_tujuan" class="field-input" placeholder="Pisahkan dengan koma jika lebih dari satu">
                </div>

                <!-- Kalender Periode -->
                <div class="card card-pad" style="background: var(--bg);">
                    <label class="field-label" style="display: block; margin-bottom: 12px;">Periode Data (Bulan) <span style="color: var(--danger);">*</span></label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px;">
                        <div>
                            <label class="field-label">Dari Bulan</label>
                            <input type="month" name="bln_awal" value="<?= date('Y-m') ?>" class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Sampai Bulan</label>
                            <input type="month" name="bln_akhir" value="<?= date('Y-m') ?>" class="field-input">
                        </div>
                    </div>
                </div>

                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="langsung_publish" value="1">
                    <span>Langsung publikasikan ke board publik setelah disimpan</span>
                </label>

                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--line);">
                    <a href="kelola_data.php?jenis=<?= urlencode($currentJenis) ?>&kategori=<?= urlencode($currentKategori) ?>" style="font-size: 13px; color: var(--ink-muted); text-decoration: none;">Batal / Ke Kelola Data</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>Simpan Data</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleLokalField() {
        const kategori = document.getElementById('selectKategori').value;
        document.getElementById('fieldLokal').style.display = kategori === 'ikan' ? 'block' : 'none';
    }

    function updateCountryFieldLabel() {
        const jenis = document.querySelector('input[name="jenis_kegiatan"]:checked').value;
        const labels = {
            ekspor: 'Negara Tujuan / Perusahaan',
            impor: 'Negara Asal / Perusahaan',
            domestik_keluar: 'Negara / Perusahaan (opsional)',
            domestik_masuk: 'Negara / Perusahaan (opsional)'
        };
        document.getElementById('countryFieldLabel').textContent = labels[jenis];
    }

    document.querySelectorAll('input[name="jenis_kegiatan"]').forEach(function(input) {
        input.addEventListener('change', updateCountryFieldLabel);
    });
    toggleLokalField();
    updateCountryFieldLabel();
    </script>
</body>
</html>
