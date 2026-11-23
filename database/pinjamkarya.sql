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
