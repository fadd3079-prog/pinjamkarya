# PinjamKarya: Platform Web Komunitas Peminjaman Perkakas (*Library of Things*) dan Layanan Perbaikan Mandiri (*Right-to-Repair Hub*)

## 1. Nama dan Deskripsi Proyek
**PinjamKarya** adalah aplikasi web ekonomi sirkular tingkat komunitas yang mengintegrasikan dua ekosistem layanan utama dalam satu platform terpadu:
1. **Perpustakaan Perkakas Komunitas (*Library of Things*):** Memfasilitasi peminjaman perkakas pertukangan, peralatan produksi UMKM, sarana acara komunal, hingga alat bantu mobilitas kesehatan tanpa harus membeli unit baru.
2. **Pusat Perbaikan Mandiri (*Right-to-Repair Hub*):** Mempertemukan warga yang memiliki barang elektronik atau perkakas rusak dengan teknisi lokal terverifikasi melalui mekanisme diagnosis dan persetujuan estimasi biaya yang transparan.

### Identitas Tim Pengembang
- **Aflah** (Pengelola Hub & Arsitektur Sistem)
- **Balqis** (Koordinator Mitra Kontributor & Layanan Right-to-Repair)
- **Fadhol** (Pengembangan Pengalaman Warga & Analitik Dampak SDGs)

---

## 2. Latar Belakang Masalah dan Tujuan Proyek (Keterkaitan SDGs)
Banyak keluarga dan pelaku UMKM membutuhkan perkakas kerja atau peralatan produksi hanya untuk beberapa hari dalam setahun. Membeli perkakas baru untuk pemakaian sesaat membebani pengeluaran rumah tangga dan meningkatkan penumpukan barang menganggur. Di sisi lain, ketika peralatan elektronik rumah tangga mengalami kerusakan ringan, warga sering membuangnya karena ketiadaan akses ke teknisi lokal yang transparan soal biaya perbaikan.

**PinjamKarya** hadir untuk menjawab permasalahan tersebut dengan mendukung langsung dua Tujuan Pembangunan Berkelanjutan (*Sustainable Development Goals*):
- **SDG 12 (Konsumsi dan Produksi yang Bertanggung Jawab):** Menekan timbulan sampah elektronik (*e-waste*) melalui layanan *Right-to-Repair* serta mengoptimalkan usia pakai perkakas melalui pemakaian bersama secara bergilir.
- **SDG 10 (Berkurangnya Kesenjangan):** Membuka akses setara terhadap alat kerja produktif dan alat bantu kesehatan bagi seluruh warga serta pelaku UMKM mikro berbasis **Skor Kepercayaan Komunitas**, bukan semata kemampuan modal membeli alat baru.

---

## 3. Struktur Direktori dan Fitur Sistem

Seluruh halaman aplikasi dikelompokkan berdasarkan domain fitur (100% PHP Native prosedural modular tanpa framework):

- **Root (`index.php`, `dashboard.php`):**
  - `index.php`: Halaman beranda publik, ringkasan katalog, alur peminjaman/servis, serta etalase dampak sirkularitas Hub.
  - `dashboard.php`: Pengendali utama (*controller*) halaman Dashboard multi-peran yang menangani autentikasi sesi, pembersihan *Ghost Booking*, inisialisasi data bersama, serta pemuatan komponen parsial tampilan Bento Grid sesuai peran aktif (`includes/dashboard_admin.php`, `includes/dashboard_mitra.php`, dan `includes/dashboard_user.php`).
- **`auth/` (Autentikasi, Profil & Google OAuth):**
  - `login.php`, `register.php`, `logout.php`, `google_login.php`, `google_callback.php`, `profil.php`.
  - Mengelola registrasi warga/mitra, login email/Google, sesi pengguna, pembaruan profil, dan visualisasi Skor Kepercayaan Komunitas.
- **`katalog/` (Katalog Publik & Jadwal Ketersediaan):**
  - `index.php`: Katalog perkakas publik dengan pembersihan otomatis *Ghost Booking* dan filter tanggal kosong bebas bentrok.
  - `detail_alat.php`: Spesifikasi perkakas, panduan keselamatan kerja kategori, dan kalender ketersediaan.
