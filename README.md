# Sistem Informasi Komoditas Karantina

Aplikasi PHP untuk mengelola data lalu lintas komoditas karantina Kalimantan Selatan, memublikasikannya ke board publik, dan mengimpor data dari Excel.

## Fitur

- Board publik untuk lalu lintas domestik masuk/keluar, ekspor, dan impor.
- Ringkasan, grafik komoditas, filter periode bulanan, dan Mode Grafik tren bulanan antar tahun.
- Pencetakan Board Publik siap A4 landscape, pencarian langsung komoditas, serta halaman grafik khusus per komoditas dengan kartu ringkasan bergaya KPI Board Publik untuk nilai, volume, frekuensi, dan daerah/negara tujuan per tahun; grafik dapat diunduh sebagai PNG.
- Filter periode menggunakan pemilih bulan dan tahun pada Board Publik dan Kelola Data.
- Panel admin untuk mengimpor data Excel kategori hewan, ikan, dan tumbuhan.
- Penanda periode Nihil untuk bulan tanpa data dan log aktivitas admin.
- Kelola data untuk meninjau, mengubah, menerbitkan, menghapus, menambah, dan mengekspor data.
- Pengelolaan akun admin dan peran pengguna.

## Persyaratan

- PHP 8.2 atau lebih baru, 64-bit, dengan ekstensi PDO MySQL.
- MySQL atau MariaDB.
- Composer.
- Ekstensi PhpSpreadsheet: `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `libxml`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, dan `zlib`.

## Menjalankan secara lokal

1. Pasang dependensi:

   ```powershell
   composer install
   ```

2. Siapkan database MySQL menggunakan `database/migrate.php` dari CLI. Atur `SILAKOM_DB_HOST`, `SILAKOM_DB_NAME`, `SILAKOM_DB_USER`, dan `SILAKOM_DB_PASSWORD` untuk koneksi; bila database belum memiliki akun admin, atur juga `SILAKOM_INITIAL_ADMIN_PASSWORD` dengan minimal 16 karakter. Jangan memakai kredensial atau password default.
3. Buat `config/database.local.php` secara lokal. File ini tidak boleh di-commit dan harus mengembalikan array string dengan kunci `host`, `name`, `user`, dan `password`.
4. Jalankan aplikasi melalui Apache/PHP lokal, misalnya Laragon, lalu buka `index.php`.
5. Masuk ke halaman admin melalui tombol **Login** pada board publik.

Skrip di `database/` hanya boleh dijalankan melalui CLI. Jangan gunakan skrip reset pada database produksi. Petunjuk penanganan kredensial dan deploy aman ada di [docs/security-operations.md](docs/security-operations.md).

Untuk database yang sudah ada, buat cadangan database lalu jalankan `php database\upgrade_schema.php` melalui PHP CLI sebelum menggunakan fitur Nihil dan Log Aktivitas. Migrasi ini menambahkan tabel yang dibutuhkan tanpa menghapus data lama.

## Alur penggunaan

1. Masuk sebagai admin.
2. Unggah file Excel pada kategori yang sesuai, atau tambahkan data satu per satu.
3. Tinjau dan kelola data pada **Kelola & Publish Data**. Pastikan jenis lalu lintas, kategori, periode, nilai, volume, dan lokasi benar.
4. Publikasikan data yang sudah diverifikasi.
5. Periksa hasilnya pada board publik. Buka **Mode Grafik** untuk membandingkan tren bulanan antar tahun.

## Pengujian

Tes helper aplikasi tidak memerlukan koneksi database:

```powershell
php tests\run.php
```

Validasi sintaks seluruh file PHP aplikasi (melewati direktori dependensi):

```powershell
Get-ChildItem -Recurse -Filter *.php |
    Where-Object { $_.FullName -notmatch '\\vendor\\' } |
    ForEach-Object { php -l $_.FullName }
```

Pemeriksaan ekstensi yang dibutuhkan dependensi:

```powershell
composer check-platform-reqs
```

Daftar pemeriksaan alur browser dan cakupan tes dijelaskan di [docs/testing-checklist.md](docs/testing-checklist.md). Panduan Mode Grafik tersedia di [docs/mode-grafik.md](docs/mode-grafik.md).

## Deploy

Push ke branch `master` menjalankan tes helper dan pemeriksaan sintaks PHP. Job deploy dinonaktifkan sampai host mengonfirmasi dukungan FTPS; setelah dikonfirmasi, aktifkan GitHub Actions repository variable `FTPS_DEPLOY_ENABLED=true` dan simpan kredensial deploy hanya sebagai Actions secrets. Workflow memakai FTPS, bukan FTP plaintext. Alur browser dan database tetap perlu diuji manual di lingkungan pengujian sebelum rilis.
