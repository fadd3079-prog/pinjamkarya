# TECHNICAL SPECIFICATION & AGENTIC BUILD BLUEPRINT: PINJAMKARYA

> **Instruksi untuk AI Coding Agent (OpenCode):**
> Dokumen ini adalah spesifikasi tunggal (*Single Source of Truth*) untuk membangun aplikasi web **PinjamKarya** dari awal hingga akhir. Ikuti seluruh aturan arsitektur, nama tabel/kolom basis data, struktur direktori, serta 9 aturan pengaman logika (*guardrails*) di bawah ini secara ketat. Jangan mengubah nama kolom SQL, jangan mengganti *tech stack* ke *framework* lain, dan jangan meninggalkan kode *placeholder* atau `TODO`.

---

## 1. Gambaran Umum Proyek & Konteks
- **Nama Aplikasi:** PinjamKarya — Platform Pusat Berbagi Aset Fisik Komunitas (*Library of Things*) & Layanan Perbaikan Barang (*Right-to-Repair Hub*).
- **Konteks Akademik:** Tugas Proyek Akhir (Responsi 2) Praktikum Pemrograman Web I — Jurusan Informatika, Fakultas Teknik, Universitas Jenderal Soedirman (2026).
- **Target SDGs:**
  - **SDG 12 (*Responsible Consumption and Production*):** Menekan timbulan limbah elektronik (*e-waste*) dan memperpanjang masa pakai perkakas melalui peminjaman bersama serta perbaikan barang rusak.
  - **SDG 10 (*Reduced Inequalities*):** Membuka akses alat produktif UMKM dan alat bantu kesehatan rumah tangga secara gratis/berbiaya minimal bagi warga, serta memberi saluran pesanan kerja bagi teknisi lokal independen.
- **Tim Pengembang:**
  1. **Aflah** — Admin Pengelola Hub (Modul 1, Modul 2, Modul 3)
  2. **Balqis** — Mitra Kontributor & Teknisi (Modul 4, Modul 5, Modul 6)
  3. **Fadhol** — Warga & Pelaku UMKM (Modul 7, Modul 8, Modul 9)

---

## 2. Tech Stack & Batasan Arsitektur (Wajib Dipatuhi)
Aplikasi **wajib** dibangun menggunakan tumpukan teknologi standar Praktikum Pemrograman Web 1 agar ringan, portabel di XAMPP/Laragon, dan sesuai rubrik penilaian:
1. **Backend:** PHP 8.x Native terstruktur menggunakan ekstensi **MySQLi** (*procedural* atau *object-oriented* yang konsisten) dengan **Prepared Statements** (`mysqli_prepare`) untuk seluruh kueri berparameter.
2. **Database:** MySQL / MariaDB (`utf8mb4_unicode_ci`, engine `InnoDB`).
3. **Frontend:** HTML5, CSS3, **Bootstrap 5.3.3 (CDN)**, **Bootstrap Icons 1.11.3 (CDN)**, dan Vanilla JavaScript ringan untuk interaktivitas UI/validasi tanggal.
4. **Keamanan & Portabilitas:**
   - Password wajib menggunakan `password_hash($pass, PASSWORD_DEFAULT)` dan `password_verify()`.
   - Output HTML wajib disanitasi menggunakan fungsi helper `e()` (`htmlspecialchars`).
   - Upload gambar dibatasi pada ekstensi `jpg`, `jpeg`, `png`, `webp` dengan ukuran maksimal `2 MB` dan nama file unik berbasis `time()`.
   - Konfigurasi URL dan database dipusatkan di `config/app.php` dan `config/database.php`.

---

## 3. Struktur Direktori & Manifes File Lengkap
Bangun seluruh struktur direktori dan berkas berikut secara lengkap:

```text
pinjamkarya/
├── assets/
│   └── uploads/
│       ├── alat/
│       │   └── .gitkeep
│       └── tiket/
│           └── .gitkeep
├── config/
│   ├── app.php
│   └── database.php
├── database/
│   └── pinjamkarya.sql
├── includes/
│   ├── footer.php
│   ├── functions.php
│   ├── header.php
│   └── sidebar.php
├── modul1/
│   ├── kelola_user.php
│   ├── login.php
│   ├── logout.php
│   ├── profil.php
│   └── register.php
├── modul2/
│   ├── kelola_kategori.php
│   └── panduan.php
├── modul3/
│   ├── aset_mitra.php
│   └── kelola_aset.php
├── modul4/
│   ├── detail_alat.php
│   └── katalog.php
├── modul5/
│   ├── kelola_transaksi.php
│   ├── reservasi.php
│   └── riwayat_pinjam.php
├── modul6/
│   ├── inspeksi.php
│   └── riwayat_inspeksi.php
├── modul7/
│   └── buat_tiket.php
├── modul8/
│   ├── antrean_teknisi.php
│   ├── kelola_tiket.php
│   └── tiket_saya.php
├── modul9/
│   └── dashboard.php
├── index.php
└── README.md
```

---

## 4. Skema Basis Data Terkunci (`database/pinjamkarya.sql`)
Gunakan skrip DDL dan *seed data* berikut secara persis tanpa mengubah nama tabel, kolom, maupun nilai `ENUM`:

