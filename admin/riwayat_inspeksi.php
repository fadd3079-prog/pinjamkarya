<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin', 'mitra']);
refresh_user_session($conn);

$user = $_SESSION['user'];
if ($user['role'] === 'mitra' && empty($user['is_kontributor'])) {
    set_flash('warning', 'Riwayat inspeksi hanya untuk Admin atau Mitra Kontributor Alat.');
    header('Location: ' . BASE_URL . '/auth/profil.php');
    exit;
}

$id_peminjaman_filter = (int)($_GET['id_peminjaman'] ?? 0);

$sql = "SELECT i.*, p.kode_booking, p.tgl_mulai, p.tgl_selesai, a.nama_alat, a.id_pemilik,
               w.nama AS nama_peminjam, pt.nama AS nama_petugas
        FROM inspeksi_alat i
        JOIN peminjaman p ON i.id_peminjaman = p.id_peminjaman
        JOIN aset_alat a ON p.id_alat = a.id_alat
        JOIN users w ON p.id_warga = w.id_user
        JOIN users pt ON i.id_petugas = pt.id_user
        WHERE 1=1";

$types = '';
$params = [];

if ($user['role'] === 'mitra') {
    $sql .= " AND a.id_pemilik = ?";
    $types .= 'i';
    $params[] = (int)$user['id_user'];
}

if ($id_peminjaman_filter > 0) {
    $sql .= " AND i.id_peminjaman = ?";
    $types .= 'i';
    $params[] = $id_peminjaman_filter;
}

$sql .= " ORDER BY i.id_inspeksi DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res_log = mysqli_stmt_get_result($stmt);

$page_title = 'Log Inspeksi';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Log Inspeksi Fisik</h1>
    <?php if ($id_peminjaman_filter > 0): ?>
        <a href="<?= BASE_URL ?>/admin/riwayat_inspeksi.php" class="btn btn-muted">Semua Log</a>
    <?php endif; ?>
</div>

<section class="pk-table-card" aria-label="Log Inspeksi">
    <?php if (mysqli_num_rows($res_log) === 0): ?>
        <div class="pk-empty-state">
            <div class="pk-empty-icon"><?= svg_icon('clipboard', 22) ?></div>
            <h3>Belum Ada Catatan Inspeksi</h3>
        </div>
    <?php else: ?>
        <div class="pk-table-scroll">
            <table class="pk-table">
                <thead>
                    <tr>
                        <th>ID &amp; Tanggal</th>
                        <th>Kode Booking</th>
                        <th>Alat</th>
                        <th>Peminjam</th>
                        <th>Tahap</th>
                        <th>Kondisi</th>
                        <th>Catatan &amp; Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($res_log)): ?>
                        <tr>
                            <td class="pk-nowrap" data-label="Tanggal">
                                <strong>#<?= (int)$row['id_inspeksi'] ?></strong> &bull;
                                <span class="text-muted"><?= e(format_tanggal($row['waktu_inspeksi'])) ?></span>
                            </td>
                            <td class="pk-td-head">
                                <div class="pk-td-head-row">
                                    <div class="pk-td-head-main">
                                        <strong class="pk-nowrap"><?= e($row['kode_booking']) ?></strong>
                                        <div class="pk-mobile-inline"><br><?= e($row['nama_alat']) ?></div>
                                    </div>
                                    <span class="pk-mobile-badge">
                                        <?php if ($row['kondisi_fisik'] === 'Baik'): ?>
                                            <span class="pk-badge pk-badge--success">Baik</span>
                                        <?php elseif ($row['kondisi_fisik'] === 'Rusak Ringan'): ?>
                                            <span class="pk-badge pk-badge--warning">Rusak Ringan</span>
                                        <?php else: ?>
                                            <span class="pk-badge pk-badge--danger">Rusak Berat</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="pk-desktop-cell" data-label="Alat"><strong><?= e($row['nama_alat']) ?></strong></td>
                            <td data-label="Peminjam"><?= e($row['nama_peminjam']) ?></td>
                            <td data-label="Tahap">
                                <span class="pk-badge <?= $row['jenis_inspeksi'] === 'Akhir' ? 'pk-badge--primary' : 'pk-badge--muted' ?>">
                                    <?= e($row['jenis_inspeksi']) ?>
                                </span>
                            </td>
                            <td class="pk-desktop-cell" data-label="Kondisi">
                                <?php if ($row['kondisi_fisik'] === 'Baik'): ?>
                                    <span class="pk-badge pk-badge--success">Baik</span>
                                <?php elseif ($row['kondisi_fisik'] === 'Rusak Ringan'): ?>
                                    <span class="pk-badge pk-badge--warning">Rusak Ringan</span>
                                <?php else: ?>
                                    <span class="pk-badge pk-badge--danger">Rusak Berat</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Catatan">
                                <div><?= e($row['catatan_inspeksi']) ?></div>
                                <div class="text-muted mt-1" style="font-size:0.82rem;">Petugas: <?= e($row['nama_petugas']) ?></div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
mysqli_stmt_close($stmt);
require_once ROOT_PATH . '/includes/footer.php';
?>
