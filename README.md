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

## 3. Daftar Lengkap 9 Modul dan Alur Kerja Sistem
1. **Modul 1: Autentikasi, Peran & Skor Kredibilitas (`modul1/`)**
   - Mengelola pendaftaran warga/mitra (`register.php`), autentikasi sesi (`login.php`, `logout.php`), pembaruan profil dan visualisasi Skor Kepercayaan (`profil.php`), serta panel Admin untuk memverifikasi teknisi baru (`kelola_user.php`).
2. **Modul 2: Master Kategori, Panduan Keselamatan & Parameter SDGs (`modul2/`)**
   - Menyediakan halaman publik prosedur keselamatan kerja wajib baca (`panduan.php`) serta CRUD master kategori beserta acuan bobot limbah (`rata_rata_berat_kg`) dan estimasi harga beli baru (`kelola_kategori.php`).
3. **Modul 3: Manajemen Inventaris Aset & Penitipan Mitra (`modul3/`)**
   - Mengelola inventaris perkakas Hub dan verifikasi fisik alat titipan mitra (`kelola_aset.php`), serta formulir penitipan alat bagi Mitra Kontributor (`aset_mitra.php`).
4. **Modul 4: Katalog Publik & Kalender Ketersediaan (`modul4/`)**
   - Menampilkan katalog perkakas dengan pembersihan otomatis *Ghost Booking* dan penyaringan jadwal kosong bebas bentrok (`katalog.php`), dilengkapi halaman spesifikasi dan kalender tanggal terisi (`detail_alat.php`).
5. **Modul 5: Mesin Reservasi & Transaksi Peminjaman (`modul5/`)**
   - Mengelola pengajuan reservasi dengan gerbang Skor Kepercayaan, persetujuan SOP keselamatan, dan transaksi SQL atomik anti-*double booking* (`reservasi.php`), riwayat kode booking warga (`riwayat_pinjam.php`), serta pemantauan transaksi oleh Admin (`kelola_transaksi.php`).
6. **Modul 6: Inspeksi Kondisi & Riwayat Aset (`modul6/`)**
   - Mengawal transisi status peminjaman melalui pemeriksaan fisik Awal dan Akhir (`inspeksi.php`). Saat Inspeksi Akhir, sistem otomatis menghitung penambahan/penalti Skor Kepercayaan warga dan menerbitkan tiket servis otomatis apabila alat kembali dalam keadaan rusak, serta mencatat log audit (`riwayat_inspeksi.php`).
7. **Modul 7: Meja Tiket Perbaikan / *Right-to-Repair* (`modul7/`)**
   - Memfasilitasi warga melaporkan kerusakan barang elektronik/perkakas lengkap dengan foto kondisi dan pilihan metode pengecekan (`buat_tiket.php`).
8. **Modul 8: Diagnosis Teknisi & Pelacakan Servis 6 Tahap (`modul8/`)**
   - Menghadirkan pelacakan progres servis 6 tahap dan keputusan persetujuan/penolakan estimasi biaya oleh warga (`tiket_saya.php`), meja kerja klaim dan diagnosis bagi teknisi terverifikasi (`antrean_teknisi.php`), serta pemulihan otomatis status alat inventaris Hub yang selesai diservis (`kelola_tiket.php`).
9. **Modul 9: Dashboard Dampak Sirkularitas SDGs (`modul9/dashboard.php`)**
   - Menghitung secara dinamis menggunakan agregasi SQL (`SUM` dan `COUNT`) total penghematan finansial warga (Rp), total bobot *e-waste* yang dicegah (Kg), jumlah barang terselamatkan, jumlah peminjaman tuntas, serta grafik SVG perbandingan dampak per kategori.

---

## 4. Teknologi yang Digunakan (Zero Framework & Zero Library)
- **Backend:** 100% Pure PHP Native (PHP 8.x) dengan arsitektur *Modular Procedural* dan ekstensi `mysqli` (Prepared Statements & Transaksi SQL Atomik).
- **Basis Data:** MySQL / MariaDB (InnoDB Relational Schema dengan Foreign Key Constraints).
- **Frontend Styling:** 100% Pure Vanilla CSS3 Lokal (`assets/css/pinjamkarya-ui.css`) menggunakan CSS Custom Properties (`:root`), CSS Grid, dan Flexbox tanpa framework CSS eksternal dan tanpa CDN.
- **Interaktivitas:** 100% Pure Vanilla JavaScript ES6+ Lokal (`assets/js/pinjamkarya.js`) tanpa library pihak ketiga.
- **Visual & Grafik:** Pure Inline SVG Icons & Native SVG Bar Chart (`includes/functions.php` & `modul9/dashboard.php`).

---

## 5. Panduan Instalasi dan Konfigurasi Lokal
1. **Salin Direktori Proyek:**
   Tempatkan folder `pinjamkarya` di dalam direktori web server lokal Anda, misalnya `C:\xampp\htdocs\pinjamkarya` atau `C:\xampp2\htdocs\pinjamkarya`.
2. **Nyalakan Layanan Web Server & Database:**
   Buka XAMPP Control Panel, lalu jalankan modul **Apache** dan **MySQL**.
3. **Impor Basis Data & Data Awal (*Seed Data*):**
   - Buka `http://localhost/phpmyadmin` di peramban Anda.
   - Pilih tab **Import**, unggah file `database/pinjamkarya.sql`, lalu tekan **Import / Go**.
   - Skrip tersebut akan otomatis membuat database `db_pinjamkarya`, 6 tabel relasional, serta data awal siap uji coba.
4. **Sesuaikan Konfigurasi Koneksi (Opsional):**
   - Periksa `config/database.php` (default: host `localhost`, user `root`, password `""`, database `db_pinjamkarya`).
   - Periksa `config/app.php` pada konstanta `BASE_URL` (default: `http://localhost/pinjamkarya`).

---

## 6. Panduan Menjalankan Aplikasi dan Daftar Akun Demo
Buka peramban dan akses alamat:
`http://localhost/pinjamkarya/`

Pada halaman **Masuk Akun (`modul1/login.php`)**, Anda juga dapat langsung mengeklik tombol **Akun Uji Coba Cepat** untuk mengisi email dan kata sandi secara otomatis:

| Peran Pengguna | Nama Akun | Email Login | Kata Sandi | Keterangan Pengujian |
| :--- | :--- | :--- | :--- | :--- |
| **Admin Hub** | Aflah (Admin Hub) | `aflah@pinjamkarya.id` | `password` | Verifikasi teknisi, aktivasi alat mitra, Inspeksi Awal & Akhir |
| **Mitra (Kontributor & Teknisi Terverifikasi)** | Balqis (Mitra & Teknisi) | `balqis@pinjamkarya.id` | `password` | Titip alat mitra, klaim tiket kerusakan, kirim diagnosis & biaya |
| **Warga (Skor Tinggi: 110)** | Fadhol (Warga) | `fadhol@pinjamkarya.id` | `password` | Memenuhi syarat meminjam seluruh alat dan menyetujui biaya servis |
| **Warga (Skor Rendah: 65)** | Siti (Warga) | `siti@pinjamkarya.id` | `password` | Menguji fitur blokir otomatis saat meminjam alat dengan `min_skor > 65` |
| **Mitra (Teknisi Belum Diverifikasi)** | Budi (Teknisi Baru) | `budi@pinjamkarya.id` | `password` | Menguji proteksi meja antrean teknisi sebelum diverifikasi Admin |