- **`reservasi/` (Peminjaman Warga):**
  - `reservasi.php`: Pengajuan reservasi dengan gerbang Skor Kepercayaan, persetujuan SOP keselamatan, dan transaksi SQL atomik anti-*double booking*.
  - `riwayat_pinjam.php`: Riwayat transaksi peminjaman dan kode booking warga.
- **`perbaikan/` (Layanan Perbaikan Mandiri / *Right-to-Repair*):**
  - `buat_tiket.php`: Pelaporan kerusakan barang elektronik/perkakas lengkap dengan foto kondisi dan pilihan metode pengecekan.
  - `tiket_saya.php`: Pelacakan progres servis 6 tahap serta persetujuan/penolakan estimasi biaya oleh warga.
- **`mitra/` (Ruang Mitra Kontributor & Teknisi):**
  - `aset_mitra.php`: Formulir penitipan dan pemantauan status aset titipan Mitra Kontributor.
  - `antrean_teknisi.php`: Meja klaim tiket kerusakan, input diagnosis, estimasi biaya, dan pembaruan progres teknisi terverifikasi.
- **`admin/` (Panel Administrasi Hub):**
  - `kelola_user.php`: Manajemen pengguna dan verifikasi akun teknisi baru.
  - `kelola_kategori.php`: CRUD master kategori alat, panduan keselamatan, bobot limbah (`rata_rata_berat_kg`), dan estimasi harga beli baru.
  - `kelola_aset.php`: Manajemen inventaris perkakas Hub dan aktivasi alat titipan mitra.
  - `kelola_transaksi.php`: Pemantauan transaksi peminjaman dan status pengembalian.
  - `inspeksi.php` & `riwayat_inspeksi.php`: Inspeksi kondisi Awal dan Akhir (penghitungan otomatis Skor Kepercayaan serta penerbitan tiket servis otomatis jika rusak) beserta log auditnya.
  - `kelola_tiket.php`: Pengawasan tiket servis dan pemulihan otomatis status alat inventaris Hub setelah selesai diservis.
- **`config/`, `includes/`, `assets/`, `database/`:**
  - `config/`: Konfigurasi terpusat (`app.php`, `database.php`, `google.php`, serta templat `config.example.php` / `google.example.php`).
  - `includes/`:
    - Kerangka layout bersama (`header.php`, `sidebar.php`, `footer.php`).
    - Komponen parsial tampilan dashboard per peran (`dashboard_admin.php`, `dashboard_mitra.php`, `dashboard_user.php`).
    - Pustaka fungsi helper & komponen UI terpusat (`functions.php`: autentikasi/sesi, token CSRF, query *prepared statement* `pk_stmt_all`/`pk_stmt_one`, pemformatan, unggah gambar, generator kode unik `generate_kode_unik`, perender grafik tren SVG `pk_render_svg_trend`, serta perender tata letak Bento Grid `pk_render_dashboard_bento`).
  - `assets/`: Aset statis lokal (`css/pinjamkarya-ui.css`, `js/pinjamkarya.js`, gambar, dan direktori unggahan).
  - `database/pinjamkarya.sql`: Skema tabel relasional dan *seed data* awal.

---

## 4. Teknologi yang Digunakan (Zero Framework & Zero Library)
- **Backend:** 100% Pure PHP Native (PHP 8.x) dengan arsitektur *Modular Procedural* dan ekstensi `mysqli` (Prepared Statements & Transaksi SQL Atomik).
- **Basis Data:** MySQL / MariaDB (InnoDB Relational Schema dengan Foreign Key Constraints).
- **Frontend Styling:** 100% Pure Vanilla CSS3 Lokal (`assets/css/pinjamkarya-ui.css`) menggunakan CSS Custom Properties (`:root`), CSS Grid, dan Flexbox tanpa framework CSS eksternal dan tanpa CDN.
- **Interaktivitas:** 100% Pure Vanilla JavaScript ES6+ Lokal (`assets/js/pinjamkarya.js`) tanpa library pihak ketiga.
- **Visual & Grafik:** Pure Inline SVG Icons & Native SVG Bar Chart (`includes/functions.php`, `dashboard.php`, serta parsial `includes/dashboard_admin.php`, `includes/dashboard_mitra.php`, dan `includes/dashboard_user.php`).