```sql
CREATE DATABASE IF NOT EXISTS pinjamkarya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pinjamkarya;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tiket_perbaikan;
DROP TABLE IF EXISTS inspeksi_alat;
DROP TABLE IF EXISTS peminjaman;
DROP TABLE IF EXISTS aset_alat;
DROP TABLE IF EXISTS kategori_alat;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. TABEL USERS (Modul 1: Autentikasi, Peran Ganda Mitra & Skor Kredibilitas)
CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    alamat TEXT NOT NULL,
    role ENUM('admin', 'mitra', 'warga') NOT NULL DEFAULT 'warga',
    is_kontributor TINYINT(1) NOT NULL DEFAULT 0,
    is_teknisi TINYINT(1) NOT NULL DEFAULT 0,
    is_verified_teknisi TINYINT(1) NOT NULL DEFAULT 0,
    skor_kredibilitas INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. TABEL KATEGORI ALAT (Modul 2: Master Kategori, Panduan & Acuan Dampak SDGs)
CREATE TABLE kategori_alat (
    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    panduan_keselamatan TEXT NOT NULL,
    rata_rata_berat_kg DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    estimasi_harga_baru DECIMAL(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB;

-- 3. TABEL ASET ALAT (Modul 3: Inventaris Unit Fisik Komunitas & Titipan Mitra)
CREATE TABLE aset_alat (
    id_alat INT AUTO_INCREMENT PRIMARY KEY,
    id_kategori INT NOT NULL,
    id_pemilik INT NOT NULL,
    nama_alat VARCHAR(150) NOT NULL,
    deskripsi TEXT NOT NULL,
    foto_alat VARCHAR(255) NOT NULL DEFAULT 'default_alat.jpg',
    harga_beli_baru DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    biaya_perawatan DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    max_hari_pinjam INT NOT NULL DEFAULT 3,
    min_skor INT NOT NULL DEFAULT 50,
    status_alat ENUM('Menunggu Verifikasi', 'Tersedia', 'Dipinjam', 'Perawatan') NOT NULL DEFAULT 'Menunggu Verifikasi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alat_kategori FOREIGN KEY (id_kategori) REFERENCES kategori_alat(id_kategori) ON DELETE RESTRICT,
    CONSTRAINT fk_alat_pemilik FOREIGN KEY (id_pemilik) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. TABEL PEMINJAMAN (Modul 4 & 5: Reservasi, Jadwal & Kode Transaksi)
CREATE TABLE peminjaman (
    id_peminjaman INT AUTO_INCREMENT PRIMARY KEY,
    kode_booking VARCHAR(30) NOT NULL UNIQUE,
    id_alat INT NOT NULL,
    id_warga INT NOT NULL,
    tgl_mulai DATE NOT NULL,
    tgl_selesai DATE NOT NULL,
    setuju_panduan TINYINT(1) NOT NULL DEFAULT 1,
    status_peminjaman ENUM('Dipesan', 'Dipinjam', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Dipesan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pinjam_alat FOREIGN KEY (id_alat) REFERENCES aset_alat(id_alat) ON DELETE RESTRICT,
    CONSTRAINT fk_pinjam_warga FOREIGN KEY (id_warga) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. TABEL INSPEKSI ALAT (Modul 6: Checklist Kondisi Awal & Akhir)
CREATE TABLE inspeksi_alat (
    id_inspeksi INT AUTO_INCREMENT PRIMARY KEY,
    id_peminjaman INT NOT NULL,
    id_petugas INT NOT NULL,
    jenis_inspeksi ENUM('Awal', 'Akhir') NOT NULL,
    kondisi_fisik ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') NOT NULL,
    catatan_inspeksi TEXT NOT NULL,
    waktu_inspeksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inspeksi_pinjam FOREIGN KEY (id_peminjaman) REFERENCES peminjaman(id_peminjaman) ON DELETE CASCADE,
    CONSTRAINT fk_inspeksi_petugas FOREIGN KEY (id_petugas) REFERENCES users(id_user) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 6. TABEL TIKET PERBAIKAN (Modul 7 & 8: Right-to-Repair & Pelacakan Servis)
CREATE TABLE tiket_perbaikan (
    id_tiket INT AUTO_INCREMENT PRIMARY KEY,
    kode_tiket VARCHAR(30) NOT NULL UNIQUE,
    id_pelapor INT NOT NULL,
    id_kategori INT NOT NULL,
    id_teknisi INT NULL DEFAULT NULL,
    id_alat_hub INT NULL DEFAULT NULL,
    nama_barang VARCHAR(150) NOT NULL,
    deskripsi_kerusakan TEXT NOT NULL,
    foto_barang VARCHAR(255) NOT NULL DEFAULT 'default_tiket.jpg',
    metode_cek ENUM('Diantar ke Hub', 'Kunjungan Teknisi') NOT NULL DEFAULT 'Diantar ke Hub',
    diagnosis_teknisi TEXT NULL,
    estimasi_biaya DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estimasi_hari INT NOT NULL DEFAULT 0,
    persetujuan_warga TINYINT(1) NOT NULL DEFAULT 0,
    status_tiket ENUM('Menunggu Diagnosis', 'Menunggu Persetujuan', 'Dikerjakan', 'Siap Diambil', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Menunggu Diagnosis',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tiket_pelapor FOREIGN KEY (id_pelapor) REFERENCES users(id_user) ON DELETE CASCADE,
    CONSTRAINT fk_tiket_kategori FOREIGN KEY (id_kategori) REFERENCES kategori_alat(id_kategori) ON DELETE RESTRICT,
    CONSTRAINT fk_tiket_teknisi FOREIGN KEY (id_teknisi) REFERENCES users(id_user) ON DELETE SET NULL,
    CONSTRAINT fk_tiket_alat_hub FOREIGN KEY (id_alat_hub) REFERENCES aset_alat(id_alat) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================================
-- SEED DATA / DUMMY DATA SERAGAM (Password semua akun: password)
-- ==========================================================
INSERT INTO users (id_user, nama, email, password, no_hp, alamat, role, is_kontributor, is_teknisi, is_verified_teknisi, skor_kredibilitas) VALUES
(1, 'Aflah (Admin Hub)', 'aflah@pinjamkarya.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567801', 'Hub Komunitas Purwokerto Utara', 'admin', 0, 0, 0, 100),
(2, 'Balqis (Mitra Kontributor & Teknisi)', 'balqis@pinjamkarya.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567802', 'Jl. HR Bunyamin No. 14, Purwokerto', 'mitra', 1, 1, 1, 100),
(3, 'Fadhol (Warga & UMKM)', 'fadhol@pinjamkarya.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567803', 'Perum Griya Satria, Purwokerto', 'warga', 0, 0, 0, 110),
(4, 'Budi Santoso (Teknisi Baru)', 'budi@pinjamkarya.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567804', 'Arcawinangun, Purwokerto Timur', 'mitra', 0, 1, 0, 100),
(5, 'Siti Aminah (Warga Skor Rendah)', 'siti@pinjamkarya.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567805', 'Karangklesem, Purwokerto Selatan', 'warga', 0, 0, 0, 65);

INSERT INTO kategori_alat (id_kategori, nama_kategori, panduan_keselamatan, rata_rata_berat_kg, estimasi_harga_baru) VALUES
(1, 'Pertukangan & Konstruksi', '1. Gunakan kacamata pelindung dan sarung tangan kerja.\n2. Periksa kondisi kabel sebelum mencolokkan ke stopkontak.\n3. Jangan gunakan di area basah atau tergenang air.', 3.50, 650000.00),
(2, 'Peralatan Produksi UMKM', '1. Bersihkan sisa bahan makanan/produksi segera setelah pemakaian.\n2. Pastikan pengaturan suhu/daya sesuai kapasitas listrik rumah.\n3. Simpan kembali di tempat kering.', 6.20, 1450000.00),
(3, 'Alat Bantu Kesehatan', '1. Lakukan disinfeksi permukaan menggunakan alkohol swab sebelum dan sesudah pemakaian.\n2. Kunci rem roda saat pengguna berpindah posisi.\n3. Jangan melebihi batas beban maksimal 100 kg.', 12.00, 1200000.00),
(4, 'Elektronik Rumah Tangga', '1. Cabut steker dari sumber listrik sebelum membuka penutup komponen.\n2. Hindari beban listrik berlebih pada satu stopkontak.', 2.80, 450000.00);

INSERT INTO aset_alat (id_alat, id_kategori, id_pemilik, nama_alat, deskripsi, foto_alat, harga_beli_baru, biaya_perawatan, max_hari_pinjam, min_skor, status_alat) VALUES
(1, 1, 2, 'Mesin Bor Listrik Bosch GSB 550', 'Bor impact 550W lengkap dengan koper dan 10 mata bor tembok/besi. Cocok untuk instalasi rak atau perbaikan ringan.', 'default_alat.jpg', 680000.00, 5000.00, 3, 80, 'Tersedia'),
(2, 1, 1, 'Tangga Lipat Aluminium 2 Meter', 'Tangga lipat ringan anti karat dengan pijakan anti selip, beban maksimal 120 kg.', 'default_alat.jpg', 550000.00, 0.00, 3, 50, 'Tersedia'),
(3, 2, 2, 'Mesin Impulse Sealer Plastik 30cm', 'Alat perekat kemasan plastik tebal untuk pelaku UMKM kuliner/snack.', 'default_alat.jpg', 350000.00, 0.00, 5, 70, 'Tersedia'),
(4, 3, 1, 'Kursi Roda Standar Lipat', 'Kursi roda medis rangka baja krom dengan sandaran tangan nyaman untuk kebutuhan pemulihan sementara.', 'default_alat.jpg', 1250000.00, 0.00, 7, 50, 'Tersedia'),
(5, 2, 2, 'Hand Mixer Heavy Duty 500W', 'Mixer adonan roti/kue kapasitas sedang milik Mitra Balqis, menunggu verifikasi kelayakan fisik Admin.', 'default_alat.jpg', 1600000.00, 10000.00, 3, 90, 'Menunggu Verifikasi');

INSERT INTO peminjaman (id_peminjaman, kode_booking, id_alat, id_warga, tgl_mulai, tgl_selesai, setuju_panduan, status_peminjaman) VALUES
(1, 'PK-20261110-001', 1, 3, '2026-11-10', '2026-11-12', 1, 'Selesai'),
(2, 'PK-20261115-002', 2, 5, '2026-11-15', '2026-11-16', 1, 'Dipesan'),
(3, 'PK-20261121-003', 1, 3, '2026-11-21', '2026-11-23', 1, 'Dipesan');

INSERT INTO inspeksi_alat (id_inspeksi, id_peminjaman, id_petugas, jenis_inspeksi, kondisi_fisik, catatan_inspeksi) VALUES
(1, 1, 1, 'Awal', 'Baik', 'Unit bor menyala normal, kabel utuh, koper lengkap 10 mata bor.'),
(2, 1, 1, 'Akhir', 'Baik', 'Kembali tepat waktu, unit bersih dan berfungsi normal.');

INSERT INTO tiket_perbaikan (id_tiket, kode_tiket, id_pelapor, id_kategori, id_teknisi, nama_barang, deskripsi_kerusakan, foto_barang, metode_cek, diagnosis_teknisi, estimasi_biaya, estimasi_hari, persetujuan_warga, status_tiket) VALUES
(1, 'RTR-20261112-001', 3, 4, 2, 'Kipas Angin Berdiri Miyako', 'Baling-baling tidak mau berputar sama sekali meskipun tombol kecepatan ditekan.', 'default_tiket.jpg', 'Diantar ke Hub', 'Sekring termal (thermal fuse) di dalam dinamo putus akibat panas berlebih.', 35000.00, 1, 1, 'Selesai'),
(2, 'RTR-20261118-002', 3, 4, 2, 'Rice Cooker Cosmos 1.8L', 'Nasi cepat basi dan penghangat tidak panas, lampu warm mati.', 'default_tiket.jpg', 'Diantar ke Hub', 'Elemen pemanas bagian tutup dan thermostat otomatis perlu diganti.', 65000.00, 2, 0, 'Menunggu Persetujuan'),
(3, 'RTR-20261119-003', 5, 1, NULL, 'Gerinda Tangan Modern', 'Muncul percikan api besar di bagian belakang dan bau gosong.', 'default_tiket.jpg', 'Kunjungan Teknisi', NULL, 0.00, 0, 0, 'Menunggu Diagnosis');
```

