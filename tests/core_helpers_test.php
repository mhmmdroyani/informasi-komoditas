<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/period_aggregation.php';
require_once __DIR__ . '/../includes/raw_excel_import.php';

function expect_core_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

expect_core_same(
    'Burung',
    normalize_commodity_group_name(' burung kutilang '),
    'Commodity groups should normalize leading commodity labels.'
);
expect_core_same(
    'Sarang Burung Walet',
    normalize_commodity_group_name('Sarang Burung Walet'),
    'Sarang burung walet should remain separate.'
);
expect_core_same(
    ['volume' => 2500.0, 'unit' => 'Kilogram'],
    normalize_compatible_volume_unit(2.5, 'ton'),
    'Ton volumes should normalize to kilograms.'
);

$aggregated = aggregate_commodity_rows([
    [
        'id' => 1,
        'nama_komoditas' => 'Burung Kutilang',
        'frekuensi' => 2,
        'volume' => 1,
        'satuan' => 'ton',
        'nilai_ekspor' => 100,
        'negara_tujuan' => 'Malaysia',
        'is_published' => 1
    ],
    [
        'id' => 2,
        'nama_komoditas' => 'Burung Merpati',
        'frekuensi' => 3,
        'volume' => 500,
        'satuan' => 'kg',
        'nilai_ekspor' => 200,
        'negara_tujuan' => 'Malaysia, Vietnam',
        'is_published' => 0
    ],
    [
        'id' => 3,
        'nama_komoditas' => 'Sarang Burung Walet',
        'frekuensi' => 1,
        'volume' => 100,
        'satuan' => 'kg',
        'nilai_ekspor' => 50,
        'is_published' => 1
    ]
]);

expect_core_same(2, count($aggregated), 'Grouped commodity rows should stay separate from exceptions.');
expect_core_same('Burung', $aggregated[0]['nama_komoditas'], 'The grouped display name should be canonical.');
expect_core_same(5, $aggregated[0]['frekuensi'], 'Grouped frequencies should sum.');
expect_core_same(1500.0, $aggregated[0]['volume'], 'Compatible volume units should aggregate.');
expect_core_same(300.0, $aggregated[0]['nilai_ekspor'], 'Grouped commodity values should sum.');
expect_core_same('Malaysia, Vietnam', $aggregated[0]['negara_tujuan'], 'Locations should be unique per group.');
expect_core_same('Campuran', $aggregated[0]['publication_status'], 'Mixed publish states should be identified.');
expect_core_same('Sarang Burung Walet', $aggregated[1]['nama_komoditas'], 'Walet records should remain separate.');

$exportHeader = [
    'Klasifikasi',
    'Komoditas',
    'Vol P1',
    'Satuan Lain',
    'Harga Barang (Rp)',
    'Daerah Asal',
    'Daerah Tujuan',
    'Negara Tujuan'
];
$expectedColumns = [
    'klasifikasi' => 0,
    'komoditas' => 1,
    'volume' => 2,
    'satuan' => 3,
    'nilai' => 4,
    'daerah_asal' => 5,
    'daerah_tujuan' => 6,
    'negara_tujuan' => 7
];
expect_core_same(
    $expectedColumns,
    raw_excel_detect_columns($exportHeader, 'ekspor'),
    'The Excel importer should recognize the required export headers.'
);
expect_core_same(
    $expectedColumns,
    raw_excel_detect_columns(array_slice($exportHeader, 0, 7, true) + [7 => 'Negara Asal'], 'impor'),
    'The Excel importer should accept the origin-country header for imports.'
);
expect_core_same(null, raw_excel_detect_columns(['Komoditas', 'Volume'], 'ekspor'), 'Incomplete Excel headers should be rejected.');

$readFilter = new RawExcelImportReadFilter(8, 12, [35, 40]);
expect_core_same(true, $readFilter->readCell('AJ', 8), 'The Excel filter should include required columns at the header row.');
expect_core_same(true, $readFilter->readCell('AO', 12), 'The Excel filter should include required columns in data rows.');
expect_core_same(false, $readFilter->readCell('AK', 9), 'The Excel filter should exclude unrelated columns.');
expect_core_same(false, $readFilter->readCell('AJ', 13), 'The Excel filter should exclude rows outside its range.');

$mergedEntries = merge_raw_excel_entries(
    [[
        'klasifikasi' => '',
        'nama_komoditas' => 'Ikan Tuna',
        'frekuensi' => 1,
        'volume' => 1,
        'satuan' => 'ton',
        'nilai_ekspor' => 100,
        'daerah_asal' => 'Banjar',
        'daerah_tujuan' => 'Jakarta',
        'negara_tujuan' => ''
    ]],
    [[
        'klasifikasi' => 'Ikan',
        'nama_komoditas' => 'Ikan Tuna',
        'frekuensi' => 2,
        'volume' => 500,
        'satuan' => 'kg',
        'nilai_ekspor' => 200,
        'daerah_asal' => 'Banjar, Tanah Laut',
        'daerah_tujuan' => 'Jakarta',
        'negara_tujuan' => ''
    ]]
);
expect_core_same(1, count($mergedEntries), 'Append imports should merge matching commodity/unit rows.');
expect_core_same(3, $mergedEntries[0]['frekuensi'], 'Append imports should sum frequency.');
expect_core_same(1500.0, $mergedEntries[0]['volume'], 'Append imports should normalize compatible units.');
expect_core_same(300.0, $mergedEntries[0]['nilai_ekspor'], 'Append imports should sum value.');
expect_core_same('Banjar, Tanah Laut', $mergedEntries[0]['daerah_asal'], 'Append imports should preserve unique locations.');
expect_core_same('Ikan', $mergedEntries[0]['klasifikasi'], 'Append imports should fill a missing classification.');

echo "Core helper tests passed.\n";