---

## 5. Panduan Instalasi dan Konfigurasi Terpusat (Lokal & Hosting)
1. **Salin Direktori Proyek:**
   Tempatkan folder `pinjamkarya` di root dokumen web server lokal (misalnya `C:\xampp\htdocs\pinjamkarya` atau `/opt/lampp/htdocs/pinjamkarya`) atau di `public_html` server hosting.
2. **Nyalakan Layanan Web Server & Database:**
   Jalankan layanan **Apache** dan **MySQL/MariaDB** (XAMPP/LAMPP atau hosting Anda).
3. **Impor Basis Data & Data Awal (*Seed Data*):**
   - Buka `http://localhost/phpmyadmin` (atau panel database hosting).
   - Pilih tab **Import**, unggah file `database/pinjamkarya.sql`, lalu tekan **Import / Go**.
   - Skrip tersebut otomatis membuat database `pinjamkarya`, 6 tabel relasional, serta data awal siap uji coba.
4. **Konfigurasi Terpusat (Opsional untuk Lokal, Wajib untuk Produksi/Hosting):**
   - Salin `config/config.example.php` menjadi `config/config.local.php` (sudah diabaikan oleh `.gitignore`).
   - Sesuaikan array `database` (`host`, `port`, `user`, `pass`, `name`, `charset`), `app` (`base_url`, `timezone`, `debug`), dan `google` (`client_id`, `client_secret`, `redirect_uri`).
   - Biarkan `app.base_url` dan `google.redirect_uri` kosong (`''`) agar aplikasi mendeteksi otomatis URL aktif dari protokol, host, dan subfolder.
   - Pada server produksi/VPS, konfigurasi juga dapat di-override lewat environment variables (`APP_BASE_URL`, `APP_TIMEZONE`, `APP_DEBUG`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`).
   - Akses langsung ke `config/`, `includes/`, `database/`, serta file sensitif (`*.sql`, `*.local.php`, `*.example.php`, dotfiles) diblokir melalui `.htaccess`.

---

## 6. Panduan Menjalankan Aplikasi dan Daftar Akun Demo
Buka peramban dan akses alamat:
`http://localhost/pinjamkarya/`

Pada halaman **Masuk Akun (`auth/login.php`)**, Anda juga dapat langsung mengeklik tombol **Akun Uji Coba Cepat** untuk mengisi email dan kata sandi secara otomatis:

| Peran Pengguna | Nama Akun | Email Login | Kata Sandi | Keterangan Pengujian |
| :--- | :--- | :--- | :--- | :--- |
| **Admin Hub** | Aflah (Admin Hub) | `aflah@pinjamkarya.id` | `password` | Verifikasi teknisi, aktivasi alat mitra, Inspeksi Awal & Akhir |
| **Mitra (Kontributor & Teknisi Terverifikasi)** | Balqis (Mitra & Teknisi) | `balqis@pinjamkarya.id` | `password` | Titip alat mitra, klaim tiket kerusakan, kirim diagnosis & biaya |
| **Warga (Skor Tinggi: 110)** | Fadhol (Warga) | `fadhol@pinjamkarya.id` | `password` | Memenuhi syarat meminjam seluruh alat dan menyetujui biaya servis |
| **Warga (Skor Rendah: 65)** | Siti (Warga) | `siti@pinjamkarya.id` | `password` | Menguji fitur blokir otomatis saat meminjam alat dengan `min_skor > 65` |
| **Mitra (Teknisi Belum Diverifikasi)** | Budi (Teknisi Baru) | `budi@pinjamkarya.id` | `password` | Menguji proteksi meja antrean teknisi sebelum diverifikasi Admin |