---

## 5. Kontrak Fondasi & Template UI Bersama (`config/` & `includes/`)

### 5.1 `config/database.php`
- Koneksi `mysqli_connect('localhost', 'root', '', 'pinjamkarya')`, `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`, charset `utf8mb4`.

### 5.2 `config/app.php`
- Memulai session jika `session_status() === PHP_SESSION_NONE`.
- Mendefinisikan konstanta:
  - `BASE_URL` = `'http://localhost/pinjamkarya'`
  - `ROOT_PATH` = `dirname(__DIR__)`
  - `UPLOAD_ALAT_PATH` = `ROOT_PATH . '/assets/uploads/alat/'`
  - `UPLOAD_TIKET_PATH` = `ROOT_PATH . '/assets/uploads/tiket/'`
- Memuat `config/database.php` dan `includes/functions.php`.

### 5.3 `includes/functions.php`
Wajib menyediakan fungsi-fungsi berikut:
1. `e($string)`: `htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8')`.
2. `rupiah($angka)`: `'Rp ' . number_format((float)$angka, 0, ',', '.')`.
3. `set_flash($type, $message)` & `get_flash()`: Alert Bootstrap 5 berbasis `$_SESSION['flash']`.
4. `require_login()` & `require_role(array $allowed_roles)`: *Session & Role Guard*.
5. `refresh_user_session($conn)`: Menyinkronkan data terbaru user (terutama `skor_kredibilitas` dan `is_verified_teknisi`) dari tabel `users` ke `$_SESSION['user']`.
6. `upload_gambar($file, $target_dir, $prefix = 'img_')`: Validasi ekstensi (`jpg, jpeg, png, webp`), ukuran maks `2MB`, rename file unik.
7. `cek_bentrok_jadwal($conn, $id_alat, $tgl_mulai, $tgl_selesai)`: Menjalankan rumus irisan universal:
   ```sql
   SELECT COUNT(*) AS bentrok FROM peminjaman
   WHERE id_alat = ?
     AND status_peminjaman IN ('Dipesan', 'Dipinjam')
     AND (tgl_mulai <= ? AND tgl_selesai >= ?)
   ```
   *(Binding: `tgl_selesai` sebagai param 2, `tgl_mulai` sebagai param 3)*.
