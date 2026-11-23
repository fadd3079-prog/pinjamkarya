<?php
require_once dirname(__DIR__) . '/config/app.php';
require_login();
refresh_user_session($conn);

$user = $_SESSION['user'];
$id_user = (int)$user['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $password_baru = $_POST['password_baru'] ?? '';

    if ($nama === '' || $no_hp === '' || $alamat === '') {
        set_flash('warning', 'Nama lengkap, nomor telepon, dan alamat tidak boleh kosong.');
    } elseif ($password_baru !== '' && strlen($password_baru) < 6) {
        set_flash('warning', 'Kata sandi baru minimal terdiri dari 6 karakter.');
    } else {
        if ($password_baru !== '') {
            $hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'UPDATE users SET nama = ?, no_hp = ?, alamat = ?, password = ? WHERE id_user = ?');
            mysqli_stmt_bind_param($stmt, 'ssssi', $nama, $no_hp, $alamat, $hash, $id_user);
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET nama = ?, no_hp = ?, alamat = ? WHERE id_user = ?');
            mysqli_stmt_bind_param($stmt, 'sssi', $nama, $no_hp, $alamat, $id_user);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        refresh_user_session($conn);
        $user = $_SESSION['user'];
        set_flash('success', 'Data profil berhasil diperbarui.');
        header('Location: ' . BASE_URL . '/modul1/profil.php');
        exit;
    }
}

$riwayat_inspeksi = [];
if ($user['role'] === 'warga') {
    $sql_hist = "SELECT i.*, p.kode_booking, p.tgl_mulai, p.tgl_selesai, a.nama_alat
                 FROM inspeksi_alat i
                 JOIN peminjaman p ON i.id_peminjaman = p.id_peminjaman
                 JOIN aset_alat a ON p.id_alat = a.id_alat
                 WHERE p.id_warga = ?
                 ORDER BY i.waktu_inspeksi DESC";
    $stmt_h = mysqli_prepare($conn, $sql_hist);
    mysqli_stmt_bind_param($stmt_h, 'i', $id_user);
    mysqli_stmt_execute($stmt_h);
    $res_h = mysqli_stmt_get_result($stmt_h);
    while ($row = mysqli_fetch_assoc($res_h)) {
        $riwayat_inspeksi[] = $row;
    }
    mysqli_stmt_close($stmt_h);
}

$page_title = 'Profil & Skor Kepercayaan';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Profil Akun</h1>
</div>

