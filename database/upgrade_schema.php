<?php
// database/upgrade_schema.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

try {
    echo "Memulai migrasi database untuk 4 jenis kegiatan lalu lintas...\n";

    // 1. Periksa apakah kolom jenis_kegiatan sudah ada di ekspor_komoditas
    $cols = $pdo->query("SHOW COLUMNS FROM ekspor_komoditas LIKE 'jenis_kegiatan'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE ekspor_komoditas 
            ADD COLUMN jenis_kegiatan ENUM('ekspor', 'impor', 'domestik_keluar', 'domestik_masuk') NOT NULL DEFAULT 'ekspor' AFTER id,
            ADD INDEX idx_jenis_kegiatan (jenis_kegiatan),
            ADD INDEX idx_jenis_kategori_pub (jenis_kegiatan, kategori, is_published)");
        echo " - Kolom jenis_kegiatan berhasil ditambahkan ke ekspor_komoditas.\n";
    }

    foreach ([
        'tanggal_mulai' => "ADD COLUMN tanggal_mulai DATE NULL AFTER periode",
        'tanggal_selesai' => "ADD COLUMN tanggal_selesai DATE NULL AFTER tanggal_mulai",
    ] as $column => $definition) {
        $exists = $pdo->query("SHOW COLUMNS FROM ekspor_komoditas LIKE '{$column}'")->fetchAll();
        if (empty($exists)) {
            $pdo->exec("ALTER TABLE ekspor_komoditas {$definition}");
            echo " - Kolom {$column} berhasil ditambahkan ke ekspor_komoditas.\n";
        }
    }

    // 2. Periksa apakah kolom jenis_kegiatan sudah ada di ekspor_settings
    $colsSettings = $pdo->query("SHOW COLUMNS FROM ekspor_settings LIKE 'jenis_kegiatan'")->fetchAll();
    if (empty($colsSettings)) {
        // Hapus UNIQUE index lama pada kategori saja
        try {
            $pdo->exec("ALTER TABLE ekspor_settings DROP INDEX kategori");
        } catch (Exception $e) {
            // Abaikan jika nama index beda
        }
        $pdo->exec("ALTER TABLE ekspor_settings 
            ADD COLUMN jenis_kegiatan ENUM('ekspor', 'impor', 'domestik_keluar', 'domestik_masuk') NOT NULL DEFAULT 'ekspor' AFTER id,
            ADD UNIQUE KEY uq_jenis_kategori (jenis_kegiatan, kategori)");
        echo " - Kolom jenis_kegiatan berhasil ditambahkan ke ekspor_settings dengan unique (jenis_kegiatan, kategori).\n";
    }

    foreach ([
        'filter_mode' => "ADD COLUMN filter_mode ENUM('tahun','bulan','hari','semua') NOT NULL DEFAULT 'tahun' AFTER urutkan_berdasarkan",
        'tanggal_awal' => "ADD COLUMN tanggal_awal DATE NULL AFTER filter_mode",
        'tanggal_akhir' => "ADD COLUMN tanggal_akhir DATE NULL AFTER tanggal_awal",
        'tahun_awal' => "ADD COLUMN tahun_awal SMALLINT NULL AFTER tanggal_akhir",
        'tahun_akhir' => "ADD COLUMN tahun_akhir SMALLINT NULL AFTER tahun_awal",
    ] as $column => $definition) {
        $exists = $pdo->query("SHOW COLUMNS FROM ekspor_settings LIKE '{$column}'")->fetchAll();
        if (empty($exists)) {
            $pdo->exec("ALTER TABLE ekspor_settings {$definition}");
            echo " - Kolom {$column} berhasil ditambahkan ke ekspor_settings.\n";
        }
    }

    $periodeSettingExists = $pdo->query("SHOW COLUMNS FROM ekspor_settings LIKE 'periode'")->fetchAll();
    if (empty($periodeSettingExists)) {
        $pdo->exec("ALTER TABLE ekspor_settings ADD COLUMN periode VARCHAR(50) NULL AFTER tahun_akhir");
        echo " - Kolom periode berhasil ditambahkan ke ekspor_settings.\n";
    }

    try {
        $pdo->exec("ALTER TABLE ekspor_settings DROP INDEX uq_jenis_kategori");
    } catch (Exception $e) {
        // Index mungkin sudah pernah diubah.
    }
    try {
        $pdo->exec("ALTER TABLE ekspor_settings ADD UNIQUE KEY uq_jenis_kategori_periode (jenis_kegiatan, kategori, periode)");
    } catch (Exception $e) {
        // Index mungkin sudah tersedia.
    }

    // 3. Tambahkan role dan status aktif untuk fitur Kelola Pengguna
    foreach ([
        'role' => "ADD COLUMN role ENUM('admin', 'user') NOT NULL DEFAULT 'admin' AFTER nama",
        'is_active' => "ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role",
    ] as $column => $definition) {
        $exists = $pdo->query("SHOW COLUMNS FROM admin_users LIKE '{$column}'")->fetchAll();
        if (empty($exists)) {
            $pdo->exec("ALTER TABLE admin_users {$definition}");
            echo " - Kolom {$column} berhasil ditambahkan ke admin_users.\n";
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ekspor_nihil (
        jenis_kegiatan ENUM('ekspor','impor','domestik_keluar','domestik_masuk') NOT NULL,
        kategori ENUM('hewan','ikan','tumbuhan') NOT NULL,
        periode CHAR(7) NOT NULL,
        marked_by INT NULL,
        marked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (jenis_kegiatan, kategori, periode),
        INDEX idx_nihil_periode (periode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo " - Tabel ekspor_nihil tersedia.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        username VARCHAR(100) NOT NULL,
        action VARCHAR(64) NOT NULL,
        description TEXT NOT NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_created_at (created_at),
        INDEX idx_activity_admin_id (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo " - Tabel admin_activity_logs tersedia.\n";

    echo "MIGRASI BERHASIL!\n";
} catch (Exception $e) {
    echo "MIGRASI GAGAL: " . $e->getMessage() . "\n";
    exit(1);
}