8. `bersihkan_ghost_booking($conn)`:
   ```sql
   UPDATE peminjaman SET status_peminjaman = 'Dibatalkan'
   WHERE status_peminjaman = 'Dipesan' AND tgl_mulai < CURDATE()
   ```
9. `get_gambar_alat($filename)` & `get_gambar_tiket($filename)`: Jika file fisik ada di `assets/uploads/alat/` atau `assets/uploads/tiket/` (dan bukan `default_alat.jpg` / `default_tiket.jpg` yang belum ada fisiknya), kembalikan URL file tersebut; jika belum ada file fisiknya, kembalikan URL *placeholder* SVG data-URI atau gambar bawaan yang elegan sehingga **tidak pernah muncul ikon gambar pecah (*broken image*)** saat demo.

### 5.4 `includes/header.php`, `includes/sidebar.php`, `includes/footer.php`
- Tema warna hijau sirkular (*eco-circular emerald* `#193829` untuk sidebar, `bg-success` untuk navbar).
- Navbar menampilkan logo **PinjamKarya**, badge **Skor Kredibilitas** (khusus role `warga`), nama & role pengguna, serta tombol Masuk/Daftar/Keluar.
- Sidebar merender menu secara dinamis sesuai peran (`public`, `warga`, `mitra` dengan pengecekan `is_kontributor` dan `is_teknisi`, serta `admin`).
- `index.php` di root mengarahkan pengunjung ke `modul4/katalog.php`.