<div class="pk-grid pk-grid--2">
    <div>
        <?php if ($user['role'] === 'warga'): ?>
            <?php
            $skor = (int)$user['skor_kredibilitas'];
            $persen_skor = min(100, max(0, (int)round(($skor / 150) * 100)));
            ?>
            <section class="pk-card" aria-labelledby="skor-heading">
                <div class="pk-card-header">
                    <h2 id="skor-heading" class="pk-card-title">
                        <?= svg_icon('heart', 18) ?>
                        <span>Skor Kepercayaan</span>
                    </h2>
                </div>

                <div class="fs-xl fw-bold text-warm mb-2"><?= $skor ?> Poin</div>

                <div class="pk-progress mb-3" role="progressbar" aria-valuenow="<?= $skor ?>" aria-valuemin="0" aria-valuemax="150" aria-label="Skor Kepercayaan">
                    <div class="pk-progress-bar pk-progress-bar--warm" style="width: <?= $persen_skor ?>%;"></div>
                </div>

                <ul class="pk-tool-meta mb-0">
                    <li>
                        <span>Kembali Tepat Waktu &amp; Baik</span>
                        <strong class="text-secondary">+10 Poin</strong>
                    </li>
                    <li>
                        <span>Keterlambatan Pengembalian</span>
                        <strong class="text-danger">-5 Poin / Hari</strong>
                    </li>
                    <li>
                        <span>Kerusakan Fisik Alat</span>
                        <strong class="text-danger">-15 Poin</strong>
                    </li>
                </ul>
            </section>
        <?php elseif ($user['role'] === 'mitra'): ?>
            <section class="pk-card" aria-labelledby="mitra-heading">
                <div class="pk-card-header">
                    <h2 id="mitra-heading" class="pk-card-title">
                        <?= svg_icon('shield', 18) ?>
                        <span>Status Mitra</span>
                    </h2>
                </div>

                <ul class="pk-tool-meta mb-0">
                    <li>
                        <span>Kontributor Alat</span>
                        <strong><?= !empty($user['is_kontributor']) ? 'Aktif' : 'Nonaktif' ?></strong>
                    </li>
                    <li>
                        <span>Teknisi Perbaikan</span>
                        <strong><?= !empty($user['is_teknisi']) ? 'Aktif' : 'Nonaktif' ?></strong>
                    </li>
                    <?php if (!empty($user['is_teknisi'])): ?>
                        <li>
                            <span>Verifikasi Teknisi</span>
                            <?php if (!empty($user['is_verified_teknisi'])): ?>
                                <span class="pk-badge pk-badge--success">Terverifikasi</span>
                            <?php else: ?>
                                <span class="pk-badge pk-badge--warning">Menunggu Verifikasi</span>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </section>
        <?php else: ?>
            <section class="pk-card" aria-labelledby="admin-heading">
                <div class="pk-card-header">
                    <h2 id="admin-heading" class="pk-card-title">
                        <?= svg_icon('shield', 18) ?>
                        <span>Status Akun</span>
                    </h2>
                </div>
                <ul class="pk-tool-meta mb-0">
                    <li>
                        <span>Peran</span>
                        <strong>Admin Pengelola Hub</strong>
                    </li>
                    <li>
                        <span>Terdaftar Sejak</span>
                        <strong><?= e(format_tanggal($user['created_at'])) ?></strong>
                    </li>
                </ul>
            </section>
        <?php endif; ?>
    </div>

    <div>
        <section class="pk-card" aria-labelledby="edit-profil-heading">
            <div class="pk-card-header">
                <h2 id="edit-profil-heading" class="pk-card-title">
                    <?= svg_icon('user', 18) ?>
                    <span>Data Diri</span>
                </h2>
            </div>

            <form method="post" action="<?= BASE_URL ?>/modul1/profil.php">
                <div class="form-group">
                    <label for="email_view" class="form-label">Email</label>
                    <input type="email" id="email_view" class="form-control" value="<?= e($user['email']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" class="form-control" value="<?= e($user['nama']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="no_hp" class="form-label">No. Telepon / WhatsApp</label>
                    <input type="text" id="no_hp" name="no_hp" class="form-control" value="<?= e($user['no_hp']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="alamat" class="form-label">Alamat</label>
                    <textarea id="alamat" name="alamat" class="form-control" required><?= e($user['alamat']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="password_baru" class="form-label">Kata Sandi Baru (Opsional)</label>
                    <input type="password" id="password_baru" name="password_baru" class="form-control" placeholder="Kosongkan jika tidak diubah">
                </div>

                <button type="submit" class="btn btn-primary">
                    <?= svg_icon('check-circle', 16) ?>
                    <span>Simpan Perubahan</span>
                </button>
            </form>
        </section>
    </div>
</div>

<?php if ($user['role'] === 'warga'): ?>
<section class="pk-card" aria-labelledby="riwayat-skor-heading">
    <div class="pk-card-header">
        <h2 id="riwayat-skor-heading" class="pk-card-title">
            <?= svg_icon('clipboard', 18) ?>
            <span>Riwayat Inspeksi</span>
        </h2>
    </div>

    <?php if (empty($riwayat_inspeksi)): ?>
        <div class="pk-empty-state">
            <div class="pk-empty-icon"><?= svg_icon('clipboard', 22) ?></div>
            <h3>Belum Ada Riwayat Inspeksi</h3>
            <p class="mb-2">Catatan inspeksi akan muncul setelah Anda meminjam perkakas dan petugas Hub melakukan serah-terima fisik alat.</p>
            <div class="mt-2">
                <a href="<?= BASE_URL ?>/modul4/katalog.php" class="btn btn-primary">Jelajahi Katalog Alat</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="pk-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kode Booking</th>
                        <th>Perkakas</th>
                        <th>Tahap</th>
                        <th>Kondisi</th>
                        <th>Catatan Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat_inspeksi as $item): ?>
                        <tr>
                            <td><?= e(format_tanggal($item['waktu_inspeksi'])) ?></td>
                            <td><strong><?= e($item['kode_booking']) ?></strong></td>
                            <td><?= e($item['nama_alat']) ?></td>
                            <td>
                                <span class="pk-badge <?= $item['jenis_inspeksi'] === 'Akhir' ? 'pk-badge--primary' : 'pk-badge--muted' ?>">
                                    <?= e($item['jenis_inspeksi']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($item['kondisi_fisik'] === 'Baik'): ?>
                                    <span class="pk-badge pk-badge--success">Baik</span>
                                <?php elseif ($item['kondisi_fisik'] === 'Rusak Ringan'): ?>
                                    <span class="pk-badge pk-badge--warning">Rusak Ringan</span>
                                <?php else: ?>
                                    <span class="pk-badge pk-badge--danger">Rusak Berat</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($item['catatan_inspeksi']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
