<?php
require_once __DIR__ . '/../config/auth.php';
require_admin();

use Dompdf\Dompdf;
use Dompdf\Options;

$exportStage = 'memuat library PDF';
$initialBufferLevel = ob_get_level();

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../includes/period_aggregation.php';

    $exportStage = 'menghubungkan database';
    require_once __DIR__ . '/../config/koneksi.php';

    $jenis = $_GET['jenis'] ?? 'domestik_masuk';
    if (!in_array($jenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'], true)) {
        $jenis = 'domestik_masuk';
    }

    $kategori = $_GET['kategori'] ?? 'hewan';
    if (!in_array($kategori, ['hewan', 'ikan', 'tumbuhan'], true)) {
        $kategori = 'hewan';
    }

    $legacyPeriod = trim($_GET['periode'] ?? '');
    $periodeA = trim($_GET['periode_a'] ?? $legacyPeriod);
    $periodeB = trim($_GET['periode_b'] ?? $legacyPeriod);
    $validPeriod = static function (string $period): bool {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1
            && checkdate((int)substr($period, 5, 2), 1, (int)substr($period, 0, 4));
    };

    if (!$validPeriod($periodeA) || !$validPeriod($periodeB)) {
        http_response_code(400);
        exit('Pilih Periode A dan Periode B yang valid sebelum mengunduh PDF.');
    }

    $rangeStart = min($periodeA, $periodeB);
    $rangeEnd = max($periodeA, $periodeB);
    $exportStage = 'mengambil data dari database';
    $stmt = $pdo->prepare(
        'SELECT *
         FROM ekspor_komoditas
         WHERE jenis_kegiatan = ? AND kategori = ?
           AND periode >= ? AND periode <= ?
           AND periode REGEXP \'^[0-9]{4}-(0[1-9]|1[0-2])$\'
         ORDER BY is_published DESC, nilai_ekspor DESC, frekuensi DESC'
    );
    $stmt->execute([$jenis, $kategori, $rangeStart, $rangeEnd]);
    $rows = aggregate_commodity_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

    $labelKegiatan = get_label_kegiatan($jenis);
    $labelKategori = strtoupper($kategori);
    $labelPeriodeA = format_label_periode_kode($rangeStart) ?: $rangeStart;
    $labelPeriodeB = format_label_periode_kode($rangeEnd) ?: $rangeEnd;
    $labelPeriode = $rangeStart === $rangeEnd ? $labelPeriodeA : "{$labelPeriodeA} - {$labelPeriodeB}";
    $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $exportStage = 'menyusun laporan PDF';
    ob_start();
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { size: A4 landscape; margin: 24px 22px 30px; }
            body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 8px; }
            h1 { margin: 0 0 4px; color: #17365d; font-size: 15px; text-align: center; }
            .institution { margin: 0 0 3px; font-size: 9px; font-weight: bold; text-align: center; }
            .period { margin: 0 0 12px; color: #5b6573; font-size: 8px; text-align: center; }
            .summary { margin: 0 0 8px; font-size: 8px; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
            th, td { padding: 4px 3px; border: 1px solid #cbd2da; vertical-align: top; overflow-wrap: break-word; }
            th { background: #17365d; color: #fff; font-size: 7px; text-align: center; }
            tbody tr:nth-child(even) { background: #f3f6fa; }
            .number { text-align: right; white-space: nowrap; }
            .center { text-align: center; }
            .commodity-local { margin-top: 2px; color: #52657a; font-style: italic; font-size: 7px; }
            .footer { position: fixed; right: 0; bottom: -18px; color: #687386; font-size: 7px; }
        </style>
    </head>
    <body>
        <h1>REKAP DATA <?= $escape($labelKegiatan) ?> KARANTINA <?= $escape($labelKategori) ?></h1>
        <p class="institution">Balai Karantina Hewan, Ikan, dan Tumbuhan Kalimantan Selatan</p>
        <p class="period">Periode: <?= $escape($labelPeriode) ?></p>
        <p class="summary">
            Total komoditas: <?= number_format(count($rows), 0, ',', '.') ?>
            &nbsp;|&nbsp; Dicetak: <?= date('d/m/Y H:i') ?>
        </p>
        <table>
            <thead>
                <tr>
                    <th style="width: 3%">No</th>
                    <th style="width: 8%">Klasifikasi</th>
                    <th style="width: 15%">Komoditas</th>
                    <th style="width: 9%">Periode</th>
                    <th style="width: 6%">Frekuensi</th>
                    <th style="width: 7%">Volume</th>
                    <th style="width: 6%">Satuan</th>
                    <th style="width: 10%">Harga Barang (Rp)</th>
                    <th style="width: 10%">Daerah Asal</th>
                    <th style="width: 10%">Daerah Tujuan</th>
                    <th style="width: 9%">Negara Tujuan</th>
                    <th style="width: 7%">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td class="center" colspan="12">Tidak ada data untuk periode yang dipilih.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td class="center"><?= $index + 1 ?></td>
                            <td><?= $escape($row['klasifikasi'] ?? '-') ?></td>
                            <td>
                                <?= $escape($row['nama_komoditas'] ?? '-') ?>
                                <?php if (!empty($row['nama_komoditas_lokal'])): ?>
                                    <div class="commodity-local"><?= $escape($row['nama_komoditas_lokal']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= $escape($labelPeriode) ?></td>
                            <td class="number"><?= number_format((float)($row['frekuensi'] ?? 0), 0, ',', '.') ?></td>
                            <td class="number"><?= number_format((float)($row['volume'] ?? 0), 2, ',', '.') ?></td>
                            <td><?= $escape($row['satuan'] ?? '-') ?></td>
                            <td class="number"><?= number_format((float)($row['nilai_ekspor'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= $escape($row['daerah_asal'] ?? '-') ?></td>
                            <td><?= $escape($row['daerah_tujuan'] ?? '-') ?></td>
                            <td><?= $escape($row['negara_tujuan'] ?? '-') ?></td>
                            <td class="center"><?= $escape($row['publication_status'] ?? 'Draft') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="footer">Laporan Kelola Data - <?= $escape($labelPeriode) ?></div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $exportStage = 'merender laporan PDF';
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $filename = sprintf(
        'Rekap_%s_%s_%s.pdf',
        preg_replace('/[^A-Za-z0-9_-]/', '_', $jenis),
        preg_replace('/[^A-Za-z0-9_-]/', '_', $kategori),
        $rangeStart === $rangeEnd ? $rangeStart : $rangeStart . '_sd_' . $rangeEnd
    );
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
} catch (Throwable $error) {
    while (ob_get_level() > $initialBufferLevel) {
        ob_end_clean();
    }

    $errorId = bin2hex(random_bytes(4));
    error_log(sprintf(
        '[PDF export %s] Stage: %s; %s: %s',
        $errorId,
        $exportStage,
        get_class($error),
        $error->getMessage()
    ));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo "Unduhan PDF gagal pada tahap: {$exportStage}.\n";
    echo "Kode diagnostik: {$errorId}.";
    exit;
}