---

## 6. Spesifikasi Fungsional & Teknis 9 Modul

### Modul 1: Autentikasi & Skor Kredibilitas Warga (`modul1/`)
- **`login.php`**: Login menggunakan `email` dan `password` (`password_verify`), menyimpan array user lengkap ke `$_SESSION['user']`. Sertakan info akun demo cepat di bawah form (`aflah@pinjamkarya.id`, `balqis@pinjamkarya.id`, `fadhol@pinjamkarya.id`, password: `password`) untuk memudahkan pengujian.
- **`register.php`**: Registrasi akun baru dengan pilihan `role` (`warga` atau `mitra`). Jika memilih `mitra`, tampilkan checkbox sub-peran (`is_kontributor` dan/atau `is_teknisi`, minimal pilih satu). Akun `warga` otomatis mendapat `skor_kredibilitas = 100`.
- **`logout.php`**: Menghancurkan sesi dan mengarahkan ke `login.php`.
- **`profil.php`**: Menampilkan dan memperbarui profil (`nama`, `no_hp`, `alamat`, ganti password opsional). Untuk `warga`, tampilkan kartu besar **Skor Kredibilitas Saat Ini** beserta riwayat perubahan poin/inspeksi. Untuk `mitra` teknisi, tampilkan badge status verifikasi (`Terverifikasi` vs `Menunggu Verifikasi Admin`).
- **`kelola_user.php` (Admin Only)**: Daftar seluruh pengguna. Tombol aksi **Verifikasi Teknisi** (`is_verified_teknisi = 1`) atau **Cabut Verifikasi** bagi mitra yang mendaftar sebagai teknisi.

### Modul 2: Master Kategori & Panduan Keselamatan (`modul2/`)
- **`panduan.php` (Semua Peran / Publik)**: Menampilkan daftar kategori perkakas beserta panduan keselamatan kerja wajib baca, estimasi harga baru, dan rata-rata berat (kg).
- **`kelola_kategori.php` (Admin Only)**: CRUD lengkap (`Tambah`, `Edit`, `Hapus` dengan proteksi *foreign key* jika kategori masih dipakai) untuk tabel `kategori_alat` (`nama_kategori`, `panduan_keselamatan`, `rata_rata_berat_kg`, `estimasi_harga_baru`).

### Modul 3: Manajemen Inventaris Aset Komunitas (`modul3/`)
- **`kelola_aset.php` (Admin Only)**:
  - CRUD seluruh unit pada tabel `aset_alat` lengkap dengan upload foto (`UPLOAD_ALAT_PATH`).
  - Bagian khusus **Antrean Verifikasi Alat Titipan Mitra** (`status_alat = 'Menunggu Verifikasi'`): Admin dapat menekan tombol **Setujui & Aktifkan (`Tersedia`)** atau **Tolak/Hapus**.
  - Admin dapat mengubah status unit antara `Tersedia` dan `Perawatan` secara manual jika diperlukan pemeliharaan berkala.
- **`aset_mitra.php` (Mitra dengan `is_kontributor = 1`)**:
  - Form penitipan alat baru milik Mitra (`id_pemilik = $_SESSION['user']['id_user']`, status awal otomatis `'Menunggu Verifikasi'`).
  - Tabel daftar alat yang dititipkan Mitra beserta status saat ini dan riwayat peminjamannya.

### Modul 4: Katalog & Kalender Cek Ketersediaan (`modul4/`)
- **`katalog.php` (Semua Peran / Publik)**:
  - **Wajib memanggil `bersihkan_ghost_booking($conn)` di baris awal eksekusi.**
  - Form filter: Kata kunci `nama_alat`, dropdown `id_kategori`, dan filter ketersediaan tanggal (`tgl_mulai` & `tgl_selesai`). Jika tanggal diisi, saring alat yang tidak bentrok pada rentang tanggal tersebut menggunakan logika irisan tanggal.
  - Menampilkan kartu katalog untuk alat berstatus `Tersedia` (dan badge info untuk unit yang sedang `Dipinjam` atau `Perawatan`), dilengkapi badge kategori, syarat `min_skor`, maks durasi pinjam, dan biaya perawatan (`Gratis` jika `0`).
- **`detail_alat.php` (Semua Peran / Publik)**:
  - Menampilkan detail spesifikasi alat, pemilik (Hub Komunitas atau Nama Mitra Kontributor), panduan keselamatan dari `kategori_alat`, tabel/daftar **Jadwal Sudah Terisi** (`status_peminjaman IN ('Dipesan', 'Dipinjam')` mulai hari ini ke depan), serta tombol **Ajukan Peminjaman** menuju `modul5/reservasi.php?id_alat=...`.

