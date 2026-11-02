<?php
// config/koneksi.php
$databaseConfigPath = __DIR__ . '/database.local.php';
if (!is_file($databaseConfigPath)) {
    throw new RuntimeException('Konfigurasi database lokal tidak ditemukan.');
}

$databaseConfig = require $databaseConfigPath;
foreach (['host', 'name', 'user', 'password'] as $key) {
    if (!is_array($databaseConfig) || !array_key_exists($key, $databaseConfig) || !is_string($databaseConfig[$key])) {
        throw new RuntimeException('Konfigurasi database lokal tidak lengkap.');
    }
}

function create_database_connection(array $databaseConfig): PDO
{
    return new PDO(
        "mysql:host={$databaseConfig['host']};dbname={$databaseConfig['name']};charset=utf8mb4",
        $databaseConfig['user'],
        $databaseConfig['password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
}

try {
    $pdo = create_database_connection($databaseConfig);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

/**
 * Helper untuk membersihkan format angka dari Excel (Rp, titik, koma, spasi, dash)
 */
function clean_number($val) {
    if ($val === null || $val === '' || $val === '-') {
        return 0;
    }
    if (is_numeric($val)) {
        return (float)$val;
    }
    
    $str = trim((string)$val);
    // Hapus simbol mata uang dan karakter non-numerik selain . dan ,
    $str = preg_replace('/[^\d.,\-]/', '', $str);
    
    // Jika ada koma dan titik:
    // Pola Indonesia: 1.250.000,50 -> ganti titik hilang, koma jadi titik
    if (strpos($str, '.') !== false && strpos($str, ',') !== false) {
        if (strrpos($str, ',') > strrpos($str, '.')) {
            // Format Indo: 1.000,50
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } else {
            // Format US: 1,000.50
            $str = str_replace(',', '', $str);
        }
    } elseif (strpos($str, ',') !== false) {
        // Hanya ada koma, bisa koma desimal (misal 12,5) atau ribuan (1,250)
        // Jika setelah koma ada 2 digit atau 1 digit, kemungkinan desimal
        $parts = explode(',', $str);
        if (count($parts) === 2 && strlen($parts[1]) <= 2) {
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(',', '', $str);
        }
    }
    
    return is_numeric($str) ? (float)$str : 0;
}

/**
 * Format angka dalam rupiah singkat (Triliun, Miliar, Juta, Ribu)
 */
function format_rupiah_singkat($nilai) {
    $nilai = (float)$nilai;
    if ($nilai >= 1000000000000) {
        return 'Rp ' . number_format($nilai / 1000000000000, 2, ',', '.') . ' T';
    } elseif ($nilai >= 1000000000) {
        return 'Rp ' . number_format($nilai / 1000000000, 2, ',', '.') . ' M';
    } elseif ($nilai >= 1000000) {
        return 'Rp ' . number_format($nilai / 1000000, 2, ',', '.') . ' Jt';
    } else {
        return 'Rp ' . number_format($nilai, 0, ',', '.');
    }
}

/**
 * Format angka ribuan Indonesia
 */
function format_angka($nilai, $desimal = 0) {
    return number_format((float)$nilai, $desimal, ',', '.');
}

/**
 * Format nilai ekspor untuk kartu KPI sesuai desain Canva:
 * Mengembalikan ['angka' => 'Rp 8,35', 'satuan' => 'Miliar']
 */
function format_kpi_nilai($nilai) {
    $nilai = (float)$nilai;
    if ($nilai >= 1000000000000) {
        return ['angka' => 'Rp ' . number_format($nilai / 1000000000000, 2, ',', '.'), 'satuan' => 'Triliun'];
    } elseif ($nilai >= 1000000000) {
        return ['angka' => 'Rp ' . number_format($nilai / 1000000000, 2, ',', '.'), 'satuan' => 'Miliar'];
    } elseif ($nilai >= 1000000) {
        return ['angka' => 'Rp ' . number_format($nilai / 1000000, 2, ',', '.'), 'satuan' => 'Juta'];
    } else {
        return ['angka' => 'Rp ' . number_format($nilai, 0, ',', '.'), 'satuan' => 'Ribu'];
    }
}

/**
 * Hitung jumlah benua dari daftar nama negara
 */
function hitung_benua($daftarNegara) {
    $benuaPeta = [
        'Asia' => ['china', 'hong kong', 'taiwan', 'singapura', 'jepang', 'korea', 'malaysia', 'vietnam', 'thailand', 'india', 'uae', 'emirat', 'brunei', 'timor', 'filipina', 'saudi', 'arab'],
        'Amerika' => ['amerika', 'usa', 'united states', 'kanada', 'brazil', 'meksiko', 'chile'],
        'Eropa' => ['jerman', 'belanda', 'netherlands', 'eropa', 'belgia', 'inggris', 'uk', 'prancis', 'italia', 'spanyol', 'rusia'],
        'Oseania' => ['australia', 'papua', 'selandia baru', 'new zealand'],
        'Afrika' => ['mesir', 'afrika', 'nigeria', 'maroko', 'kenya']
    ];
    
    $benuaTerdeteksi = [];
    foreach ($daftarNegara as $negara) {
        $n = strtolower(trim($negara));
        $ditemukan = false;
        foreach ($benuaPeta as $benua => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($n, $kw) !== false) {
                    $benuaTerdeteksi[$benua] = true;
                    $ditemukan = true;
                    break 2;
                }
            }
        }
        if (!$ditemukan && !empty($n)) {
            $benuaTerdeteksi['Asia'] = true;
        }
    }
    return max(1, count($benuaTerdeteksi));
}

/**
 * Helper untuk label jenis kegiatan karantina
 */
function get_label_kegiatan($jenis) {
    switch ($jenis) {
        case 'impor': return 'Impor';
        case 'domestik_keluar': return 'Domestik Keluar';
        case 'domestik_masuk': return 'Domestik Masuk';
        case 'ekspor':
        default: return 'Ekspor';
    }
}

/**
 * Helper untuk label arah / asal-tujuan komoditas
 */
function get_label_arah($jenis) {
    switch ($jenis) {
        case 'impor': return 'Negara Asal';
        case 'domestik_keluar': return 'Daerah Tujuan';
        case 'domestik_masuk': return 'Daerah Asal';
        case 'ekspor':
        default: return 'Negara Tujuan';
    }
}

/**
 * Helper untuk satuan wilayah (Negara / Daerah / Provinsi)
 */
function get_label_wilayah($jenis) {
    switch ($jenis) {
        case 'domestik_keluar':
        case 'domestik_masuk':
            return 'Daerah';
        case 'ekspor':
        case 'impor':
        default:
            return 'Negara';
    }
}

/**
 * Helper untuk dokumen sertifikat karantina
 */
function get_label_sertifikat($jenis, $kategori) {
    if ($kategori === 'ikan') {
        return 'Dokumen Health Certificate';
    } elseif ($kategori === 'tumbuhan') {
        return 'Phytosanitary Certificate';
    } else {
        switch ($jenis) {
            case 'impor': return 'Dokumen Persetujuan Impor';
            case 'domestik_keluar': return 'Sertifikat Pelepasan (KH-11)';
            case 'domestik_masuk': return 'Sertifikat Pemasukan';
            case 'ekspor':
            default: return 'Dokumen Sertifikat Ekspor';
        }
    }
}

/**
 * Nama bulan Indonesia, index 1-12
 */
function nama_bulan_indo($bulan) {
    $namaBulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    return $namaBulan[(int)$bulan] ?? '';
}

/**
 * Format label periode publik berbasis bulan (contoh: "JANUARI 2026" atau "JANUARI - JUNI 2026")
 */
function format_label_tahun($tglAwal, $tglAkhir, $fallbackLabel = '') {
    // Jika rentang tanggal valid, tampilkan label berbasis bulan & tahun
    if (!empty($tglAwal) && !empty($tglAkhir)) {
        $tsAwal = strtotime($tglAwal);
        $tsAkhir = strtotime($tglAkhir);
        if ($tsAwal !== false && $tsAkhir !== false) {
            $thnAwal = (int)date('Y', $tsAwal);
            $thnAkhir = (int)date('Y', $tsAkhir);
            $blnAwal = (int)date('n', $tsAwal);
            $blnAkhir = (int)date('n', $tsAkhir);

            if ($thnAwal === $thnAkhir && $blnAwal === $blnAkhir) {
                return strtoupper(nama_bulan_indo($blnAwal) . ' ' . $thnAwal);
            } elseif ($thnAwal === $thnAkhir) {
                return strtoupper(nama_bulan_indo($blnAwal) . ' - ' . nama_bulan_indo($blnAkhir) . ' ' . $thnAwal);
            } else {
                return strtoupper(nama_bulan_indo($blnAwal) . ' ' . $thnAwal . ' - ' . nama_bulan_indo($blnAkhir) . ' ' . $thnAkhir);
            }
        }
    }

    // Fallback jika ada label teks lama yang mengandung tahun
    if (!empty($fallbackLabel)) {
        preg_match_all('/\b(20\d\d)\b/', $fallbackLabel, $matches);
        if (!empty($matches[1])) {
            $years = array_unique($matches[1]);
            sort($years);
            if (count($years) === 1) {
                return "TAHUN " . $years[0];
            } else {
                return "TAHUN " . $years[0] . " - " . end($years);
            }
        }
        return strtoupper(trim($fallbackLabel));
    }

    return "TAHUN " . date('Y');
}

function format_label_periode_kode($periode) {
    $periode = trim((string)$periode);
    if (preg_match('/^(\d{4})-(\d{2})$/', $periode, $matches)) {
        $bulan = (int)$matches[2];
        if ($bulan >= 1 && $bulan <= 12) {
            return strtoupper(nama_bulan_indo($bulan) . ' ' . $matches[1]);
        }
        return '';
    }
    if (!preg_match('/^(\d{4})-(\d{2})_(\d{4})-(\d{2})$/', $periode, $matches)) {
        return '';
    }

    $tahunAwal = (int)$matches[1];
    $bulanAwal = (int)$matches[2];
    $tahunAkhir = (int)$matches[3];
    $bulanAkhir = (int)$matches[4];

    if ($tahunAwal === $tahunAkhir) {
        return strtoupper(nama_bulan_indo($bulanAwal) . ' - ' . nama_bulan_indo($bulanAkhir) . ' ' . $tahunAwal);
    }

    return strtoupper(nama_bulan_indo($bulanAwal) . ' ' . $tahunAwal . ' - ' . nama_bulan_indo($bulanAkhir) . ' ' . $tahunAkhir);
}

/**
 * Parser kalender interaktif untuk mode Bulan (mode lain dipertahankan untuk kompatibilitas internal)
 */
function parse_calendar_filter($mode, $getParams) {
    $curYear = (int)date('Y');
    
    $result = [
        'mode' => in_array($mode, ['tahun', 'bulan', 'hari']) ? $mode : 'bulan',
        'tgl_awal' => null,
        'tgl_akhir' => null,
        'thn_awal' => $curYear,
        'thn_akhir' => $curYear,
        'bln_awal' => date('Y-01'),
        'bln_akhir' => date('Y-m'),
        'label_tahun' => "TAHUN {$curYear}"
    ];

    if ($result['mode'] === 'tahun') {
        $thnAwal = (int)($getParams['thn_awal'] ?? ($getParams['tahun_awal'] ?? $curYear));
        $thnAkhir = (int)($getParams['thn_akhir'] ?? ($getParams['tahun_akhir'] ?? $thnAwal));
        if ($thnAwal > $thnAkhir) {
            $tmp = $thnAwal;
            $thnAwal = $thnAkhir;
            $thnAkhir = $tmp;
        }
        $result['thn_awal'] = $thnAwal;
        $result['thn_akhir'] = $thnAkhir;
        $result['tgl_awal'] = "{$thnAwal}-01-01";
        $result['tgl_akhir'] = "{$thnAkhir}-12-31";
        $result['label_tahun'] = ($thnAwal === $thnAkhir) ? "TAHUN {$thnAwal}" : "TAHUN {$thnAwal} - {$thnAkhir}";
    } elseif ($result['mode'] === 'bulan') {
        $blnAwal = trim($getParams['bln_awal'] ?? date('Y-01'));
        $blnAkhir = trim($getParams['bln_akhir'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $blnAwal)) $blnAwal = date('Y-01');
        if (!preg_match('/^\d{4}-\d{2}$/', $blnAkhir)) $blnAkhir = date('Y-m');
        
        if ($blnAwal > $blnAkhir) {
            $tmp = $blnAwal;
            $blnAwal = $blnAkhir;
            $blnAkhir = $tmp;
        }
        $result['bln_awal'] = $blnAwal;
        $result['bln_akhir'] = $blnAkhir;
        $result['tgl_awal'] = "{$blnAwal}-01";
        $lastDay = date('t', strtotime("{$blnAkhir}-01"));
        $result['tgl_akhir'] = "{$blnAkhir}-{$lastDay}";
        
        $y1 = (int)substr($blnAwal, 0, 4);
        $y2 = (int)substr($blnAkhir, 0, 4);
        $m1 = (int)substr($blnAwal, 5, 2);
        $m2 = (int)substr($blnAkhir, 5, 2);
        $result['thn_awal'] = $y1;
        $result['thn_akhir'] = $y2;
        if ($y1 === $y2 && $m1 === $m2) {
            $result['label_tahun'] = strtoupper(nama_bulan_indo($m1) . " {$y1}");
        } elseif ($y1 === $y2) {
            $result['label_tahun'] = strtoupper(nama_bulan_indo($m1) . ' - ' . nama_bulan_indo($m2) . " {$y1}");
        } else {
            $result['label_tahun'] = strtoupper(nama_bulan_indo($m1) . " {$y1} - " . nama_bulan_indo($m2) . " {$y2}");
        }
    } elseif ($result['mode'] === 'hari') {
        $tglAwal = trim($getParams['tgl_awal'] ?? ($getParams['tanggal_awal'] ?? date('Y-01-01')));
        $tglAkhir = trim($getParams['tgl_akhir'] ?? ($getParams['tanggal_akhir'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAwal)) $tglAwal = date('Y-01-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAkhir)) $tglAkhir = date('Y-m-d');
        
        if ($tglAwal > $tglAkhir) {
            $tmp = $tglAwal;
            $tglAwal = $tglAkhir;
            $tglAkhir = $tmp;
        }
        $result['tgl_awal'] = $tglAwal;
        $result['tgl_akhir'] = $tglAkhir;
        $y1 = (int)date('Y', strtotime($tglAwal));
        $y2 = (int)date('Y', strtotime($tglAkhir));
        $result['thn_awal'] = $y1;
        $result['thn_akhir'] = $y2;
        $result['label_tahun'] = ($y1 === $y2) ? "TAHUN {$y1}" : "TAHUN {$y1} - {$y2}";
    }

    return $result;
}
