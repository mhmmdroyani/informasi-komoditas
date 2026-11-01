# Pengujian Sistem

## Pemeriksaan otomatis lokal

Jalankan dari root repository:

```powershell
php tests\run.php
```

Tes helper mencakup pengelompokan komoditas, pengecualian Sarang Burung Walet, konsolidasi satuan volume, deteksi header dan penggabungan data Excel, serta agregasi grafik tahunan. Tes ini tidak menulis ke database.

Periksa sintaks seluruh file PHP aplikasi (tidak termasuk dependensi `vendor`):

```powershell
Get-ChildItem -Recurse -Filter *.php |
    Where-Object { $_.FullName -notmatch '\\vendor\\' } |
    ForEach-Object { php -l $_.FullName }
```

Validasi manifest dependensi:

```powershell
composer validate --no-check-publish
```

Peringatan Composer tentang metadata lisensi tidak menghalangi validasi dependensi.

## Checklist uji fungsional browser

Jalankan di database pengujian atau salinan data yang sudah dicadangkan. Hindari melakukan tes tulis/hapus pada data produksi.

### Board publik

- Buka board tanpa filter dan periksa tampilan untuk kategori hewan, ikan, dan tumbuhan.
- Ganti jenis lalu lintas: domestik masuk, domestik keluar, ekspor, impor.
- Ubah periode A/B dan pastikan daftar, ringkasan, grafik, serta label periode mengikuti pilihan.
- Ubah urutan, jumlah Top, dan kategori; pastikan filter tidak kehilangan pilihan penting.
- Periksa agregasi nama komoditas awalan Burung, Ular, Vaksin, dan Ayam; Sarang Burung Walet harus tetap terpisah.
- Periksa keadaan tanpa data, tombol cetak, layout layar kecil, serta hasil cetak.

### Mode Grafik

- Pilih rentang tahun dan pastikan setiap seri menunjukkan Januari–Desember.
- Pastikan legenda dan warna membedakan tahun.
- Pastikan tahun tanpa data di dalam rentang memiliki seri nol.
- Ganti jenis lalu lintas dan kategori; pilihan tahun harus mengikuti periode publikasi yang tersedia.
- Kembali ke ringkasan board dan pastikan filter bulanan tetap berfungsi.

### Admin, keamanan alur, dan data

- Pastikan halaman admin mengarahkan pengguna yang belum masuk ke halaman login.
- Uji pembatasan menu/aksi admin sesuai peran pengguna.
- Uji login/logout, token CSRF pada form, pesan sukses, dan penanganan input tidak valid.
- Uji tambah, edit, publikasi, pembatalan publikasi, dan penghapusan pada data uji.
- Pastikan perubahan periode, klasifikasi, nama lokal ikan, frekuensi, volume/satuan, nilai, dan lokasi tersimpan dan tampil konsisten.
- Coba impor file Excel valid, header tidak lengkap, file kosong, dan format yang tidak didukung.
- Uji mode tambah/append dan replace hanya pada periode di database pengujian; pastikan nilai agregat, lokasi unik, serta status publikasi sesuai harapan.
- Periksa ekspor Excel/CSV dan hasil cetak Kelola Data.
- Unduh PDF Kelola Data untuk satu periode dan rentang periode; pastikan judul, periode, semua kolom data, dan seluruh hasil sesuai filter.
- Pastikan Download Excel berisi seluruh hasil sesuai filter periode yang dipilih.
- Coba proses pada koneksi database yang gagal di lingkungan uji dan pastikan error tidak disajikan sebagai keberhasilan.

## Batas cakupan saat ini

Tes otomatis yang tersedia menguji helper PHP tanpa database. Pengujian alur halaman, otorisasi, operasi database, upload file lewat HTTP, dan tampilan lintas browser masih memerlukan pengujian manual/integrasi di lingkungan terpisah.