### Modul 5: Reservasi & Serah Terima Peminjaman (`modul5/`)
- **`reservasi.php` (Warga Only)**:
  - Validasi pra-tampil: Cek `skor_kredibilitas >= min_skor`. Jika kurang, tampilkan peringatan selisih poin dan kunci tombol submit.
  - Menampilkan kotak **Panduan Keselamatan Kategori** dengan checkbox wajib dicentang (`setuju_panduan = 1`).
  - Input `tgl_mulai` dan `tgl_selesai`.
  - Validasi backend saat POST:
    1. `tgl_mulai >= date('Y-m-d')` dan `tgl_selesai >= tgl_mulai`.
    2. Hitung durasi hari: `(strtotime($tgl_selesai) - strtotime($tgl_mulai)) / 86400 + 1 <= max_hari_pinjam`.
    3. Bungkus dalam `mysqli_begin_transaction($conn)`: panggil `cek_bentrok_jadwal()`. Jika bentrok, rollback dan tampilkan pesan error beserta tanggal kosong terdekat (`MAX(tgl_selesai) + 1 hari`).
    4. Jika aman, generate kode unik `PK-YYYYMMDD-XXX` (misal `PK-20261119-004`), `INSERT` ke `peminjaman` dengan status `'Dipesan'`, lalu `mysqli_commit($conn)`.
- **`riwayat_pinjam.php` (Warga Only)**:
  - Daftar riwayat peminjaman warga, kode booking, status transaksi, tombol **Batalkan Pesanan** (hanya muncul jika `status_peminjaman == 'Dipesan'`), dan modal/detail catatan hasil inspeksi awal & akhir.
- **`kelola_transaksi.php` (Admin Only)**:
  - Daftar seluruh transaksi peminjaman dengan filter status.
  - **Aturan Ketat:** Dilarang ada tombol ubah status langsung ke `Dipinjam` atau `Selesai`.
  - Jika `status_peminjaman == 'Dipesan'`: tampilkan tombol **Proses Serah Terima (Inspeksi Awal)** -> `modul6/inspeksi.php?id=...&jenis=Awal` dan tombol **Batalkan (No-Show)**.
  - Jika `status_peminjaman == 'Dipinjam'`: tampilkan tombol **Proses Pengembalian (Inspeksi Akhir)** -> `modul6/inspeksi.php?id=...&jenis=Akhir`.

### Modul 6: Inspeksi Kondisi & Riwayat Aset (`modul6/`)
- **`inspeksi.php` (Admin Only)**:
  - Menerima parameter `id` (`id_peminjaman`) dan `jenis` (`Awal` atau `Akhir`).
  - **Jika `jenis == 'Awal'` (dan status peminjaman `'Dipesan'`):**
    - Form kondisi fisik (`Baik`, `Rusak Ringan`, `Rusak Berat`) dan `catatan_inspeksi`.
    - Saat submit (dalam transaksi DB): `INSERT` ke `inspeksi_alat`, `UPDATE peminjaman SET status_peminjaman = 'Dipinjam'`, dan `UPDATE aset_alat SET status_alat = 'Dipinjam'`.
  - **Jika `jenis == 'Akhir'` (dan status peminjaman `'Dipinjam'`):**
    - Form kondisi pengembalian (`Baik`, `Rusak Ringan`, `Rusak Berat`) dan `catatan_inspeksi`.
    - Hitung selisih hari keterlambatan: `$hari_telat = max(0, (strtotime(date('Y-m-d')) - strtotime($pinjam['tgl_selesai'])) / 86400)`.
    - **Percabangan A (`kondisi_fisik == 'Baik'`):**
      - `UPDATE aset_alat SET status_alat = 'Tersedia'`.
      - Jika `$hari_telat == 0`: tambah `+10` ke `users.skor_kredibilitas` peminjam.
      - Jika `$hari_telat > 0`: kurangi `5 * $hari_telat` dari `users.skor_kredibilitas` (batas minimum skor `0`).
    - **Percabangan B (`kondisi_fisik != 'Baik'` — Jembatan Sirkularitas Otomatis):**
      - `UPDATE aset_alat SET status_alat = 'Perawatan'`.
      - Kurangi `15 + (5 * $hari_telat)` dari `users.skor_kredibilitas` (batas minimum skor `0`).
      - Otomatis `INSERT INTO tiket_perbaikan` (Modul 7) dengan `kode_tiket` baru (`RTR-YYYYMMDD-XXX`), `id_pelapor = $_SESSION['user']['id_user']` (Admin Hub), `id_kategori = $alat['id_kategori']`, `id_alat_hub = $alat['id_alat']`, `nama_barang = $alat['nama_alat']`, `deskripsi_kerusakan = '[Auto-Tiket Inspeksi] ' . $catatan_inspeksi`, `foto_barang = $alat['foto_alat']`, `metode_cek = 'Diantar ke Hub'`, `status_tiket = 'Menunggu Diagnosis'`.
    - `INSERT` ke `inspeksi_alat` (`jenis_inspeksi = 'Akhir'`) dan `UPDATE peminjaman SET status_peminjaman = 'Selesai'`.
- **`riwayat_inspeksi.php` (Admin & Mitra Kontributor)**:
  - Menampilkan log audit seluruh inspeksi fisik awal dan akhir (untuk Mitra Kontributor, difilter hanya untuk aset miliknya).

