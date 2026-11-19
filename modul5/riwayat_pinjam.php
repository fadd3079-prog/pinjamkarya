<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['warga']);
refresh_user_session($conn);

$id_warga = (int)$_SESSION['user']['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    $id_peminjaman = (int)($_POST['id_peminjaman'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE peminjaman SET status_peminjaman = 'Dibatalkan' WHERE id_peminjaman = ? AND id_warga = ? AND status_peminjaman = 'Dipesan'");
    mysqli_stmt_bind_param($stmt, 'ii', $id_peminjaman, $id_warga);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected > 0) {
        set_flash('info', 'Pesanan peminjaman berhasil dibatalkan.');
    } else {
        set_flash('warning', 'Pesanan tidak dapat dibatalkan.');
    }
    header('Location: ' . BASE_URL . '/modul5/riwayat_pinjam.php');
    exit;
}

$sql = "SELECT p.*, a.nama_alat, a.foto_alat, a.biaya_perawatan, k.nama_kategori,
        (SELECT CONCAT(i1.kondisi_fisik, ' : ', i1.catatan_inspeksi) FROM inspeksi_alat i1 WHERE i1.id_peminjaman = p.id_peminjaman AND i1.jenis_inspeksi = 'Awal' LIMIT 1) AS info_inspeksi_awal,
        (SELECT CONCAT(i2.kondisi_fisik, ' : ', i2.catatan_inspeksi) FROM inspeksi_alat i2 WHERE i2.id_peminjaman = p.id_peminjaman AND i2.jenis_inspeksi = 'Akhir' LIMIT 1) AS info_inspeksi_akhir
        FROM peminjaman p
        JOIN aset_alat a ON p.id_alat = a.id_alat
        JOIN kategori_alat k ON a.id_kategori = k.id_kategori
        WHERE p.id_warga = ?
        ORDER BY p.id_peminjaman DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id_warga);
mysqli_stmt_execute($stmt);
$res_pinjam = mysqli_stmt_get_result($stmt);

$page_title = 'Pinjaman Saya';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Pinjaman Saya</h1>
    <a href="<?= BASE_URL ?>/modul4/katalog.php" class="btn btn-primary">
        <?= svg_icon('plus', 16) ?>
        <span>Pinjam Alat</span>
    </a>
</div>

<?php if (mysqli_num_rows($res_pinjam) === 0): ?>
    <div class="pk-empty-state">
        <div class="pk-empty-icon"><?= svg_icon('calendar', 22) ?></div>
        <h3>Belum Ada Pinjaman</h3>
        <div class="mt-2">
            <a href="<?= BASE_URL ?>/modul4/katalog.php" class="btn btn-primary">Lihat Katalog</a>
        </div>
    </div>
<?php else: ?>
    <div class="pk-grid pk-grid--2">
        <?php while ($row = mysqli_fetch_assoc($res_pinjam)): ?>
            <article class="pk-card d-flex flex-column justify-between mb-0">
                <div>
                    <div class="d-flex align-center justify-between flex-wrap gap-2 mb-2">
                        <span class="pk-badge pk-badge--primary"><?= e($row['kode_booking']) ?></span>
                        <?= badge_status_pinjam($row['status_peminjaman']) ?>
                    </div>

                    <h2 class="mb-1"><?= e($row['nama_alat']) ?></h2>
                    <div class="text-muted mb-3" style="font-size:0.85rem;"><?= e($row['nama_kategori']) ?></div>

                    <ul class="pk-tool-meta">
                        <li>
                            <span>Jadwal</span>
                            <strong><?= e(format_tanggal($row['tgl_mulai'])) ?> - <?= e(format_tanggal($row['tgl_selesai'])) ?></strong>
                        </li>
                        <li>
                            <span>Biaya Rawat</span>
                            <strong><?= (float)$row['biaya_perawatan'] <= 0 ? 'Gratis' : rupiah($row['biaya_perawatan']) ?></strong>
                        </li>
                    </ul>

                    <?php if (!empty($row['info_inspeksi_awal']) || !empty($row['info_inspeksi_akhir'])): ?>
                        <div class="pk-guideline-box mb-3" style="white-space:normal;">
                            <?php if (!empty($row['info_inspeksi_awal'])): ?>
                                <div><strong>Inspeksi Awal:</strong> <?= e($row['info_inspeksi_awal']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['info_inspeksi_akhir'])): ?>
                                <div class="mt-1"><strong>Inspeksi Akhir:</strong> <?= e($row['info_inspeksi_akhir']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <?php if ($row['status_peminjaman'] === 'Dipesan'): ?>
                        <form method="post" action="<?= BASE_URL ?>/modul5/riwayat_pinjam.php" onsubmit="return confirm('Batalkan pesanan ini?');">
                            <input type="hidden" name="action" value="cancel_booking">
                            <input type="hidden" name="id_peminjaman" value="<?= (int)$row['id_peminjaman'] ?>">
                            <button type="submit" class="btn btn-danger w-100">
                                <?= svg_icon('x', 16) ?>
                                <span>Batalkan Pesanan</span>
                            </button>
                        </form>
                    <?php elseif ($row['status_peminjaman'] === 'Dipinjam'): ?>
                        <div class="pk-badge pk-badge--primary w-100 justify-center" style="min-height:42px;">
                            Kembalikan maks. <?= e(format_tanggal($row['tgl_selesai'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php
mysqli_stmt_close($stmt);
require_once ROOT_PATH . '/includes/footer.php';
?>
