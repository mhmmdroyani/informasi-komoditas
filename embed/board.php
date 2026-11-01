<?php
// embed/board.php - Master Template Board Infografis Karantina Kalsel (Format PPT Canva)
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/period_aggregation.php';
require_once __DIR__ . '/../includes/annual_comparison.php';

$assetPath = $assetPath ?? '';
$showControls = $show_controls ?? (isset($_GET['nav']) && $_GET['nav'] == 1);
$comparisonPage = $comparisonPage ?? false;

$kategori = $_GET['kategori'] ?? 'hewan';
if (!in_array($kategori, ['hewan', 'ikan', 'tumbuhan'])) {
    $kategori = 'hewan';
}

$jenis = $_GET['jenis'] ?? ($_GET['kegiatan'] ?? 'domestik_masuk');
if (!in_array($jenis, ['ekspor', 'impor', 'domestik_keluar', 'domestik_masuk'])) {
    $jenis = 'domestik_masuk';
}
$isDomestik = in_array($jenis, ['domestik_keluar', 'domestik_masuk'], true);
$fieldTujuan = match ($jenis) {
    'domestik_keluar' => 'daerah_tujuan',
    'domestik_masuk' => 'daerah_asal',
    default => 'negara_tujuan',
};

// 1. Ambil Setting
$stmtSet = $pdo->prepare("SELECT * FROM ekspor_settings WHERE jenis_kegiatan = ? AND kategori = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
$stmtSet->execute([$jenis, $kategori]);
$setting = $stmtSet->fetch() ?: [
    'top_n' => 5,
    'urutkan_berdasarkan' => 'nilai_ekspor',
    'filter_mode' => 'tahun',
    'tanggal_awal' => '2026-01-01',
    'tanggal_akhir' => '2026-12-31',
    'periode_label' => 'TAHUN 2026'
];

$topN = (int)($setting['top_n'] ?? 5);
$urutan = (($setting['urutkan_berdasarkan'] ?? '') === 'frekuensi') ? 'frekuensi' : 'nilai_ekspor';
$periodeStmt = $pdo->prepare("SELECT DISTINCT periode FROM ekspor_komoditas WHERE jenis_kegiatan = ? AND kategori = ? AND is_published = 1 AND periode REGEXP '^[0-9]{4}-[0-9]{2}$' ORDER BY periode ASC");
$periodeStmt->execute([$jenis, $kategori]);
$publishedPeriods = $periodeStmt->fetchAll(PDO::FETCH_COLUMN);
$periodeOptionSet = array_fill_keys($publishedPeriods, true);

$tanggalAwal = (string)($setting['tanggal_awal'] ?? '');
$tanggalAkhir = (string)($setting['tanggal_akhir'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal) && !empty($setting['tahun_awal'])) {
    $tanggalAwal = (int)$setting['tahun_awal'] . '-01-01';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir) && !empty($setting['tahun_akhir'])) {
    $tanggalAkhir = (int)$setting['tahun_akhir'] . '-12-31';
}
$bulanAwal = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal) ? substr($tanggalAwal, 0, 7) : '';
$bulanAkhir = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir) ? substr($tanggalAkhir, 0, 7) : '';
if (
    preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanAwal)
    && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanAkhir)
    && checkdate((int)substr($bulanAwal, 5, 2), 1, (int)substr($bulanAwal, 0, 4))
    && checkdate((int)substr($bulanAkhir, 5, 2), 1, (int)substr($bulanAkhir, 0, 4))
    && $bulanAwal <= $bulanAkhir
) {
    $bulanBerjalan = new DateTimeImmutable($bulanAwal . '-01');
    $bulanTerakhir = new DateTimeImmutable($bulanAkhir . '-01');
    $batasBulan = 0;
    while ($bulanBerjalan <= $bulanTerakhir && $batasBulan < 2400) {
        $periodeOptionSet[$bulanBerjalan->format('Y-m')] = true;
        $bulanBerjalan = $bulanBerjalan->modify('+1 month');
        $batasBulan++;
    }
}
$periodeOptions = array_keys($periodeOptionSet);
sort($periodeOptions, SORT_STRING);
$periodeA = trim($_GET['periode_a'] ?? ($periodeOptions[0] ?? ''));
$defaultPeriodeB = (new DateTimeImmutable('first day of this month'))
    ->modify('-1 month')
    ->format('Y-m');
if (!in_array($defaultPeriodeB, $periodeOptions, true)) {
    $availablePreviousPeriods = array_values(array_filter(
        $periodeOptions,
        static function ($period) use ($defaultPeriodeB) {
            return $period <= $defaultPeriodeB;
        }
    ));
    $defaultPeriodeB = $availablePreviousPeriods !== []
        ? end($availablePreviousPeriods)
        : ($periodeOptions[count($periodeOptions) - 1] ?? '');
}
$periodeB = trim($_GET['periode_b'] ?? $defaultPeriodeB);
if (!in_array($periodeA, $periodeOptions, true)) $periodeA = $periodeOptions[0] ?? '';
if (!in_array($periodeB, $periodeOptions, true)) $periodeB = $periodeOptions[count($periodeOptions) - 1] ?? '';
$rentangPeriodeAwal = min($periodeA, $periodeB);
$rentangPeriodeAkhir = max($periodeA, $periodeB);
$periodeFilter = $periodeB;
$yearOptions = published_comparison_years($publishedPeriods);
$comparisonYearA = trim($_GET['tahun_a'] ?? substr($periodeA, 0, 4));
$comparisonYearB = trim($_GET['tahun_b'] ?? substr($periodeB, 0, 4));
if (!in_array($comparisonYearA, $yearOptions, true)) {
    $comparisonYearA = $yearOptions[0] ?? '';
}
if (!in_array($comparisonYearB, $yearOptions, true)) {
    $comparisonYearB = $yearOptions[count($yearOptions) - 1] ?? '';
}
$comparisonYearStart = $comparisonYearA !== '' && $comparisonYearB !== ''
    ? min($comparisonYearA, $comparisonYearB)
    : '';
$comparisonYearEnd = $comparisonYearA !== '' && $comparisonYearB !== ''
    ? max($comparisonYearA, $comparisonYearB)
    : '';
$comparisonYears = $comparisonYearStart !== '' && $comparisonYearEnd !== ''
    ? range((int)$comparisonYearStart, (int)$comparisonYearEnd)
    : [];
$comparisonYears = array_map('strval', $comparisonYears);

if ($periodeB !== '') {
    $stmtPeriodSet = $pdo->prepare("SELECT * FROM ekspor_settings WHERE jenis_kegiatan = ? AND kategori = ? AND periode = ? LIMIT 1");
    $stmtPeriodSet->execute([$jenis, $kategori, $periodeB]);
    $periodSetting = $stmtPeriodSet->fetch();
    if ($periodSetting) {
        $setting = array_merge($setting, $periodSetting);
        $topN = (int)($setting['top_n'] ?? 5);
        $urutan = (($setting['urutkan_berdasarkan'] ?? '') === 'frekuensi') ? 'frekuensi' : 'nilai_ekspor';
    }
}
$requestedTopN = filter_var($_GET['top_n'] ?? null, FILTER_VALIDATE_INT);
if ($requestedTopN !== false && $requestedTopN !== null && in_array($requestedTopN, [0, 1, 3, 5, 10], true)) {
    $topN = $requestedTopN;
}
$requestedSort = $_GET['urutkan'] ?? '';
if (is_string($requestedSort) && in_array($requestedSort, ['nilai_ekspor', 'frekuensi'], true)) {
    $urutan = $requestedSort;
}
$compareMode = $comparisonPage && $comparisonYearStart !== '' && $comparisonYearEnd !== '';
$comparisonLabelA = format_label_periode_kode($periodeA) ?: $periodeA;
$comparisonLabelB = format_label_periode_kode($periodeB) ?: $periodeB;
$comparisonRangeLabel = $comparisonPage
    ? ($comparisonYearStart === $comparisonYearEnd
        ? $comparisonYearStart
        : $comparisonYearStart . ' - ' . $comparisonYearEnd)
    : ($periodeA === $periodeB
        ? $comparisonLabelA
        : (format_label_periode_kode($rentangPeriodeAwal) ?: $rentangPeriodeAwal)
            . ' - ' .
            (format_label_periode_kode($rentangPeriodeAkhir) ?: $rentangPeriodeAkhir));