### Modul 7: Meja Tiket Perbaikan / *Right-to-Repair* (`modul7/`)
- **`buat_tiket.php` (Warga Only)**:
  - Formulir pengajuan kerusakan barang rumah tangga/UMKM: pilih `id_kategori`, isi `nama_barang`, `deskripsi_kerusakan`, pilih `metode_cek` (`Diantar ke Hub` atau `Kunjungan Teknisi`), dan unggah `foto_barang` (`UPLOAD_TIKET_PATH`).
  - Menerbitkan `kode_tiket` unik (`RTR-YYYYMMDD-XXX`) dengan `status_tiket = 'Menunggu Diagnosis'` dan `id_teknisi = NULL`.

### Modul 8: Diagnosis Teknisi & Pelacakan Servis (`modul8/`)
- **`tiket_saya.php` (Warga Only)**:
  - Menampilkan daftar tiket perbaikan yang diajukan warga beserta *stepper*/badge 6 tahap status.
  - Jika `status_tiket == 'Menunggu Persetujuan'`: tampilkan kotak penawaran dari Teknisi (`diagnosis_teknisi`, `estimasi_biaya`, `estimasi_hari`) beserta dua tombol utama:
    1. **Setujui Biaya & Mulai Servis:** `UPDATE tiket_perbaikan SET persetujuan_warga = 1, status_tiket = 'Dikerjakan'`.
    2. **Tolak Estimasi (Cari Teknisi Lain):** `UPDATE tiket_perbaikan SET id_teknisi = NULL, diagnosis_teknisi = NULL, estimasi_biaya = 0, estimasi_hari = 0, persetujuan_warga = 0, status_tiket = 'Menunggu Diagnosis'`.
  - Jika `status_tiket IN ('Menunggu Diagnosis', 'Menunggu Persetujuan')`: sediakan tombol **Batalkan Pengajuan** (`status_tiket = 'Dibatalkan'`).
  - Jika `status_tiket == 'Siap Diambil'`: sediakan tombol **Konfirmasi Barang Sudah Diterima** (`status_tiket = 'Selesai'`).
- **`antrean_teknisi.php` (Mitra dengan `is_teknisi = 1`)**:
  - Jika `is_verified_teknisi == 0`: tampilkan peringatan bahwa akun teknisi sedang menunggu verifikasi Admin Hub dan sembunyikan papan tiket.
  - Jika `is_verified_teknisi == 1`:
    - **Tab 1 — Antrean Tiket Terbuka (`status_tiket = 'Menunggu Diagnosis' AND id_teknisi IS NULL`):** Menampilkan daftar tiket warga maupun tiket otomatis alat Hub. Teknisi dapat menekan **Klaim & Kirim Diagnosis**, mengisi `diagnosis_teknisi`, `estimasi_biaya`, dan `estimasi_hari`, lalu menyimpan (ubah `id_teknisi = current_user`, `status_tiket = 'Menunggu Persetujuan'`). Pastikan validasi bahwa `id_teknisi IS NULL` saat UPDATE agar tidak terjadi balapan klaim.
    - **Tab 2 — Pekerjaan Servis Saya (`id_teknisi = current_user`):**
      - Jika `status_tiket == 'Dikerjakan'` (dan `persetujuan_warga == 1`): tampilkan tombol **Tandai Siap Diambil** (`status_tiket = 'Siap Diambil'`).
      - Larang keras perubahan ke `Dikerjakan` jika `persetujuan_warga == 0`.
- **`kelola_tiket.php` (Admin Only)**:
  - Memantau seluruh tiket perbaikan.
  - Jika `status_tiket == 'Siap Diambil'`: Admin dapat menekan tombol **Konfirmasi Selesai** (`status_tiket = 'Selesai'`). **Bonus Logika Sirkular:** Jika tiket tersebut memiliki `id_alat_hub IS NOT NULL` (berasal dari alat inventaris Hub yang rusak saat inspeksi), otomatis kembalikan `UPDATE aset_alat SET status_alat = 'Tersedia' WHERE id_alat = ?`.

### Modul 9: Dashboard Dampak Sirkularitas (`modul9/dashboard.php` — Semua Peran / Publik)
Seluruh angka pada dashboard **wajib** dihitung secara dinamis menggunakan kueri agregasi SQL (`SUM` dan `COUNT`) dari transaksi berstatus `'Selesai'` (tanpa angka *hardcode*):
1. **KPI 1 — Total Estimasi Penghematan Warga (Rp):**
   - Penghematan Pinjam = `SELECT COALESCE(SUM(a.harga_beli_baru - a.biaya_perawatan), 0) FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE p.status_peminjaman = 'Selesai'`
   - Penghematan Servis = `SELECT COALESCE(SUM(k.estimasi_harga_baru - t.estimasi_biaya), 0) FROM tiket_perbaikan t JOIN kategori_alat k ON t.id_kategori = k.id_kategori WHERE t.status_tiket = 'Selesai'`
   - Tampilkan total gabungan serta rincian kontribusi dari alur Pinjam vs Servis.
2. **KPI 2 — Total Limbah Elektronik & Perkakas Dicegah (Kg):**
   - `SELECT COALESCE(SUM(k.rata_rata_berat_kg), 0) FROM tiket_perbaikan t JOIN kategori_alat k ON t.id_kategori = k.id_kategori WHERE t.status_tiket = 'Selesai'`
