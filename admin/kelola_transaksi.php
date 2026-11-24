<?php
/**
 * Halaman transaksi peminjaman (panel Admin Hub).
 *
 * Tujuan : Memantau seluruh booking, memfilter berdasarkan status, membatalkan
 *          pesanan no-show, serta menjadi pintu masuk inspeksi awal/akhir.
 * Akses  : Admin Hub (role 'admin').
 * Alur   : Bersihkan ghost booking -> aksi POST (cancel_noshow) -> muat daftar
 *          transaksi sesuai filter -> tampilkan tabel dan aksi inspeksi.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

bersihkan_ghost_booking($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_noshow') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
        header('Location: ' . BASE_URL . '/admin/kelola_transaksi.php');
        exit;
    }

    $id_peminjaman = (int)($_POST['id_peminjaman'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE peminjaman SET status_peminjaman = 'Dibatalkan' WHERE id_peminjaman = ? AND status_peminjaman = 'Dipesan'");
    mysqli_stmt_bind_param($stmt, 'i', $id_peminjaman);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    set_flash('warning', 'Pesanan dibatalkan karena tidak hadir (No-Show).');
    header('Location: ' . BASE_URL . '/admin/kelola_transaksi.php');
    exit;
}

$filter_status    = $_GET['status'] ?? '';
$allowed_statuses = ['Dipesan', 'Dipinjam', 'Selesai', 'Dibatalkan'];

$sql = "SELECT p.*, a.nama_alat, u.nama AS nama_warga, u.no_hp, u.skor_kredibilitas
        FROM peminjaman p
        JOIN aset_alat a ON p.id_alat = a.id_alat
        JOIN users u ON p.id_warga = u.id_user";

if (in_array($filter_status, $allowed_statuses, true)) {
    // Filter valid: tampilkan satu status saja.
    $sql .= " WHERE p.status_peminjaman = ? ORDER BY p.id_peminjaman DESC";
    $trx_list = pk_stmt_all($conn, $sql, 's', [$filter_status]);
} else {
    // Tanpa filter: urutkan berdasarkan prioritas status lalu ID terbaru.
    $sql .= " ORDER BY FIELD(p.status_peminjaman, 'Dipesan', 'Dipinjam', 'Selesai', 'Dibatalkan'), p.id_peminjaman DESC";
    $trx_list = pk_stmt_all($conn, $sql);
}

$page_title = 'Transaksi Peminjaman';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Transaksi Peminjaman</h1>
    <a href="<?= BASE_URL ?>/admin/riwayat_inspeksi.php" class="btn btn-outline">
        <?= svg_icon('clipboard', 16) ?>
        <span>Log Inspeksi</span>
    </a>
</div>

<div class="pk-pill-tabs pk-filter-bar" role="navigation" aria-label="Filter status transaksi">
    <a href="<?= BASE_URL ?>/admin/kelola_transaksi.php" class="pk-pill<?= $filter_status === '' ? ' is-active' : '' ?>">Semua</a>
    <?php foreach ($allowed_statuses as $st): ?>
        <a href="<?= BASE_URL ?>/admin/kelola_transaksi.php?status=<?= urlencode($st) ?>" class="pk-pill<?= $filter_status === $st ? ' is-active' : '' ?>"><?= e($st) ?></a>
    <?php endforeach; ?>
</div>

<section class="pk-table-card" aria-labelledby="trx-heading">
    <div class="pk-table-card-head">
        <h2 id="trx-heading" class="pk-card-title">
            <?= svg_icon('calendar', 18) ?>
            <span>Daftar Transaksi</span>
        </h2>
    </div>

    <div class="pk-table-scroll">
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
                <?php if (count($trx_list) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">Tidak ada transaksi pada filter ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trx_list as $t): ?>
                        <tr>
                            <td class="pk-td-head">
                                <div class="pk-td-head-row">
                                    <div class="pk-td-head-main">
                                        <span class="pk-code-tag"><?= e($t['kode_booking']) ?></span>
                                        <div class="pk-mobile-inline"><br><?= e($t['nama_alat']) ?></div>
                                    </div>
                                    <span class="pk-mobile-badge"><?= badge_status_pinjam($t['status_peminjaman']) ?></span>
                                </div>
                            </td>
                            <td class="pk-desktop-cell" data-label="Perkakas"><strong><?= e($t['nama_alat']) ?></strong></td>
                            <td data-label="Peminjam">
                                <strong><?= e(format_nama_pengguna((string)$t['nama_warga'])) ?></strong><br>
                                <span class="pk-td-sub"><?= e($t['no_hp']) ?> &bull; Skor <?= (int)$t['skor_kredibilitas'] ?></span>
                            </td>
                            <td data-label="Jadwal" class="pk-nowrap">
                                <?= e(format_tanggal($t['tgl_mulai'])) ?> - <strong><?= e(format_tanggal($t['tgl_selesai'])) ?></strong>
                            </td>
                            <td class="pk-desktop-cell" data-label="Status"><?= badge_status_pinjam($t['status_peminjaman']) ?></td>
                            <td class="pk-td-actions">
                                <?php if ($t['status_peminjaman'] === 'Dipesan'): ?>
                                    <div class="btn-group-wrap">
                                        <a href="<?= BASE_URL ?>/admin/inspeksi.php?id=<?= (int)$t['id_peminjaman'] ?>&amp;jenis=Awal" class="btn btn-primary">
                                            <?= svg_icon('clipboard', 16) ?>
                                            <span>Inspeksi Awal</span>
                                        </a>
                                        <form method="post" action="<?= BASE_URL ?>/admin/kelola_transaksi.php" data-confirm="Batalkan pesanan <?= e($t['kode_booking']) ?> (No-Show)?" data-confirm-title="Batalkan Pesanan" data-confirm-btn="Batalkan" data-confirm-tone="danger">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel_noshow">
                                            <input type="hidden" name="id_peminjaman" value="<?= (int)$t['id_peminjaman'] ?>">
                                            <button type="submit" class="btn btn-danger btn-icon" title="No-Show" aria-label="Batalkan No-Show <?= e($t['kode_booking']) ?>">
                                                <?= svg_icon('x', 16) ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($t['status_peminjaman'] === 'Dipinjam'): ?>
                                    <a href="<?= BASE_URL ?>/admin/inspeksi.php?id=<?= (int)$t['id_peminjaman'] ?>&amp;jenis=Akhir" class="btn btn-secondary">
                                        <?= svg_icon('check-circle', 16) ?>
                                        <span>Inspeksi Akhir</span>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/admin/riwayat_inspeksi.php?id_peminjaman=<?= (int)$t['id_peminjaman'] ?>" class="btn btn-muted btn-icon" title="Hasil Inspeksi" aria-label="Lihat Hasil Inspeksi <?= e($t['kode_booking']) ?>">
                                        <?= svg_icon('eye', 16) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
