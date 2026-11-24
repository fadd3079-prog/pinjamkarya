<?php
/**
 * Halaman inspeksi fisik aset (serah terima awal & pengembalian akhir).
 *
 * Tujuan : Mencatat kondisi fisik alat saat diserahkan dan dikembalikan,
 *          memperbarui status peminjaman/aset, menyesuaikan Skor Kepercayaan
 *          warga, serta menerbitkan tiket perbaikan otomatis bila alat rusak.
 * Akses  : Admin Hub (role 'admin').
 * Alur   : Validasi transaksi & jenis inspeksi -> tampilkan form -> simpan hasil
 *          inspeksi dalam transaksi database -> redirect ke daftar transaksi.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

$id_petugas    = (int)$_SESSION['user']['id_user'];
$id_peminjaman = (int)($_GET['id'] ?? ($_POST['id_peminjaman'] ?? 0));
$jenis         = ($_GET['jenis'] ?? ($_POST['jenis'] ?? '')) === 'Akhir' ? 'Akhir' : 'Awal';

// Transaksi wajib dipilih sebelum halaman inspeksi dapat dibuka.
if ($id_peminjaman <= 0) {
    set_flash('warning', 'Pilih transaksi peminjaman terlebih dahulu.');
    header('Location: ' . BASE_URL . '/admin/kelola_transaksi.php');
    exit;
}

$pinjam = pk_stmt_one(
    $conn,
    "SELECT p.*, a.id_alat, a.id_kategori, a.nama_alat, a.foto_alat, a.status_alat,
            u.nama AS nama_warga, u.skor_kredibilitas
     FROM peminjaman p
     JOIN aset_alat a ON p.id_alat = a.id_alat
     JOIN users u ON p.id_warga = u.id_user
     WHERE p.id_peminjaman = ? LIMIT 1",
    'i',
    [$id_peminjaman]
);

if (empty($pinjam)) {
    set_flash('danger', 'Transaksi peminjaman tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/kelola_transaksi.php');
    exit;
}

// Inspeksi Awal hanya untuk booking baru; Inspeksi Akhir hanya saat alat dipinjam.
if ($jenis === 'Awal' && $pinjam['status_peminjaman'] !== 'Dipesan') {
    set_flash('warning', 'Inspeksi Awal hanya untuk transaksi berstatus Dipesan.');
    header('Location: ' . BASE_URL . '/admin/kelola_transaksi.php');
    exit;
}

if ($jenis === 'Akhir' && $pinjam['status_peminjaman'] !== 'Dipinjam') {
    set_flash('warning', 'Inspeksi Akhir hanya untuk transaksi berstatus Dipinjam.');
    header('Location: ' . BASE_URL . '/admin/kelola_transaksi.php');
    exit;
}

// Selisih hari keterlambatan pengembalian (0 bila tepat waktu).
$hari_telat = max(0, (int)floor((strtotime(date('Y-m-d')) - strtotime($pinjam['tgl_selesai'])) / 86400));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
        header('Location: ' . BASE_URL . '/admin/inspeksi.php?id=' . $id_peminjaman . '&jenis=' . $jenis);
        exit;
    }

    $kondisi_fisik    = $_POST['kondisi_fisik'] ?? '';
    $catatan_inspeksi = trim($_POST['catatan_inspeksi'] ?? '');

    if (!in_array($kondisi_fisik, ['Baik', 'Rusak Ringan', 'Rusak Berat'], true) || $catatan_inspeksi === '') {
        set_flash('warning', 'Pilih kondisi fisik dan isi catatan pemeriksaan.');
    } else {
        mysqli_begin_transaction($conn);

        try {
            $id_alat = (int)$pinjam['id_alat'];
            $id_warga = (int)$pinjam['id_warga'];

            if ($jenis === 'Awal') {
                // Serah terima: catat inspeksi, aktifkan peminjaman, tandai aset dipinjam.
                $stmt_ins = mysqli_prepare($conn, "INSERT INTO inspeksi_alat (id_peminjaman, id_petugas, jenis_inspeksi, kondisi_fisik, catatan_inspeksi) VALUES (?, ?, 'Awal', ?, ?)");
                mysqli_stmt_bind_param($stmt_ins, 'iiss', $id_peminjaman, $id_petugas, $kondisi_fisik, $catatan_inspeksi);
                mysqli_stmt_execute($stmt_ins);
                mysqli_stmt_close($stmt_ins);

                $stmt_p = mysqli_prepare($conn, "UPDATE peminjaman SET status_peminjaman = 'Dipinjam' WHERE id_peminjaman = ?");
                mysqli_stmt_bind_param($stmt_p, 'i', $id_peminjaman);
                mysqli_stmt_execute($stmt_p);
                mysqli_stmt_close($stmt_p);

                $stmt_a = mysqli_prepare($conn, "UPDATE aset_alat SET status_alat = 'Dipinjam' WHERE id_alat = ?");
                mysqli_stmt_bind_param($stmt_a, 'i', $id_alat);
                mysqli_stmt_execute($stmt_a);
                mysqli_stmt_close($stmt_a);

                mysqli_commit($conn);
                set_flash('success', 'Inspeksi Awal selesai. Status diubah menjadi Dipinjam.');
                header('Location: ' . BASE_URL . '/admin/kelola_transaksi.php');
                exit;
            }

            // Pengembalian: catat inspeksi akhir lalu selesaikan peminjaman.
            $stmt_ins = mysqli_prepare($conn, "INSERT INTO inspeksi_alat (id_peminjaman, id_petugas, jenis_inspeksi, kondisi_fisik, catatan_inspeksi) VALUES (?, ?, 'Akhir', ?, ?)");
            mysqli_stmt_bind_param($stmt_ins, 'iiss', $id_peminjaman, $id_petugas, $kondisi_fisik, $catatan_inspeksi);
            mysqli_stmt_execute($stmt_ins);
            mysqli_stmt_close($stmt_ins);

            $stmt_p = mysqli_prepare($conn, "UPDATE peminjaman SET status_peminjaman = 'Selesai' WHERE id_peminjaman = ?");
            mysqli_stmt_bind_param($stmt_p, 'i', $id_peminjaman);
            mysqli_stmt_execute($stmt_p);
            mysqli_stmt_close($stmt_p);

            $pesan_skor = '';

            if ($kondisi_fisik === 'Baik') {
                // Alat kembali normal: aset siap dipinjam lagi.
                $stmt_a = mysqli_prepare($conn, "UPDATE aset_alat SET status_alat = 'Tersedia' WHERE id_alat = ?");
                mysqli_stmt_bind_param($stmt_a, 'i', $id_alat);
                mysqli_stmt_execute($stmt_a);
                mysqli_stmt_close($stmt_a);

                if ($hari_telat === 0) {
                    // Bonus +10 poin untuk pengembalian tepat waktu.
                    $stmt_u = mysqli_prepare($conn, 'UPDATE users SET skor_kredibilitas = skor_kredibilitas + 10 WHERE id_user = ?');
                    mysqli_stmt_bind_param($stmt_u, 'i', $id_warga);
                    mysqli_stmt_execute($stmt_u);
                    mysqli_stmt_close($stmt_u);
                    $pesan_skor = 'Warga mendapat +10 poin Skor Kepercayaan.';
                } else {
                    // Penalti keterlambatan: 5 poin per hari.
                    $penalti = 5 * $hari_telat;
                    $stmt_u = mysqli_prepare($conn, 'UPDATE users SET skor_kredibilitas = GREATEST(0, skor_kredibilitas - ?) WHERE id_user = ?');
                    mysqli_stmt_bind_param($stmt_u, 'ii', $penalti, $id_warga);
                    mysqli_stmt_execute($stmt_u);
                    mysqli_stmt_close($stmt_u);
                    $pesan_skor = 'Terlambat ' . $hari_telat . ' hari (-' . $penalti . ' poin).';
                }
            } else {
                // Alat rusak: pindahkan ke perawatan dan buat tiket servis otomatis.
                $stmt_a = mysqli_prepare($conn, "UPDATE aset_alat SET status_alat = 'Perawatan' WHERE id_alat = ?");
                mysqli_stmt_bind_param($stmt_a, 'i', $id_alat);
                mysqli_stmt_execute($stmt_a);
                mysqli_stmt_close($stmt_a);

                $penalti = 15 + (5 * $hari_telat);
                $stmt_u = mysqli_prepare($conn, 'UPDATE users SET skor_kredibilitas = GREATEST(0, skor_kredibilitas - ?) WHERE id_user = ?');
                mysqli_stmt_bind_param($stmt_u, 'ii', $penalti, $id_warga);
                mysqli_stmt_execute($stmt_u);
                mysqli_stmt_close($stmt_u);

                $kode_tiket          = generate_kode_tiket($conn);
                $id_kategori         = (int)$pinjam['id_kategori'];
                $nama_barang         = $pinjam['nama_alat'];
                $deskripsi_kerusakan = '[Auto-Tiket Inspeksi] ' . $catatan_inspeksi;
                $foto_barang         = $pinjam['foto_alat'];
                $metode_cek          = 'Diantar ke Hub';
                $status_tiket        = 'Menunggu Diagnosis';

                $stmt_t = mysqli_prepare($conn, 'INSERT INTO tiket_perbaikan (kode_tiket, id_pelapor, id_kategori, id_alat_hub, nama_barang, deskripsi_kerusakan, foto_barang, metode_cek, status_tiket) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt_t, 'siiisssss', $kode_tiket, $id_petugas, $id_kategori, $id_alat, $nama_barang, $deskripsi_kerusakan, $foto_barang, $metode_cek, $status_tiket);
                mysqli_stmt_execute($stmt_t);
                mysqli_stmt_close($stmt_t);

                $pesan_skor = 'Kondisi ' . $kondisi_fisik . ' (-' . $penalti . ' poin). Alat masuk Perawatan dan Tiket ' . $kode_tiket . ' diterbitkan.';
            }

            mysqli_commit($conn);
            set_flash('success', 'Inspeksi Akhir selesai. ' . $pesan_skor);
            header('Location: ' . BASE_URL . '/admin/kelola_transaksi.php');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            set_flash('danger', 'Gagal menyimpan hasil inspeksi.');
        }
    }
}

$page_title = 'Inspeksi ' . $jenis . ' - ' . $pinjam['kode_booking'];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Inspeksi <?= e($jenis) ?>: <?= e($pinjam['kode_booking']) ?></h1>
    <a href="<?= BASE_URL ?>/admin/kelola_transaksi.php" class="btn btn-muted">Kembali</a>
</div>

<div class="pk-grid pk-grid--2">
    <section class="pk-card" aria-labelledby="form-inspeksi-heading">
        <div class="pk-card-header">
            <h2 id="form-inspeksi-heading" class="pk-card-title">
                <?= svg_icon('clipboard', 18) ?>
                <span>Hasil Pemeriksaan</span>
            </h2>
        </div>

        <form method="post" action="<?= BASE_URL ?>/admin/inspeksi.php?id=<?= (int)$id_peminjaman ?>&amp;jenis=<?= e($jenis) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id_peminjaman" value="<?= (int)$id_peminjaman ?>">
            <input type="hidden" name="jenis" value="<?= e($jenis) ?>">

            <div class="form-group">
                <label for="kondisi_fisik" class="form-label">Kondisi Fisik</label>
                <select id="kondisi_fisik" name="kondisi_fisik" class="form-select" required>
                    <option value="Baik">Baik (Berfungsi Normal &amp; Lengkap)</option>
                    <option value="Rusak Ringan">Rusak Ringan (Butuh Servis Ringan)</option>
                    <option value="Rusak Berat">Rusak Berat (Komponen Utama Rusak)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="catatan_inspeksi" class="form-label">Catatan Pemeriksaan</label>
                <textarea id="catatan_inspeksi" name="catatan_inspeksi" class="form-control" rows="4" placeholder="Kondisi mesin, kabel, dan kelengkapan..." required></textarea>
            </div>

            <button type="submit" class="btn <?= $jenis === 'Awal' ? 'btn-primary' : 'btn-secondary' ?> w-100">
                <?= svg_icon('check-circle', 16) ?>
                <span><?= $jenis === 'Awal' ? 'Simpan &amp; Serahkan Alat' : 'Simpan &amp; Selesaikan Transaksi' ?></span>
            </button>
        </form>
    </section>

    <div>
        <section class="pk-card" aria-labelledby="info-pinjam-heading">
            <div class="pk-card-header">
                <h2 id="info-pinjam-heading" class="pk-card-title">
                    <?= svg_icon('box', 18) ?>
                    <span>Rincian Transaksi</span>
                </h2>
            </div>

            <ul class="pk-tool-meta mb-0">
                <li>
                    <span>Kode Booking</span>
                    <strong><?= e($pinjam['kode_booking']) ?></strong>
                </li>
                <li>
                    <span>Perkakas</span>
                    <strong><?= e($pinjam['nama_alat']) ?></strong>
                </li>
                <li>
                    <span>Peminjam</span>
                    <strong><?= e($pinjam['nama_warga']) ?> (Skor <?= (int)$pinjam['skor_kredibilitas'] ?>)</strong>
                </li>
                <li>
                    <span>Jadwal</span>
                    <strong><?= e(format_tanggal($pinjam['tgl_mulai'])) ?> - <?= e(format_tanggal($pinjam['tgl_selesai'])) ?></strong>
                </li>
                <?php if ($jenis === 'Akhir'): ?>
                    <li>
                        <span>Ketepatan Waktu</span>
                        <?php if ($hari_telat === 0): ?>
                            <strong class="text-secondary">Tepat Waktu (+10 Poin)</strong>
                        <?php else: ?>
                            <strong class="text-danger">Terlambat <?= $hari_telat ?> Hari (-<?= 5 * $hari_telat ?> Poin)</strong>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
            </ul>
        </section>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