3. **KPI 3 — Unit Barang Terselamatkan dari *E-Waste*:**
   - `SELECT COUNT(*) FROM tiket_perbaikan WHERE status_tiket = 'Selesai'`
4. **KPI 4 — Total Transaksi Peminjaman Tuntas:**
   - `SELECT COUNT(*) FROM peminjaman WHERE status_peminjaman = 'Selesai'`
5. **Tabel/Visualisasi Pendukung:**
   - **Utilitas Per Kategori Alat:** Jumlah transaksi pinjam dan unit servis tuntas per `kategori_alat` menggunakan progress bar Bootstrap 5.
   - **Jejak Dampak Sirkular Terbaru:** 5 transaksi peminjaman dan 5 tiket servis terakhir yang berstatus `'Selesai'`.

---

## 7. Ringkasan 9 Aturan Pengaman Logika (*Guardrails*)
1. **Anti-Double Booking:** Kueri irisan tanggal `(tgl_mulai <= ? AND tgl_selesai >= ?)` di dalam transaksi SQL (`mysqli_begin_transaction`).
2. **Auto-Clean Ghost Booking:** Pembersihan pesanan `'Dipesan'` yang `tgl_mulai < CURDATE()` menjadi `'Dibatalkan'` setiap halaman `modul4/katalog.php` diakses.
3. **Inspection-Gated Transitions:** Tidak ada tombol pintas status `Dipinjam`/`Selesai` di tabel admin; semua wajib via `modul6/inspeksi.php`.
4. **Auto-Maintenance & Auto-Repair Ticket:** Pengembalian alat tidak `'Baik'` otomatis mengubah status alat ke `'Perawatan'` dan menyisipkan tiket ke `tiket_perbaikan` (dan saat tiket `id_alat_hub` selesai, status alat kembali `'Tersedia'`).
5. **Credibility Score Enforcement:** Validasi `skor_kredibilitas >= min_skor` sebelum reservasi; penyesuaian skor otomatis saat inspeksi akhir (`+10` tepat waktu & baik, `-5/hari` terlambat, `-15` rusak).
6. **Dual-Role Mitra Guard:** Pemisahan menu & akses halaman berdasarkan flag `is_kontributor`, `is_teknisi`, dan `is_verified_teknisi`.
7. **6-Stage Repair State Machine & Re-Open:** Penguncian biaya sebelum `Dikerjakan` (`persetujuan_warga == 1`) dan pelepasan ulang (`id_teknisi = NULL`) jika warga menolak estimasi.
8. **Pure Dynamic SQL SDGs Metrics:** Seluruh metrik Modul 9 berasal dari `SUM`/`COUNT` transaksi `'Selesai'`.
9. **Portability & Upload Safety:** Keberadaan `.gitkeep` di folder upload, fallback gambar anti-pecah, dan konfigurasi terpusat.

---

## 8. Dokumentasi Proyek (`README.md`)
Hasilkan juga file `README.md` di root proyek yang memuat **6 bagian wajib** sesuai Buku Panduan Responsi:
1. Nama dan deskripsi proyek (**PinjamKarya** beserta identitas tim: Aflah, Balqis, Fadhol).
2. Latar belakang masalah dan tujuan proyek (keterkaitan dengan **SDG 12** dan **SDG 10**).
3. Daftar lengkap **9 modul/fitur** yang diimplementasikan beserta alur kerjanya.
4. Teknologi yang digunakan (PHP Native, MySQLi, MySQL/MariaDB, Bootstrap 5).
5. Panduan instalasi dan konfigurasi (langkah clone, impor `database/pinjamkarya.sql`, pengaturan `config/database.php` & `config/app.php`).
6. Panduan menjalankan aplikasi beserta daftar akun demo siap pakai (`aflah@pinjamkarya.id`, `balqis@pinjamkarya.id`, `fadhol@pinjamkarya.id`, `budi@pinjamkarya.id`, `siti@pinjamkarya.id` dengan password `password`).

---

## 9. Urutan Eksekusi Kerja untuk OpenCode
Lakukan pembangunan secara bertahap dan verifikasi setiap tahap sebelum melangkah ke tahap berikutnya:
- **Langkah 1:** Buat struktur direktori lengkap, `.gitkeep`, `database/pinjamkarya.sql`, `config/database.php`, `config/app.php`, `includes/functions.php`, `includes/header.php`, `includes/sidebar.php`, `includes/footer.php`, dan `index.php`.
- **Langkah 2:** Bangun **Modul 1, Modul 2, dan Modul 3** (`modul1/`, `modul2/`, `modul3/`).
- **Langkah 3:** Bangun **Modul 4, Modul 5, dan Modul 6** (`modul4/`, `modul5/`, `modul6/`).
- **Langkah 4:** Bangun **Modul 7, Modul 8, dan Modul 9** (`modul7/`, `modul8/`, `modul9/`).
- **Langkah 5:** Bangun `README.md` lengkap dan jalankan pengecekan sintaks seluruh file PHP (`php -l`) serta verifikasi seluruh tautan navigasi antar-modul agar bebas dari *syntax error* maupun tautan mati.