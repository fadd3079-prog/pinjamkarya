<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

bersihkan_ghost_booking($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_noshow') {
    $id_peminjaman = (int)($_POST['id_peminjaman'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE peminjaman SET status_peminjaman = 'Dibatalkan' WHERE id_peminjaman = ? AND status_peminjaman = 'Dipesan'");
    mysqli_stmt_bind_param($stmt, 'i', $id_peminjaman);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    set_flash('warning', 'Pesanan dibatalkan karena tidak hadir (No-Show).');
    header('Location: ' . BASE_URL . '/modul5/kelola_transaksi.php');
    exit;
}

$filter_status = $_GET['status'] ?? '';
$allowed_statuses = ['Dipesan', 'Dipinjam', 'Selesai', 'Dibatalkan'];

$sql = "SELECT p.*, a.nama_alat, u.nama AS nama_warga, u.no_hp, u.skor_kredibilitas
        FROM peminjaman p
        JOIN aset_alat a ON p.id_alat = a.id_alat
        JOIN users u ON p.id_warga = u.id_user";

if (in_array($filter_status, $allowed_statuses, true)) {
    $sql .= " WHERE p.status_peminjaman = ? ORDER BY p.id_peminjaman DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $filter_status);
    mysqli_stmt_execute($stmt);
    $res_trx = mysqli_stmt_get_result($stmt);
} else {
    $sql .= " ORDER BY FIELD(p.status_peminjaman, 'Dipesan', 'Dipinjam', 'Selesai', 'Dibatalkan'), p.id_peminjaman DESC";
    $res_trx = mysqli_query($conn, $sql);
}

$page_title = 'Transaksi Peminjaman';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Transaksi Peminjaman</h1>
    <div class="btn-group-wrap">
        <a href="<?= BASE_URL ?>/modul5/kelola_transaksi.php" class="btn <?= $filter_status === '' ? 'btn-primary' : 'btn-muted' ?>">Semua</a>
        <?php foreach ($allowed_statuses as $st): ?>
            <a href="<?= BASE_URL ?>/modul5/kelola_transaksi.php?status=<?= urlencode($st) ?>" class="btn <?= $filter_status === $st ? 'btn-primary' : 'btn-muted' ?>"><?= e($st) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<section class="pk-card" aria-labelledby="trx-heading">
    <div class="pk-card-header">
        <h2 id="trx-heading" class="pk-card-title">
            <?= svg_icon('calendar', 18) ?>
            <span>Daftar Transaksi</span>
        </h2>
        <a href="<?= BASE_URL ?>/modul6/riwayat_inspeksi.php" class="btn btn-outline">Log Inspeksi</a>
    </div>

    <div class="table-responsive">
        <table class="pk-table">
            <thead>
                <tr>
                    <th>Kode Booking</th>
                    <th>Perkakas</th>
                    <th>Peminjam</th>
                    <th>Jadwal</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($res_trx) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Belum ada transaksi peminjaman pada status ini.
                            <?php if ($filter_status !== ''): ?>
                                <a href="<?= BASE_URL ?>/modul5/kelola_transaksi.php">Tampilkan semua transaksi</a>.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($t = mysqli_fetch_assoc($res_trx)): ?>
                        <tr>
                            <td><strong><?= e($t['kode_booking']) ?></strong></td>
                            <td><strong><?= e($t['nama_alat']) ?></strong></td>
                            <td>
                                <strong><?= e($t['nama_warga']) ?></strong><br>
                                <span class="text-muted" style="font-size:0.82rem;"><?= e($t['no_hp']) ?> &bull; Skor <?= (int)$t['skor_kredibilitas'] ?></span>
                            </td>
                            <td>
                                <?= e(format_tanggal($t['tgl_mulai'])) ?> -<br>
                                <strong><?= e(format_tanggal($t['tgl_selesai'])) ?></strong>
                            </td>
                            <td><?= badge_status_pinjam($t['status_peminjaman']) ?></td>
                            <td>
                                <?php if ($t['status_peminjaman'] === 'Dipesan'): ?>
                                    <div class="btn-group-wrap">
                                        <a href="<?= BASE_URL ?>/modul6/inspeksi.php?id=<?= (int)$t['id_peminjaman'] ?>&amp;jenis=Awal" class="btn btn-primary">
                                            <?= svg_icon('clipboard', 16) ?>
                                            <span>Inspeksi Awal</span>
                                        </a>
                                        <form method="post" action="<?= BASE_URL ?>/modul5/kelola_transaksi.php" onsubmit="return confirm('Batalkan pesanan karena warga tidak hadir?');">
                                            <input type="hidden" name="action" value="cancel_noshow">
                                            <input type="hidden" name="id_peminjaman" value="<?= (int)$t['id_peminjaman'] ?>">
                                            <button type="submit" class="btn btn-danger">No-Show</button>
                                        </form>
                                    </div>
                                <?php elseif ($t['status_peminjaman'] === 'Dipinjam'): ?>
                                    <a href="<?= BASE_URL ?>/modul6/inspeksi.php?id=<?= (int)$t['id_peminjaman'] ?>&amp;jenis=Akhir" class="btn btn-secondary">
                                        <?= svg_icon('check-circle', 16) ?>
                                        <span>Inspeksi Akhir</span>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/modul6/riwayat_inspeksi.php?id_peminjaman=<?= (int)$t['id_peminjaman'] ?>" class="btn btn-muted">
                                        Hasil Inspeksi
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