$comparisonMonthly = [];
if ($compareMode) {
    $trendStmt = $pdo->prepare("SELECT periode, nilai_ekspor, volume, satuan, frekuensi, {$fieldTujuan} AS lokasi_tujuan
        FROM ekspor_komoditas
        WHERE jenis_kegiatan = ? AND kategori = ? AND is_published = 1
            AND periode REGEXP '^[0-9]{4}-[0-9]{2}$'
            AND periode >= ? AND periode <= ?
        ORDER BY periode");
    $trendStmt->execute([$jenis, $kategori, $comparisonYearStart . '-01', $comparisonYearEnd . '-12']);
    $trendRows = $trendStmt->fetchAll();
    $comparisonMonthly = aggregate_comparison_months($comparisonYears, $trendRows);
}

// Filter kalender: utamakan parameter URL jika ada, jika tidak gunakan setting tersimpan
if (isset($_GET['bln_awal']) || isset($_GET['thn_awal']) || isset($_GET['tahun_awal']) || isset($_GET['tgl_awal']) || isset($_GET['mode'])) {
    $cal = parse_calendar_filter('bulan', $_GET);
    $tglAwal = $cal['tgl_awal'];
    $tglAkhir = $cal['tgl_akhir'];
    $labelTahunPublik = $cal['label_tahun'];
} else {
    $tglAwal = $setting['tanggal_awal'] ?? null;
    $tglAkhir = $setting['tanggal_akhir'] ?? null;
    // Selalu hitung ulang dari rentang tanggal tersimpan agar selalu sinkron dengan bulan yang dipilih di Kelola Data
    $labelTahunPublik = format_label_tahun($tglAwal, $tglAkhir, '');
}

$labelTahunPublik = $comparisonPage
    ? ($compareMode ? $comparisonRangeLabel : 'BELUM ADA PERIODE DATA')
    : ($periodeA !== '' && $periodeB !== ''
        ? ($rentangPeriodeAwal === $rentangPeriodeAkhir
            ? (format_label_periode_kode($rentangPeriodeAwal) ?: $rentangPeriodeAwal)
            : (format_label_periode_kode($rentangPeriodeAwal) ?: $rentangPeriodeAwal)
                . ' - ' .
                (format_label_periode_kode($rentangPeriodeAkhir) ?: $rentangPeriodeAkhir))
        : 'BELUM ADA PERIODE DATA');

// 2. Query Data Published
$queryParams = [$jenis, $kategori];
$sql = "SELECT * FROM ekspor_komoditas WHERE jenis_kegiatan = ? AND kategori = ? AND is_published = 1";
if ($comparisonPage && $compareMode) {
    $sql .= " AND periode REGEXP '^[0-9]{4}-[0-9]{2}$' AND periode >= ? AND periode <= ?";
    $queryParams[] = $comparisonYearStart . '-01';
    $queryParams[] = $comparisonYearEnd . '-12';
} elseif (!$comparisonPage && $periodeA !== '' && $periodeB !== '') {
    $sql .= " AND periode REGEXP '^[0-9]{4}-[0-9]{2}$' AND periode >= ? AND periode <= ?";
    $queryParams[] = $rentangPeriodeAwal;
    $queryParams[] = $rentangPeriodeAkhir;
} else {
    $sql .= " AND 1 = 0";
}
$kolomUrutanSekunder = $urutan === 'frekuensi' ? 'nilai_ekspor' : 'frekuensi';
$sql .= " ORDER BY {$urutan} DESC, {$kolomUrutanSekunder} DESC, nama_komoditas ASC";
$stmtData = $pdo->prepare($sql);
$stmtData->execute($queryParams);
$allMonthlyData = $stmtData->fetchAll();

$allData = $allMonthlyData;
if (!$comparisonPage) {
    $allData = aggregate_commodity_rows($allMonthlyData);
    usort($allData, static function ($left, $right) use ($urutan) {
        $primary = (float)$right[$urutan] <=> (float)$left[$urutan];
        if ($primary !== 0) {
            return $primary;
        }
        $secondaryColumn = $urutan === 'frekuensi' ? 'nilai_ekspor' : 'frekuensi';
        $secondary = (float)$right[$secondaryColumn] <=> (float)$left[$secondaryColumn];
        return $secondary !== 0 ? $secondary : strcasecmp($left['nama_komoditas'], $right['nama_komoditas']);
    });
}

// Batasi tampilan Top N, tetapi tetap gunakan seluruh dataset yang terpilih untuk agregat KPI.
$data = $allData;
if ($topN > 0 && count($data) > $topN) {
    $data = array_slice($data, 0, $topN);
}
// 3. Hitung Metrik dari seluruh data yang dipilih, bukan hanya baris yang ditampilkan
$totalNilai = (float)array_sum(array_column($allData, 'nilai_ekspor'));
$totalFrekuensi = (int)array_sum(array_column($allData, 'frekuensi'));
$summaryTotal = $urutan === 'frekuensi' ? $totalFrekuensi : $totalNilai;
$volumeByUnit = [];
foreach ($allData as $row) {
    $unit = trim((string)($row['satuan'] ?? '')) ?: 'Kilogram';
    $volumeByUnit[$unit] = ($volumeByUnit[$unit] ?? 0) + (float)$row['volume'];
}
arsort($volumeByUnit);
$primaryVolume = (float)(reset($volumeByUnit) ?: 0);
$primaryVolumeUnit = (string)(key($volumeByUnit) ?: 'Kilogram');
$secondaryVolumes = array_slice($volumeByUnit, 1, null, true);

$kpiNilai = format_kpi_nilai($totalNilai);

// Arah & Wilayah
$countryAliases = [
    'china' => 'China', 'tiongkok' => 'China',
    'hong kong' => 'Hong Kong', 'hongkong' => 'Hong Kong',
    'taiwan' => 'Taiwan',
    'singapura' => 'Singapura', 'singapore' => 'Singapura',
    'jepang' => 'Jepang', 'japan' => 'Jepang',
    'malaysia' => 'Malaysia',
    'vietnam' => 'Vietnam',
    'thailand' => 'Thailand',
    'india' => 'India',
    'korea selatan' => 'Korea Selatan', 'south korea' => 'Korea Selatan', 'korea' => 'Korea Selatan',
    'filipina' => 'Filipina', 'philippines' => 'Filipina', 'phillipina' => 'Filipina', 'philipina' => 'Filipina',
    'brunei' => 'Brunei', 'brunei darussalam' => 'Brunei',
    'amerika serikat' => 'Amerika Serikat', 'united states' => 'Amerika Serikat', 'usa' => 'Amerika Serikat', 'america' => 'Amerika Serikat',
    'kanada' => 'Kanada', 'canada' => 'Kanada',
    'australia' => 'Australia',
    'selandia baru' => 'Selandia Baru', 'new zealand' => 'Selandia Baru',
    'belanda' => 'Belanda', 'netherlands' => 'Belanda',
    'jerman' => 'Jerman', 'germany' => 'Jerman',
    'italia' => 'Italia', 'italy' => 'Italia',
    'prancis' => 'Prancis', 'france' => 'Prancis',
    'inggris' => 'Inggris', 'united kingdom' => 'Inggris',
    'spanyol' => 'Spanyol', 'spain' => 'Spanyol',
    'rusia' => 'Rusia', 'russia' => 'Rusia',
    'uni emirat arab' => 'Uni Emirat Arab', 'united arab emirates' => 'Uni Emirat Arab', 'uae' => 'Uni Emirat Arab',
    'arab saudi' => 'Arab Saudi', 'saudi arabia' => 'Arab Saudi',
    'qatar' => 'Qatar',
    'bangladesh' => 'Bangladesh', 'banglades' => 'Bangladesh',
    'turki' => 'Turki', 'turkey' => 'Turki',
    'mesir' => 'Mesir', 'egypt' => 'Mesir',
    'afrika selatan' => 'Afrika Selatan', 'south africa' => 'Afrika Selatan',
    'brasil' => 'Brasil', 'brazil' => 'Brasil',
    'chile' => 'Chile',
    'meksiko' => 'Meksiko', 'mexico' => 'Meksiko'
];

$normalizeCountryName = function ($name) use ($countryAliases) {
    $value = trim((string)$name);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = preg_replace('/[;|\/]+/', ',', $value);
    $normalized = strtolower($value);
    foreach ($countryAliases as $alias => $canonical) {
        if ($normalized === strtolower($alias)) {
            return $canonical;
        }
    }
    $normalized = preg_replace('/[^a-z0-9 ,\-]/i', '', $normalized);
    $normalized = preg_replace('/\s+/', ' ', trim($normalized));
    return ucwords(strtolower($normalized));
};

$uniqueDest = [];
$destCounts = [];
$destCountsBySelection = [];
foreach ($allMonthlyData as $r) {
    $normalizedVolume = normalize_compatible_volume_unit($r['volume'], $r['satuan'] ?? '');
    $commodityKey = strtolower(preg_replace(
        '/\s+/',
        ' ',
        normalize_commodity_group_name($r['nama_komoditas'] ?? '')
    ));
    $unitKey = strtolower(preg_replace('/\s+/', ' ', $normalizedVolume['unit']));
    $selectionKey = hash('sha256', $commodityKey . "\0" . $unitKey);
    if (!empty($r[$fieldTujuan])) {
        $parts = preg_split('/\s*(?:,|;|\/|\|)\s*/', (string)$r[$fieldTujuan]);
        $validParts = array_values(array_filter($parts, fn($x) => trim((string)$x) !== ''));
        $rowDestinations = [];
        foreach ($validParts as $p) {
            $destinationName = $isDomestik
                ? preg_replace('/\s+/', ' ', trim((string)$p))
                : $normalizeCountryName($p);
            if ($destinationName === '') {
                continue;
            }
            $destinationKey = strtolower($destinationName);
            $uniqueDest[$destinationKey] = $destinationName;
            $rowDestinations[$destinationKey] = true;
        }
        foreach ($rowDestinations as $destinationKey => $_) {
            $destCounts[$destinationKey] = ($destCounts[$destinationKey] ?? 0) + 1;
            $destCountsBySelection[$selectionKey][$destinationKey] =
                ($destCountsBySelection[$selectionKey][$destinationKey] ?? 0) + 1;
        }
    }
}
$daftarDest = array_values($uniqueDest);
$jumlahWilayah = count($daftarDest);

// Subteks Wilayah
$countryFlagCodes = [
    'china' => 'cn', 'tiongkok' => 'cn', 'hong kong' => 'hk', 'taiwan' => 'tw',
    'sri lanka' => 'lk', 'pakistan' => 'pk', 'kazakhstan' => 'kz',
    'singapura' => 'sg', 'singapore' => 'sg', 'jepang' => 'jp', 'japan' => 'jp',
    'malaysia' => 'my', 'vietnam' => 'vn', 'thailand' => 'th', 'india' => 'in',
    'korea selatan' => 'kr', 'south korea' => 'kr', 'filipina' => 'ph',
        'philippines' => 'ph', 'phillipina' => 'ph', 'philipina' => 'ph', 'brunei' => 'bn', 'brunei darussalam' => 'bn',
    'amerika serikat' => 'us', 'united states' => 'us', 'usa' => 'us',
    'kanada' => 'ca', 'canada' => 'ca', 'australia' => 'au',
    'selandia baru' => 'nz', 'new zealand' => 'nz', 'belanda' => 'nl',
    'netherlands' => 'nl', 'jerman' => 'de', 'germany' => 'de',
    'italia' => 'it', 'italy' => 'it', 'prancis' => 'fr', 'france' => 'fr',
    'inggris' => 'gb', 'united kingdom' => 'gb', 'spanyol' => 'es', 'spain' => 'es',
    'rusia' => 'ru', 'russia' => 'ru', 'uni emirat arab' => 'ae', 'uni emirad arab' => 'ae',
    'united arab emirates' => 'ae', 'arab saudi' => 'sa', 'saudi arabia' => 'sa',
    'qatar' => 'qa', 'bangladesh' => 'bd', 'banglades' => 'bd',
    'turki' => 'tr', 'turkey' => 'tr', 'mesir' => 'eg',
    'egypt' => 'eg', 'afrika selatan' => 'za', 'south africa' => 'za',
    'brasil' => 'br', 'brazil' => 'br', 'chile' => 'cl', 'meksiko' => 'mx',
    'mexico' => 'mx', 'venezuela' => 've', 'argentina' => 'ar',
    'portugal' => 'pt', 'belgia' => 'be', 'belgium' => 'be',
    'kolombia' => 'co', 'colombia' => 'co', 'peru' => 'pe',
    'ecuador' => 'ec', 'panama' => 'pa',
    'new caledonia' => 'nc', 'papua nugini' => 'pg', 'papua new guinea' => 'pg'
];
$locationsByCommodity = [];
foreach ($destCountsBySelection as $selectionKey => $locations) {
    arsort($locations);
    $locationsByCommodity[$selectionKey] = array_map(
        static function ($name, $count) use ($isDomestik, $countryFlagCodes) {
            $flag = null;
            if (!$isDomestik) {
                foreach ($countryFlagCodes as $countryName => $countryCode) {
                    if (strtolower(trim($name)) === $countryName || strpos(strtolower(trim($name)), $countryName) !== false) {
                        $flag = $countryCode;
                        break;
                    }
                }
            }
            return ['name' => $name, 'count' => $count, 'flag' => $flag];
        },
        array_keys($locations),
        array_values($locations)
    );
}
if ($isDomestik) {
    $subWilayah = max(1, count($daftarDest)) . ' Provinsi/Area';
} else {
    $subWilayah = hitung_benua($daftarDest) . ' Benua';
}

arsort($destCounts);

// Labels
$labelKegiatan = get_label_kegiatan($jenis);
$labelKategori = ucfirst($kategori);
$labelArah = get_label_arah($jenis);
$labelArahUpper = $labelArah;
$labelSatuanWilayah = get_label_wilayah($jenis);
$labelDokumen = get_label_sertifikat($jenis, $kategori);
$summaryMetricLabel = $urutan === 'frekuensi' ? 'Frekuensi' : 'Nilai ' . $labelKegiatan;

// Top Commodity Name
$topKomoditasName = 'Komoditas';
if (!empty($data)) {
    if ($kategori === 'ikan' && !empty($data[0]['nama_komoditas_lokal'])) {
        $topKomoditasName = $data[0]['nama_komoditas_lokal'];
    } else {
        $topKomoditasName = $data[0]['nama_komoditas'];
    }
}

// Chart Colors
$colors = ['#0f766e', '#2563eb', '#b45309', '#64748b', '#7c3aed'];
$summaryItems = [];
foreach ($data as $idx => $row) {
    $summaryItems[] = [
        'key' => $row['_selection_key'] ?? '',
        'name' => ($kategori === 'ikan' && !empty($row['nama_komoditas_lokal']))
            ? $row['nama_komoditas_lokal']
            : $row['nama_komoditas'],
        'value' => (float)$row[$urutan],
        'color' => $colors[$idx % count($colors)],
    ];
}
$hiddenItems = $topN > 0 ? array_slice($allData, $topN) : [];
if ($hiddenItems !== []) {
    $otherDestinationCounts = [];
    foreach ($hiddenItems as $row) {
        foreach ($locationsByCommodity[$row['_selection_key'] ?? ''] ?? [] as $location) {
            $locationKey = strtolower($location['name']);
            if (!isset($otherDestinationCounts[$locationKey])) {
                $otherDestinationCounts[$locationKey] = $location;
            } else {
                $otherDestinationCounts[$locationKey]['count'] += $location['count'];
            }
        }
    }
    uasort($otherDestinationCounts, static function ($left, $right) {
        return $right['count'] <=> $left['count'];
    });
    $locationsByCommodity['other'] = array_values($otherDestinationCounts);
    $summaryItems[] = [
        'key' => 'other',
        'name' => 'Lainnya',
        'value' => (float)array_sum(array_column($hiddenItems, $urutan)),
        'color' => '#94a3b8',
    ];
}


function formatVolumeOtomatis($volume, $satuan) {
    $volume = (float) $volume;
    $satuanAsli = trim((string) $satuan);
    $satuanLower = strtolower($satuanAsli);

    // Konversi Kg menjadi Ton jika mencapai 1.000 Kg
    if (
        in_array($satuanLower, ['kg', 'kilogram', 'kilograms'])
        && $volume >= 1000
    ) {
        $volumeTon = $volume / 1000;
        $tonDecimals = abs($volumeTon - round($volumeTon)) < 0.000001 ? 0 : 2;
        return [
            'angka' => format_angka($volumeTon, $tonDecimals),
            'satuan' => 'Ton'
        ];
    }

    return [
        'angka' => format_angka(
            $volume,
            fmod($volume, 1) == 0.0 ? 0 : 2
        ),
        'satuan' => $satuanAsli !== '' ? $satuanAsli : 'Kilogram'
    ];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $labelKegiatan ?> Komoditas Karantina <?= $labelKategori ?> - Balai Karantina Kalimantan Selatan</title>
    <link rel="stylesheet" href="<?= $assetPath ?>embed.css?v=<?= @filemtime(__DIR__ . '/embed.css') ?: time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet"href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<?php
$filterParams = [
    'top_n' => $topN,
    'urutkan' => $urutan,
    'periode_a' => $periodeA,
    'periode_b' => $periodeB
];
$boardFilterParams = array_merge(['jenis' => $jenis, 'kategori' => $kategori], $filterParams);
$activeFilterParams = $comparisonPage
    ? array_merge($filterParams, [
        'tahun_a' => $comparisonYearA,
        'tahun_b' => $comparisonYearB
    ])
    : $filterParams;
$comparisonFilterParams = array_merge(['jenis' => $jenis, 'kategori' => $kategori], $activeFilterParams);
$comparisonPageUrl = $assetPath . 'perbandingan.php?' . http_build_query($comparisonFilterParams);
$urlParamsExtra = '&' . http_build_query($activeFilterParams);
$topFilterUrls = [];
foreach ([1, 3, 5, 10, 0] as $topOption) {
    $params = $comparisonPage ? $comparisonFilterParams : $boardFilterParams;
    $params['top_n'] = $topOption;
    $topFilterUrls[$topOption] = '?' . http_build_query($params);
}
$sortFilterUrls = [];
foreach (['nilai_ekspor', 'frekuensi'] as $sortOption) {
    $params = $comparisonPage ? $comparisonFilterParams : $boardFilterParams;
    $params['urutkan'] = $sortOption;
    $sortFilterUrls[$sortOption] = '?' . http_build_query($params);
}
$comparisonYearAUrls = [];
foreach ($yearOptions as $yearOption) {
    $params = $comparisonFilterParams;
    $params['tahun_a'] = $yearOption;
    $comparisonYearAUrls[$yearOption] = '?' . http_build_query($params);
}
$comparisonYearBUrls = [];
foreach ($yearOptions as $yearOption) {
    $params = $comparisonFilterParams;
    $params['tahun_b'] = $yearOption;
    $comparisonYearBUrls[$yearOption] = '?' . http_build_query($params);
}
$periodeAUrls = [];
foreach ($periodeOptions as $periodOption) {
    $params = $boardFilterParams;
    $params['periode_a'] = $periodOption;
    $params['periode_b'] = $periodOption;
    $periodeAUrls[$periodOption] = '?' . http_build_query($params);
}
$periodeBUrls = [];
foreach ($periodeOptions as $periodOption) {
    $params = $boardFilterParams;
    $params['periode_b'] = $periodOption;
    $periodeBUrls[$periodOption] = '?' . http_build_query($params);
}
?>

<!-- Top Bar -->
<header class="board-topbar">
    <div class="board-topbar-inner">
        <div class="header-left">
            <img src="<?= $assetPath ?>logo-barantin.png" alt="Logo Barantin" class="barantin-logo-img">
            <div class="header-unit-text">
                <span class="hut-karantina">Badan Karantina Indonesia</span>
                <span class="hut-kalsel">Balai Karantina Kalimantan Selatan</span>
            </div>
        </div>

        <?php if ($showControls): ?>
        <div class="board-topbar-actions">
            <a href="<?= $assetPath ?>../admin/index.php" class="board-login-btn">Login</a>
        </div>
        <?php endif; ?>
    </div>
</header>

<div class="board-container">

    <!-- Hero -->
    <section class="board-hero">
        <h1 class="board-hero-title"><?= $comparisonPage ? 'Mode Grafik' : 'Top ' . ($topN > 0 ? $topN : 'Semua') . ' Komoditas' ?> <?= $labelKategori ?> — <?= $labelKegiatan ?></h1>

        <div class="board-periode-wrap">
            <span class="board-periode-badge"><?= htmlspecialchars(str_replace(' - ', ' – ', $labelTahunPublik)) ?></span>
                <button class="board-download-btn" type="button" onclick="openPrintBoard()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></svg>
                Cetak
                </button>
        </div>

        <?php if ($showControls): ?>
        <div class="board-hero-controls">
            <label class="hero-select-field">
                <span class="hero-filter-label">Lalu Lintas</span>
                <select name="jenis" id="selectJenis" onchange="window.location.href = this.value" aria-label="Pilih lalu lintas">
                    <option value="?jenis=domestik_masuk&kategori=<?= $kategori ?><?= $urlParamsExtra ?>" <?= $jenis === 'domestik_masuk' ? 'selected' : '' ?>>Domestik Masuk</option>
                    <option value="?jenis=domestik_keluar&kategori=<?= $kategori ?><?= $urlParamsExtra ?>" <?= $jenis === 'domestik_keluar' ? 'selected' : '' ?>>Domestik Keluar</option>
                    <option value="?jenis=ekspor&kategori=<?= $kategori ?><?= $urlParamsExtra ?>" <?= $jenis === 'ekspor' ? 'selected' : '' ?>>Ekspor</option>
                    <option value="?jenis=impor&kategori=<?= $kategori ?><?= $urlParamsExtra ?>" <?= $jenis === 'impor' ? 'selected' : '' ?>>Impor</option>
                </select>
            </label>

            <label class="hero-select-field">
                <span class="hero-filter-label">Komoditas</span>
                <select name="kategori" id="selectKategori" onchange="window.location.href = this.value" aria-label="Pilih komoditas">
                    <option value="?jenis=<?= $jenis ?>&kategori=hewan<?= $urlParamsExtra ?>" <?= $kategori === 'hewan' ? 'selected' : '' ?>>Hewan</option>
                    <option value="?jenis=<?= $jenis ?>&kategori=ikan<?= $urlParamsExtra ?>" <?= $kategori === 'ikan' ? 'selected' : '' ?>>Ikan</option>
                    <option value="?jenis=<?= $jenis ?>&kategori=tumbuhan<?= $urlParamsExtra ?>" <?= $kategori === 'tumbuhan' ? 'selected' : '' ?>>Tumbuhan</option>
                </select>
            </label>

            <?php if ($comparisonPage): ?>
                <label class="hero-select-field">
                    <span class="hero-filter-label">Tahun Awal</span>
                    <select name="tahun_a" aria-label="Pilih tahun awal grafik" onchange="window.location.href = this.value" <?= empty($yearOptions) ? 'disabled' : '' ?>>
                        <?php foreach ($yearOptions as $yearOption): ?>
                            <option value="<?= htmlspecialchars($comparisonYearAUrls[$yearOption], ENT_QUOTES, 'UTF-8') ?>" <?= $comparisonYearA === $yearOption ? 'selected' : '' ?>><?= htmlspecialchars($yearOption) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($yearOptions)): ?>
                            <option value="">Belum ada data tahunan</option>
                        <?php endif; ?>
                    </select>
                </label>

                <label class="hero-select-field">
                    <span class="hero-filter-label">Tahun Akhir</span>
                    <select name="tahun_b" aria-label="Pilih tahun akhir grafik" onchange="window.location.href = this.value" <?= empty($yearOptions) ? 'disabled' : '' ?>>
                        <?php foreach ($yearOptions as $yearOption): ?>
                            <option value="<?= htmlspecialchars($comparisonYearBUrls[$yearOption], ENT_QUOTES, 'UTF-8') ?>" <?= $comparisonYearB === $yearOption ? 'selected' : '' ?>><?= htmlspecialchars($yearOption) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($yearOptions)): ?>
                            <option value="">Belum ada data tahunan</option>
                        <?php endif; ?>
                    </select>
                </label>
            <?php else: ?>
                <label class="hero-select-field">
                    <span class="hero-filter-label">Periode A</span>
                    <select name="periode_a" aria-label="Pilih bulan periode A" onchange="window.location.href = this.value" <?= empty($periodeOptions) ? 'disabled' : '' ?>>
                        <?php foreach ($periodeOptions as $periodOption): ?>
                            <option value="<?= htmlspecialchars($periodeAUrls[$periodOption], ENT_QUOTES, 'UTF-8') ?>" <?= $periodeA === $periodOption ? 'selected' : '' ?>><?= htmlspecialchars(format_label_periode_kode($periodOption) ?: $periodOption) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($periodeOptions)): ?>
                            <option value="">Belum ada data bulanan</option>
                        <?php endif; ?>
                    </select>
                </label>

                <label class="hero-select-field">
                    <span class="hero-filter-label">Periode B</span>
                    <select name="periode_b" aria-label="Pilih bulan periode B" onchange="window.location.href = this.value" <?= empty($periodeOptions) ? 'disabled' : '' ?>>
                        <?php foreach ($periodeOptions as $periodOption): ?>
                            <option value="<?= htmlspecialchars($periodeBUrls[$periodOption], ENT_QUOTES, 'UTF-8') ?>" <?= $periodeB === $periodOption ? 'selected' : '' ?>><?= htmlspecialchars(format_label_periode_kode($periodOption) ?: $periodOption) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($periodeOptions)): ?>
                            <option value="">Belum ada data bulanan</option>
                        <?php endif; ?>
                    </select>
                </label>
            <?php endif; ?>

            <label class="hero-select-field">
                <span class="hero-filter-label">Tampilkan</span>
                <select name="top_n" aria-label="Jumlah komoditas" onchange="window.location.href = this.value">
                    <option value="<?= htmlspecialchars($topFilterUrls[1], ENT_QUOTES, 'UTF-8') ?>" <?= $topN === 1 ? 'selected' : '' ?>>Top 1</option>
                    <option value="<?= htmlspecialchars($topFilterUrls[3], ENT_QUOTES, 'UTF-8') ?>" <?= $topN === 3 ? 'selected' : '' ?>>Top 3</option>
                    <option value="<?= htmlspecialchars($topFilterUrls[5], ENT_QUOTES, 'UTF-8') ?>" <?= $topN === 5 ? 'selected' : '' ?>>Top 5</option>
                    <option value="<?= htmlspecialchars($topFilterUrls[10], ENT_QUOTES, 'UTF-8') ?>" <?= $topN === 10 ? 'selected' : '' ?>>Top 10</option>
                    <option value="<?= htmlspecialchars($topFilterUrls[0], ENT_QUOTES, 'UTF-8') ?>" <?= $topN === 0 ? 'selected' : '' ?>>Semua Data</option>
                </select>
            </label>

            <div class="board-sort-mode">
                <label class="hero-select-field">
                    <span class="hero-filter-label">Urutkan</span>
                    <select name="urutkan" aria-label="Urutkan komoditas" onchange="window.location.href = this.value">
                        <option value="<?= htmlspecialchars($sortFilterUrls['nilai_ekspor'], ENT_QUOTES, 'UTF-8') ?>" <?= $urutan === 'nilai_ekspor' ? 'selected' : '' ?>>Nilai Barang</option>
                        <option value="<?= htmlspecialchars($sortFilterUrls['frekuensi'], ENT_QUOTES, 'UTF-8') ?>" <?= $urutan === 'frekuensi' ? 'selected' : '' ?>>Frekuensi</option>
                    </select>
                </label>

                <a
                    href="<?= htmlspecialchars($comparisonPage
                        ? $assetPath . '../index.php?' . http_build_query($boardFilterParams)
                        : $comparisonPageUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="board-compare-btn"
                    <?= $comparisonPage ? 'aria-label="Kembali ke ringkasan Board Publik"' : 'aria-label="Buka Mode Grafik"' ?>
                ><?= $comparisonPage ? 'Kembali ke Ringkasan' : 'Mode Grafik' ?></a>
            </div>

        </div>
        <?php endif; ?>
    </section>

    <?php if ($comparisonPage && $compareMode): ?>
        <section class="board-comparison" aria-label="Perbandingan periode">
            <div class="board-comparison-chart">
                <h2>Grafik Perbandingan Bulanan</h2>
                <p><?= htmlspecialchars($comparisonRangeLabel) ?></p>
                <div class="comparison-chart-grid">
                    <div class="comparison-chart-card"><span>Nilai <?= htmlspecialchars($labelKegiatan) ?> (Miliar Rp)</span><div class="comparison-chart-canvas"><canvas id="comparisonChartNilai"></canvas></div></div>
                    <div class="comparison-chart-card"><span>Volume</span><div class="comparison-chart-canvas"><canvas id="comparisonChartVolume"></canvas></div></div>
                    <div class="comparison-chart-card"><span>Frekuensi</span><div class="comparison-chart-canvas"><canvas id="comparisonChartFrekuensi"></canvas></div></div>
                    <div class="comparison-chart-card"><span><?= htmlspecialchars($isDomestik ? get_label_arah($jenis) : 'Negara Tujuan') ?></span><div class="comparison-chart-canvas"><canvas id="comparisonChartNegara"></canvas></div></div>
                </div>
            </div>
            <script>
            const comparisonLabels = <?= json_encode(array_map('nama_bulan_indo', range(1, 12))) ?>;
            const comparisonYears = <?= json_encode($comparisonYears) ?>;
            const comparisonYearData = <?= json_encode(array_intersect_key($comparisonMonthly, array_flip($comparisonYears))) ?>;
            const comparisonColors = ['#2563eb', '#0f766e', '#d97706', '#7c3aed', '#db2777', '#0891b2'];
            const comparisonCharts = [
                ['comparisonChartNilai', 'nilai', 1000000000],
                ['comparisonChartVolume', 'volume', 1],
                ['comparisonChartFrekuensi', 'frekuensi', 1],
                ['comparisonChartNegara', 'negara', 1]
            ];
            comparisonCharts.forEach(function(chartItem) {
                new Chart(document.getElementById(chartItem[0]), {
                    type: 'line',
                    data: {
                        labels: comparisonLabels,
                        datasets: comparisonYears.map(function(year, index) {
                            const color = comparisonColors[index % comparisonColors.length];
                            const values = comparisonYearData[year][chartItem[1]] || {};
                            return {
                                label: year,
                                data: Object.values(values).map(value => (chartItem[1] === 'negara' ? value : value / chartItem[2])),
                                borderColor: color,
                                backgroundColor: color,
                                tension: 0.25
                            };
                        })
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#e5e7eb' } },
                            x: {
                                grid: { display: false },
                                ticks: {
                                    font: {
                                        size: 9
                                    }
                                }
                            }
                        }
                    }
                });
            });
            </script>
        </section>
    <?php endif; ?>

    <?php if (empty($data)): ?>
        <div class="board-empty-state">
            <h3>Data Belum Dipublikasikan</h3>
            <p><?= $periodeB === '' ? 'Belum tersedia data bulanan yang dipublikasikan.' : 'Belum ada data yang dipublikasikan untuk ' . htmlspecialchars($comparisonRangeLabel) . '.' ?></p>
        </div>
    <?php elseif (!$comparisonPage): ?>

        <?php if ($compareMode): ?>
            <div class="board-detail-period">
                <h2>Rincian Komoditas — <?= htmlspecialchars($comparisonLabelB) ?></h2>
                <p>Daftar dan ringkasan berikut menggunakan bulan Periode B.</p>
            </div>
        <?php endif; ?>

        <!-- Kartu Ringkasan -->
        <div class="kpi-row-canva">
            <!-- 1. Nilai -->
            <div class="kpi-card-canva">
                <div class="kpi-icon-circle blue">
                    <svg width="70" height="70" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2H4v2h1v11a3 3 0 003 3h8a3 3 0 003-3V8h1V6h-4zm-6-2h4v2h-4V4zm7 15a1 1 0 01-1 1H8a1 1 0 01-1-1V8h10v11z"/>
                        <path d="M9 10h2v2H9v1h2v1H9v1h3v-1h-1v-1h1a1 1 0 001-1v-1a1 1 0 00-1-1H9z"/>
                    </svg>
                </div>
                <div class="kpi-content-canva">
                    <div class="kpi-title-canva">Nilai <?= $labelKegiatan ?></div>
                    <div class="kpi-number-canva blue"><?= $kpiNilai['angka'] ?></div>
                    <div class="kpi-unit-canva blue"><?= $kpiNilai['satuan'] ?></div>
                </div>
            </div>

            <!-- 2. Volume -->
            <div class="kpi-card-canva">
                <div class="kpi-icon-circle teal">
                    <svg width="70" height="70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                </div>
                <div class="kpi-content-canva">
                    <div class="kpi-title-canva">Volume (Netto)</div>                   
                    <?php
                    $volumeTampilan = formatVolumeOtomatis(
                        $primaryVolume,
                        $primaryVolumeUnit
                    );
                    ?>

                    <div class="kpi-number-canva teal">
                        <?= $volumeTampilan['angka'] ?>
                    </div>
                    <div class="kpi-unit-canva teal">
                        <?= htmlspecialchars($volumeTampilan['satuan']) ?>
                    </div>
                    <div class="kpi-volume-secondary">
                        <?php foreach ($secondaryVolumes as $unit => $volume): ?>
                            <?php $volumeSekunderTampilan = formatVolumeOtomatis($volume, $unit); ?>
                            <span class="kpi-volume-row">
                                <strong><?= $volumeSekunderTampilan['angka'] ?></strong>
                                <small><?= htmlspecialchars(ucwords(strtolower($volumeSekunderTampilan['satuan']), " \t\r\n\f\v/-")) ?></small>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 3. Frekuensi sertifikasi -->
            <div class="kpi-card-canva">
                <div class="kpi-icon-circle lime">
                    <svg width="70" height="70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <div class="kpi-content-canva">
                    <div class="kpi-title-canva">Frekuensi Sertifikasi</div>
                    <div class="kpi-number-canva lime"><?= format_angka($totalFrekuensi) ?></div>
                    <div class="kpi-unit-canva lime">Kali</div>
                    <div class="kpi-subtext-canva"><?= htmlspecialchars($labelDokumen) ?></div>
                </div>
            </div>

            <!-- 4. Negara / daerah -->
            <div class="kpi-card-canva">
                <div class="kpi-icon-circle orange">
                    <svg width="70" height="70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                    </svg>
                </div>
                <div class="kpi-content-canva">
                    <div class="kpi-title-canva"><?= $labelArahUpper ?></div>
                    <div class="kpi-number-canva orange"><?= $jumlahWilayah ?></div>
                    <div class="kpi-unit-canva orange"><?= $labelSatuanWilayah ?></div>
                    <div class="kpi-subtext-canva"><?= $subWilayah ?></div>
                </div>
            </div>
        </div>

        <!-- Panel Ringkasan -->
        <div class="panels-row-canva">
            
            <!-- Panel 1: Kontribusi berdasarkan metrik terpilih -->
            <div class="panel-canva">
                <div class="panel-header-canva">
                    <h3>Kontribusi <?= htmlspecialchars($summaryMetricLabel) ?> berdasarkan Komoditas</h3>
                </div>
                <div class="panel-body-canva donut-panel-body">
                    <div class="donut-layout">
                        <div class="donut-chart-box">
                            <canvas id="donutChartMaster"></canvas>
                            <div class="donut-center-overlay">
                                <span class="dco-label">Total</span>
                                <?php if ($urutan === 'frekuensi'): ?>
                                    <span class="dco-val"><?= format_angka($summaryTotal) ?></span>
                                    <span class="dco-unit">Kali</span>
                                <?php else: ?>
                                    <span class="dco-val"><?= $kpiNilai['angka'] ?></span>
                                    <span class="dco-unit"><?= $kpiNilai['satuan'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="donut-legend-list">
                            <?php foreach ($summaryItems as $item):
                                $pct = $summaryTotal > 0 ? round(($item['value'] / $summaryTotal) * 100, 1) : 0;
                            ?>
                                <div class="legend-item-canva">
                                    <span class="legend-dot-canva" style="background-color: <?= htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') ?>;"></span>
                                    <div class="legend-item-info">
                                        <div class="legend-com-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="legend-row">
                                            <span class="legend-val-badge"><?= $urutan === 'frekuensi' ? format_angka($item['value']) . ' kali' : format_rupiah_singkat($item['value']) ?></span>
                                            <span class="legend-pct"><?= $pct ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Sorotan Komoditas Teratas -->
                    <div class="panel-highlight-box">
                        <div class="ph-icon-box">
                            <?php if ($kategori === 'ikan'): ?>
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 12c-3 4-8 5-12 2l-4 4 1-5c-1.5-2-1.5-5 0-7l-1-5 4 4c4-3 9-2 12 2z"/>
                                </svg>
                            <?php elseif ($kategori === 'tumbuhan'): ?>
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 009-9c0-4.97-4.03-9-9-9s-9 4.03-9 9a9 9 0 009 9zm0 0v-8m0 0l-3 3m3-3l3 3"/>
                                </svg>
                            <?php else: ?>
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="ph-text">
                            <strong><?= htmlspecialchars($topKomoditasName) ?></strong> mendominasi arus <?= strtolower($labelKegiatan) ?> komoditas <?= $kategori ?> di Kalimantan Selatan
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Top Komoditas -->
            <div class="panel-canva">
                <div class="panel-header-canva">
                    <h3>Top Komoditas berdasarkan <?= htmlspecialchars($summaryMetricLabel) ?></h3>
                </div>
                <div class="panel-body-canva top-commodity-panel-body">
                    <div class="top-bar-items">
                        <?php foreach ($summaryItems as $i => $item):
                            $pct = $summaryTotal > 0 ? round(($item['value'] / $summaryTotal) * 100, 1) : 0;
                        ?>
                            <div class="top-bar-row" role="button" tabindex="0" aria-pressed="false" data-selection-key="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Tampilkan <?= htmlspecialchars($labelArahUpper . ' untuk ' . $item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="tbr-badge"><?= $i + 1 ?></span>
                                <span class="tbr-label"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="tbr-val"><?= $urutan === 'frekuensi' ? format_angka($item['value']) . ' kali' : format_rupiah_singkat($item['value']) ?></span>
                                <span class="tbr-pct"><?= $pct ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="bar-chart-container-canva">
                        <canvas id="barChartMaster"></canvas>
                    </div>
                </div>
            </div>

            <!-- Panel 3: Top Negara / Daerah -->
            <div class="panel-canva">
                <div class="panel-header-canva">
                    <h3 id="destinationPanelTitle">Top <?= $labelArahUpper ?></h3>
                </div>
                <div class="panel-body-canva">
                    <div class="map-canvas-container">
                        <div class="dest-list" id="destinationList">
                            <?php 
                            $dIdx = 0;
                            foreach (array_slice($destCounts, 0, $topN > 0 ? $topN : count($destCounts), true) as $dName => $destinationCount):
                                $flagCode = null;
                                if (!$isDomestik) {
                                    $destinationKey = strtolower(trim($dName));
                                    foreach ($countryFlagCodes as $countryName => $countryCode) {
                                        if ($destinationKey === $countryName || strpos($destinationKey, $countryName) !== false) {
                                            $flagCode = $countryCode;
                                            break;
                                        }
                                    }
                                }
                            ?>
                                <div class="dest-row" title="<?= htmlspecialchars($dName . ' — muncul pada ' . $destinationCount . ' baris data') ?>">
                                    <?php if ($flagCode): ?><img class="dest-flag" src="https://flagcdn.com/w40/<?= $flagCode ?>.png" alt="Bendera <?= htmlspecialchars($dName) ?>"><?php endif; ?>
                                    <?php if (!$flagCode): ?><span class="dest-dot" style="background-color: <?= $colors[$dIdx % count($colors)] ?>;"></span><?php endif; ?>
                                    <span class="dest-name"><?= htmlspecialchars(strtoupper($dName)) ?></span>
                                    <span class="dest-count" aria-label="Muncul pada <?= number_format($destinationCount, 0, ',', '.') ?> baris data"><?= number_format($destinationCount, 0, ',', '.') ?> data</span>
                                </div>
                            <?php $dIdx++; endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <script>
        const summaryLabels = <?= json_encode(array_column($summaryItems, 'name'), JSON_UNESCAPED_UNICODE) ?>;
        const summaryValues = <?= json_encode(array_map('floatval', array_column($summaryItems, 'value'))) ?>;
        const summaryColors = <?= json_encode(array_column($summaryItems, 'color')) ?>;
        const storedAccessibilityPreferences = (() => {
            try {
                return JSON.parse(localStorage.getItem('boardAccessibilityPreferences') || '{}');
            } catch (error) {
                console.warn('Preferensi aksesibilitas grafik tidak dapat dibaca.', error);
                return {};
            }
        })();
        const initialChartPalette = storedAccessibilityPreferences.contrast === 'high'
            ? ['#000000', '#404040', '#737373', '#a6a6a6', '#d9d9d9']
            : (storedAccessibilityPreferences.colorBlind
                ? ['#0072B2', '#E69F00', '#009E73', '#CC79A7', '#56B4E9', '#D55E00', '#F0E442']
                : null);
        const summaryUsesFrequency = <?= json_encode($urutan === 'frekuensi') ?>;
        const locationsByCommodity = <?= json_encode($locationsByCommodity, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const destinationLabel = <?= json_encode($labelArahUpper, JSON_UNESCAPED_UNICODE) ?>;
        const destinationList = document.getElementById('destinationList');
        const destinationPanelTitle = document.getElementById('destinationPanelTitle');
        const defaultDestinationTitle = destinationPanelTitle.textContent;
        const defaultDestinationRows = Array.from(destinationList.children).map(function(item) {
            return item.cloneNode(true);
        });

        function showCommodityDestinations(row) {
            if (row.getAttribute('aria-pressed') === 'true') {
                destinationPanelTitle.textContent = defaultDestinationTitle;
                destinationList.replaceChildren(...defaultDestinationRows.map(function(item) {
                    return item.cloneNode(true);
                }));
                row.classList.remove('is-selected');
                row.setAttribute('aria-pressed', 'false');
                return;
            }

            const selectionKey = row.dataset.selectionKey;
            const locations = locationsByCommodity[selectionKey] || [];
            destinationPanelTitle.textContent = destinationLabel + ' — ' + row.querySelector('.tbr-label').textContent;
            destinationList.replaceChildren();
            if (locations.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'dest-row';
                empty.textContent = 'Tidak ada data daerah/negara untuk komoditas ini.';
                destinationList.appendChild(empty);
            }
            locations.forEach(function(location, index) {
                const item = document.createElement('div');
                item.className = 'dest-row';
                item.title = location.name;
                if (location.flag) {
                    const flag = document.createElement('img');
                    flag.className = 'dest-flag';
                    flag.src = 'https://flagcdn.com/w40/' + encodeURIComponent(location.flag) + '.png';
                    flag.alt = 'Bendera ' + location.name;
                    item.appendChild(flag);
                } else {
                    const dot = document.createElement('span');
                    dot.className = 'dest-dot';
                    dot.style.backgroundColor = summaryColors[index % summaryColors.length] || '#0f766e';
                    item.appendChild(dot);
                }
                const name = document.createElement('span');
                name.className = 'dest-name';
                name.textContent = location.name.toLocaleUpperCase('id-ID');
                item.appendChild(name);
                const count = document.createElement('span');
                count.className = 'dest-count';
                count.textContent = Number(location.count).toLocaleString('id-ID') + ' data';
                count.setAttribute(
                    'aria-label',
                    'Muncul pada ' + Number(location.count).toLocaleString('id-ID') + ' baris data'
                );
                item.appendChild(count);
                destinationList.appendChild(item);
            });

            document.querySelectorAll('.top-bar-row[data-selection-key]').forEach(function(candidate) {
                candidate.classList.toggle('is-selected', candidate === row);
                candidate.setAttribute('aria-pressed', candidate === row ? 'true' : 'false');
            });
        }

        document.querySelectorAll('.top-bar-row[data-selection-key]').forEach(function(row) {
            row.addEventListener('click', function() {
                showCommodityDestinations(row);
            });
            row.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    showCommodityDestinations(row);
                }
            });
        });

        // 1. Donut Chart
        new Chart(document.getElementById('donutChartMaster'), {
            type: 'doughnut',
            data: {
                labels: summaryLabels,
                datasets: [{
                    data: summaryValues,
                    backgroundColor: initialChartPalette
                        ? summaryValues.map((_, index) => initialChartPalette[index % initialChartPalette.length])
                        : summaryColors,
                    borderWidth: 2,
                    borderColor: initialChartPalette ? summaryValues.map(() => '#000000') : '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                            return summaryUsesFrequency
                                ? ' ' + ctx.label + ': ' + Number(ctx.raw || 0).toLocaleString('id-ID') + ' kali'
                                : ' ' + ctx.label + ': Rp ' + Number(ctx.raw || 0).toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // 2. Bar Chart
        const barLabels = summaryLabels.map(l => l.length > 15 ? l.substring(0, 15) + '...' : l);
        new Chart(document.getElementById('barChartMaster'), {
            type: 'bar',
            data: {
                labels: barLabels,
                datasets: [{
                    label: summaryUsesFrequency ? 'Frekuensi' : 'Nilai',
                    data: summaryUsesFrequency ? summaryValues : summaryValues.map(v => v / 1e9),
                    backgroundColor: initialChartPalette
                        ? summaryValues.map((_, index) => initialChartPalette[index % initialChartPalette.length])
                        : '#1d4ed8',
                    borderColor: initialChartPalette ? summaryValues.map(() => '#000000') : undefined,
                    borderWidth: initialChartPalette ? 1 : 0,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return summaryUsesFrequency
                                    ? ' Frekuensi: ' + Number(ctx.raw).toLocaleString('id-ID') + ' kali'
                                    : ' Nilai: Rp ' + Number(ctx.raw).toFixed(2) + ' Miliar';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: {
                                size: 9
                            },
                            callback: function(v) {
                                return summaryUsesFrequency
                                    ? Number(v).toLocaleString('id-ID')
                                    : v + ' M';
                            }
                        },
                        grid: { color: '#f1f5f9' }
                    },
                    y: {
                        ticks: { font: { size: 9, weight: 'bold' } },
                        grid: { display: false }
                    }
                }
            }
        });
        </script>
    <?php endif; ?>

</div>

<footer class="footer-komoditas">
    <div class="footer-container">

        <!-- Kolom 1: Identitas -->
        <div class="footer-brand">
            <div class="brand-header">
                <div class="brand-logo">
                    <img src="embed/logo-barantin.png" alt="Logo Karantina">
                </div>

                <div>
                    <h2>Informasi Komoditas</h2>
                    <span>Balai Karantina Kalimantan Selatan</span>
                </div>
            </div>

            <p>
                Penyajian data dan informasi komoditas ekspor, impor,
                dan lalu lintas karantina secara akurat, transparan,
                dan mudah diakses.
            </p>
        </div>


        <!-- Kolom 2: Kontak -->
        <div class="footer-contact">
            <h3>KONTAK</h3>

            <div class="footer-line"></div>

            <a href="mailto:karantinakalsel@karantinaindonesia.go.id"
               class="contact-item">

                <div class="contact-icon">
                    <i class="fa-regular fa-envelope"></i>
                </div>

                <span>
                    karantinakalsel@karantinaindonesia.go.id
                </span>
            </a>

            <a href="https://wa.me/628115100909"
               target="_blank"
               class="contact-item">

                <div class="contact-icon">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>

                <span>+62 811-5100-909</span>
            </a>
        </div>


        <!-- Kolom 3: Social Media -->
        <div class="footer-social">
            <h3>IKUTI KAMI</h3>

            <div class="footer-line"></div>

            <div class="social-list">

                <a href="https://www.instagram.com/karantinakalimantanselatan/" class="social-btn" aria-label="Instagram">
                    <i class="fa-brands fa-instagram"></i>
                </a>

                <a href="https://www.facebook.com/karantinakalimantanselatan/" class="social-btn" aria-label="Facebook">
                    <i class="fa-brands fa-facebook-f"></i>
                </a>

                <a href="https://x.com/karantinakalsel" class="social-btn" aria-label="X">
                    <i class="fa-brands fa-x-twitter"></i>
                </a>

                <a href="https://www.tiktok.com/@karantinakalsel" class="social-btn" aria-label="TikTok">
                    <i class="fa-brands fa-tiktok"></i>
                </a>

                <a href="https://www.youtube.com/@karantinakalimantanselatan" class="social-btn" aria-label="YouTube">
                    <i class="fa-brands fa-youtube"></i>
                </a>

            </div>
        </div>

    </div>


    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="footer-bottom-container">

            <p>
                © 2026 Balai Karantina Hewan Ikan dan Tumbuhan
                Kalimantan Selatan. Semua hak dilindungi.
            </p>

            <p>
                Created By
                <a href="https://www.instagram.com/mhmmdrroyani/" style="text-decoration: none; color: #ffffff;">@mhmmdrroyani</a> &
                <a href="https://www.instagram.com/elionagths/" style="text-decoration: none; color: #ffffff;">Novia Aulianty</a> 
            </p>

        </div>
    </div>

</footer>

<div class="accessibility-widget" id="accessibilityWidget">
    <button
        class="accessibility-trigger"
        id="accessibilityTrigger"
        type="button"
        aria-label="Buka pengaturan aksesibilitas"
        aria-controls="accessibilityPanel"
        aria-expanded="false"
    >
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="4" r="1"></circle>
            <path d="M4 7h16M12 7v5m0 0-4 8m4-8 5 5m-8-5h7"></path>
        </svg>
    </button>

    <section class="accessibility-panel" id="accessibilityPanel" aria-labelledby="accessibilityTitle" hidden>
        <header class="accessibility-panel-header">
            <div class="accessibility-panel-heading">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="4" r="1"></circle>
                    <path d="M4 7h16M12 7v5m0 0-4 8m4-8 5 5m-8-5h7"></path>
                </svg>
                <h2 id="accessibilityTitle">Aksesibilitas</h2>
            </div>
            <button class="accessibility-close" id="accessibilityClose" type="button" aria-label="Tutup pengaturan aksesibilitas">
                <span aria-hidden="true">&times;</span>
            </button>
        </header>

        <div class="accessibility-panel-content">
            <section class="accessibility-group" aria-labelledby="accessibilityTextLabel">
                <div class="accessibility-group-heading">
                    <h3 id="accessibilityTextLabel">Ukuran Teks</h3>
                    <output id="accessibilityTextValue" aria-live="polite">100%</output>
                </div>
                <div class="accessibility-button-row">
                    <button class="accessibility-option" id="accessibilityTextDown" type="button" aria-label="Kecilkan teks">
                        <span class="accessibility-option-icon" aria-hidden="true">A−</span>
                        <span>Kecilkan</span>
                    </button>
                    <button class="accessibility-option" id="accessibilityTextUp" type="button" aria-label="Besarkan teks">
                        <span class="accessibility-option-icon" aria-hidden="true">A+</span>
                        <span>Besarkan</span>
                    </button>
                </div>
            </section>

            <section class="accessibility-group" aria-labelledby="accessibilityContrastLabel">
                <h3 class="accessibility-section-title" id="accessibilityContrastLabel">Kontras</h3>
                <div class="accessibility-button-row accessibility-button-row--three">
                    <button class="accessibility-option" type="button" data-contrast="normal" aria-pressed="true">
                        <span class="accessibility-option-icon" aria-hidden="true">☼</span>
                        <span>Normal</span>
                    </button>
                    <button class="accessibility-option" type="button" data-contrast="high" aria-pressed="false">
                        <span class="accessibility-option-icon" aria-hidden="true">◐</span>
                        <span>Tinggi</span>
                    </button>
                    <button class="accessibility-option" type="button" data-contrast="gray" aria-pressed="false">
                        <span class="accessibility-option-icon" aria-hidden="true">☾</span>
                        <span>Abu-abu</span>
                    </button>
                </div>
            </section>

            <section class="accessibility-group" aria-labelledby="accessibilityColorBlindLabel">
                <h3 class="accessibility-section-title" id="accessibilityColorBlindLabel">Warna</h3>
                <button class="accessibility-option accessibility-option--wide" id="accessibilityColorBlind" type="button" aria-pressed="false">
                    <span class="accessibility-option-icon accessibility-colorblind-swatches" aria-hidden="true">
                        <span></span><span></span><span></span><span></span>
                    </span>
                    <span>Ramah Buta Warna</span>
                </button>
            </section>

            <div class="accessibility-settings-grid">
                <section class="accessibility-group" aria-labelledby="accessibilitySpacingLabel">
                    <h3 class="accessibility-section-title accessibility-section-title--small" id="accessibilitySpacingLabel">Jarak Baris</h3>
                    <button class="accessibility-option accessibility-option--large" id="accessibilitySpacing" type="button" aria-pressed="false">
                        <span class="accessibility-option-icon" aria-hidden="true">A̲</span>
                        <span>Lebar</span>
                    </button>
                </section>
                <section class="accessibility-group" aria-labelledby="accessibilityFontLabel">
                    <h3 class="accessibility-section-title accessibility-section-title--small" id="accessibilityFontLabel">Jenis Huruf</h3>
                    <button class="accessibility-option accessibility-option--large" id="accessibilityFont" type="button" aria-pressed="false">
                        <span class="accessibility-option-icon" aria-hidden="true">Aa</span>
                        <span>Mudah Baca</span>
                    </button>
                </section>
            </div>
        </div>

        <footer class="accessibility-panel-footer">
            <button class="accessibility-reset" id="accessibilityReset" type="button">
                <span aria-hidden="true">↶</span>
                Reset Semua
            </button>
        </footer>
    </section>
</div>

<script>
(() => {
    const widget = document.getElementById('accessibilityWidget');
    const trigger = document.getElementById('accessibilityTrigger');
    const panel = document.getElementById('accessibilityPanel');
    const closeButton = document.getElementById('accessibilityClose');
    const textValue = document.getElementById('accessibilityTextValue');
    const storageKey = 'boardAccessibilityPreferences';
    const defaults = {
        textScale: 100,
        contrast: 'normal',
        colorBlind: false,
        wideSpacing: false,
        readableFont: false
    };
    const allowedScales = [80, 90, 100, 110, 120, 130, 140, 150];
    const colorBlindPalette = ['#0072B2', '#E69F00', '#009E73', '#CC79A7', '#56B4E9', '#D55E00', '#F0E442'];
    let preferences = { ...defaults };

    try {
        const savedPreferences = JSON.parse(localStorage.getItem(storageKey) || '{}');
        if (savedPreferences && typeof savedPreferences === 'object') {
            preferences = {
                textScale: allowedScales.includes(savedPreferences.textScale) ? savedPreferences.textScale : defaults.textScale,
                contrast: ['normal', 'high', 'gray'].includes(savedPreferences.contrast) ? savedPreferences.contrast : defaults.contrast,
                colorBlind: savedPreferences.colorBlind === true,
                wideSpacing: savedPreferences.wideSpacing === true,
                readableFont: savedPreferences.readableFont === true
            };
        }
    } catch (error) {
        console.warn('Preferensi aksesibilitas tidak dapat dibaca dari penyimpanan browser.', error);
    }

    function savePreferences() {
        try {
            localStorage.setItem(storageKey, JSON.stringify(preferences));
        } catch (error) {
            console.warn('Preferensi aksesibilitas tidak dapat disimpan di browser ini.', error);
        }
    }

    function scaleText(root) {
        const selector = 'h1,h2,h3,h4,h5,h6,p,span,a,button,label,select,option,li,td,th,strong,small,div';
        const elements = [];
        if (root instanceof Element && root.matches(selector)) {
            elements.push(root);
        }
        if (root.querySelectorAll) {
            elements.push(...root.querySelectorAll(selector));
        }

        elements.forEach((element) => {
            if (!Array.from(element.childNodes).some((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '')) {
                return;
            }
            if (!element.hasAttribute('data-a11y-font-size')) {
                element.dataset.a11yFontSize = getComputedStyle(element).fontSize;
                element.dataset.a11yInlineFontSize = element.style.fontSize;
            }
            const originalSize = Number.parseFloat(element.dataset.a11yFontSize);
            if (preferences.textScale === defaults.textScale || !Number.isFinite(originalSize)) {
                element.style.fontSize = element.dataset.a11yInlineFontSize;
            } else {
                element.style.fontSize = `${originalSize * preferences.textScale / 100}px`;
            }
        });
    }

    function applyPreferences() {
        document.body.classList.toggle('accessibility-high-contrast', preferences.contrast === 'high');
        document.body.classList.toggle('accessibility-grayscale', preferences.contrast === 'gray');
        document.body.classList.toggle('accessibility-colorblind', preferences.colorBlind);
        document.body.classList.toggle('accessibility-wide-spacing', preferences.wideSpacing);
        document.body.classList.toggle('accessibility-readable-font', preferences.readableFont);
        scaleText(document.querySelector('.board-topbar'));
        scaleText(document.querySelector('.board-container'));
        scaleText(document.querySelector('.footer-komoditas'));
        textValue.textContent = `${preferences.textScale}%`;
        document.querySelectorAll('[data-contrast]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.contrast === preferences.contrast));
        });
        document.getElementById('accessibilityColorBlind').setAttribute('aria-pressed', String(preferences.colorBlind));
        document.getElementById('accessibilitySpacing').setAttribute('aria-pressed', String(preferences.wideSpacing));
        document.getElementById('accessibilityFont').setAttribute('aria-pressed', String(preferences.readableFont));
        applyColorBlindPalette();
        savePreferences();
    }

    function applyColorBlindPalette() {
        document.querySelectorAll('canvas').forEach((canvas) => {
            const chart = window.Chart && typeof window.Chart.getChart === 'function'
                ? window.Chart.getChart(canvas)
                : null;
            if (!chart) {
                return;
            }

            const highContrast = preferences.contrast === 'high';
            const accessibilityEnabled = preferences.colorBlind || highContrast;
            const palette = highContrast
                ? ['#000000', '#404040', '#737373', '#a6a6a6', '#d9d9d9']
                : colorBlindPalette;

            chart.data.datasets.forEach((dataset, index) => {
                if (!dataset._accessibilityColors) {
                    dataset._accessibilityColors = {
                        backgroundColor: canvas.id === 'donutChartMaster'
                            ? summaryColors
                            : (canvas.id === 'barChartMaster' ? '#1d4ed8' : dataset.backgroundColor),
                        borderColor: canvas.id === 'donutChartMaster'
                            ? '#ffffff'
                            : (canvas.id === 'barChartMaster' ? undefined : dataset.borderColor),
                        borderWidth: canvas.id === 'donutChartMaster'
                            ? 2
                            : (canvas.id === 'barChartMaster' ? 0 : dataset.borderWidth)
                    };
                }
                const color = palette[index % palette.length];
                if (chart.config.type === 'line') {
                    dataset.borderColor = accessibilityEnabled ? color : dataset._accessibilityColors.borderColor;
                    dataset.backgroundColor = accessibilityEnabled ? color : dataset._accessibilityColors.backgroundColor;
                    return;
                }

                if (accessibilityEnabled) {
                    if (['bar', 'doughnut', 'pie', 'polarArea'].includes(chart.config.type)) {
                        dataset.backgroundColor = dataset.data.map((_, colorIndex) => palette[colorIndex % palette.length]);
                    } else {
                        dataset.backgroundColor = color;
                    }
                    if (['doughnut', 'pie', 'polarArea'].includes(chart.config.type)) {
                        dataset.borderColor = dataset.data.map(() => '#000000');
                        dataset.borderWidth = 2;
                    } else if (chart.config.type === 'bar') {
                        dataset.borderColor = dataset.data.map(() => '#000000');
                        dataset.borderWidth = 1;
                    }
                } else {
                    dataset.backgroundColor = dataset._accessibilityColors.backgroundColor;
                    dataset.borderColor = dataset._accessibilityColors.borderColor;
                    dataset.borderWidth = dataset._accessibilityColors.borderWidth;
                }
            });
            chart.update();
        });

        document.querySelectorAll('.legend-dot-canva').forEach((dot, index) => {
            if (!dot.hasAttribute('data-a11y-original-color')) {
                dot.dataset.a11yOriginalColor = dot.style.backgroundColor;
            }
            dot.style.backgroundColor = preferences.colorBlind
                ? colorBlindPalette[index % colorBlindPalette.length]
                : dot.dataset.a11yOriginalColor;
        });

        applyDestinationColors();
    }

    function applyDestinationColors(root = document) {
        const dots = [];
        if (root instanceof Element && root.matches('.dest-dot')) {
            dots.push(root);
        }
        if (root.querySelectorAll) {
            dots.push(...root.querySelectorAll('.dest-dot'));
        }
        dots.forEach((dot) => {
            if (!dot.hasAttribute('data-a11y-original-color')) {
                dot.dataset.a11yOriginalColor = dot.style.backgroundColor;
            }
            dot.style.backgroundColor = preferences.colorBlind
                ? colorBlindPalette[Array.from(document.querySelectorAll('.dest-dot')).indexOf(dot) % colorBlindPalette.length]
                : dot.dataset.a11yOriginalColor;
        });
    }

    function setPanelOpen(isOpen) {
        panel.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));
        if (isOpen) {
            closeButton.focus();
        } else {
            trigger.focus();
        }
    }

    trigger.addEventListener('click', () => setPanelOpen(panel.hidden));
    closeButton.addEventListener('click', () => setPanelOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setPanelOpen(false);
        }
    });

    document.getElementById('accessibilityTextDown').addEventListener('click', () => {
        const currentIndex = allowedScales.indexOf(preferences.textScale);
        preferences.textScale = allowedScales[Math.max(0, currentIndex - 1)];
        applyPreferences();
    });
    document.getElementById('accessibilityTextUp').addEventListener('click', () => {
        const currentIndex = allowedScales.indexOf(preferences.textScale);
        preferences.textScale = allowedScales[Math.min(allowedScales.length - 1, currentIndex + 1)];
        applyPreferences();
    });
    document.querySelectorAll('[data-contrast]').forEach((button) => {
        button.addEventListener('click', () => {
            preferences.contrast = button.dataset.contrast;
            applyPreferences();
        });
    });
    document.getElementById('accessibilitySpacing').addEventListener('click', () => {
        preferences.wideSpacing = !preferences.wideSpacing;
        applyPreferences();
    });
    document.getElementById('accessibilityColorBlind').addEventListener('click', () => {
        preferences.colorBlind = !preferences.colorBlind;
        applyPreferences();
    });
    document.getElementById('accessibilityFont').addEventListener('click', () => {
        preferences.readableFont = !preferences.readableFont;
        applyPreferences();
    });
    document.getElementById('accessibilityReset').addEventListener('click', () => {
        preferences = { ...defaults };
        applyPreferences();
    });

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof Element && !widget.contains(node)) {
                    scaleText(node);
                    if (preferences.colorBlind) {
                        applyDestinationColors(node);
                    }
                }
            });
        });
    }).observe(document.querySelector('.board-container'), { childList: true, subtree: true });

    applyPreferences();
})();

function prepareBoardForPrint() {
    if (!window.Chart || typeof window.Chart.getChart !== 'function') {
        return;
    }

    document.querySelectorAll('canvas').forEach(function(canvas) {
        const chart = window.Chart.getChart(canvas);
        if (chart) {
            chart.stop();
            chart.resize();
            chart.update('none');
        }
    });
}

window.addEventListener('beforeprint', prepareBoardForPrint);

function openPrintBoard() {
    prepareBoardForPrint();
    window.print();
}
</script>

</body>
</html>
