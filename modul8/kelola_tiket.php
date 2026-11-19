<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_tiket'])) {
    $id_tiket = (int)$_POST['id_tiket'];
    $action = $_POST['action'];

    if ($action === 'approve_hub_ticket') {
        $stmt = mysqli_prepare($conn, "UPDATE tiket_perbaikan SET persetujuan_warga = 1, status_tiket = 'Dikerjakan' WHERE id_tiket = ? AND status_tiket = 'Menunggu Persetujuan'");
        mysqli_stmt_bind_param($stmt, 'i', $id_tiket);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'Estimasi servis aset Hub disetujui dan status diubah menjadi Dikerjakan.');
    } elseif ($action === 'confirm_complete') {
        mysqli_begin_transaction($conn);
        try {
            $stmt_get = mysqli_prepare($conn, 'SELECT id_alat_hub, status_tiket FROM tiket_perbaikan WHERE id_tiket = ? FOR UPDATE');
            mysqli_stmt_bind_param($stmt_get, 'i', $id_tiket);
            mysqli_stmt_execute($stmt_get);
            $tiket_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
            mysqli_stmt_close($stmt_get);

            if ($tiket_row && $tiket_row['status_tiket'] === 'Siap Diambil') {
                $stmt_u = mysqli_prepare($conn, "UPDATE tiket_perbaikan SET status_tiket = 'Selesai' WHERE id_tiket = ?");
                mysqli_stmt_bind_param($stmt_u, 'i', $id_tiket);
                mysqli_stmt_execute($stmt_u);
                mysqli_stmt_close($stmt_u);

                if (!empty($tiket_row['id_alat_hub'])) {
                    $id_alat_hub = (int)$tiket_row['id_alat_hub'];
                    $stmt_a = mysqli_prepare($conn, "UPDATE aset_alat SET status_alat = 'Tersedia' WHERE id_alat = ?");
                    mysqli_stmt_bind_param($stmt_a, 'i', $id_alat_hub);
                    mysqli_stmt_execute($stmt_a);
                    mysqli_stmt_close($stmt_a);
                    mysqli_commit($conn);
                    set_flash('success', 'Tiket selesai dan aset Hub kembali Tersedia di katalog.');
                } else {
                    mysqli_commit($conn);
                    set_flash('success', 'Tiket perbaikan selesai.');
                }
            } else {
                mysqli_rollback($conn);
                set_flash('warning', 'Tiket hanya dapat diselesaikan saat berstatus Siap Diambil.');
            }
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            set_flash('danger', 'Gagal menyelesaikan tiket.');
        }
    }

    header('Location: ' . BASE_URL . '/modul8/kelola_tiket.php');
    exit;
}

$sql = "SELECT t.*, k.nama_kategori, p.nama AS nama_pelapor, tek.nama AS nama_teknisi
        FROM tiket_perbaikan t
        JOIN kategori_alat k ON t.id_kategori = k.id_kategori
        JOIN users p ON t.id_pelapor = p.id_user
        LEFT JOIN users tek ON t.id_teknisi = tek.id_user
        ORDER BY t.id_tiket DESC";
$res_all = mysqli_query($conn, $sql);

$page_title = 'Tiket Perbaikan';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Tiket Perbaikan</h1>
</div>

<section class="pk-card" aria-label="Daftar Tiket Perbaikan">
    <div class="table-responsive">
        <table class="pk-table">
            <thead>
                <tr>
                    <th>Kode Tiket</th>
                    <th>Barang &amp; Kategori</th>
                    <th>Pelapor</th>
                    <th>Teknisi</th>
                    <th>Diagnosis &amp; Biaya</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($res_all)): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['kode_tiket']) ?></strong><br>
                            <span class="text-muted" style="font-size:0.82rem;"><?= e(format_tanggal($row['created_at'])) ?></span>
                        </td>
                        <td>
                            <strong><?= e($row['nama_barang']) ?></strong>
                            <div><span class="pk-badge pk-badge--muted mt-1"><?= e($row['nama_kategori']) ?></span></div>
                            <?php if (!empty($row['id_alat_hub'])): ?>
                                <div><span class="pk-badge pk-badge--danger mt-1">Aset Hub #<?= (int)$row['id_alat_hub'] ?></span></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($row['nama_pelapor']) ?><br>
                            <span class="text-muted" style="font-size:0.82rem;"><?= e($row['metode_cek']) ?></span>
                        </td>
                        <td><?= !empty($row['nama_teknisi']) ? e($row['nama_teknisi']) : '<span class="text-muted">-</span>' ?></td>
                        <td>
                            <?php if (!empty($row['diagnosis_teknisi'])): ?>
                                <div><?= e($row['diagnosis_teknisi']) ?></div>
                                <div class="mt-1"><strong><?= rupiah($row['estimasi_biaya']) ?></strong> (<?= (int)$row['estimasi_hari'] ?> hr)</div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= badge_status_tiket($row['status_tiket']) ?></td>
                        <td>
                            <?php if ($row['status_tiket'] === 'Siap Diambil'): ?>
                                <form method="post" action="<?= BASE_URL ?>/modul8/kelola_tiket.php">
                                    <input type="hidden" name="action" value="confirm_complete">
                                    <input type="hidden" name="id_tiket" value="<?= (int)$row['id_tiket'] ?>">
                                    <button type="submit" class="btn btn-secondary">
                                        <?= svg_icon('check-circle', 16) ?>
                                        <span>Selesai</span>
                                    </button>
                                </form>
                            <?php elseif ($row['status_tiket'] === 'Menunggu Persetujuan' && !empty($row['id_alat_hub'])): ?>
                                <form method="post" action="<?= BASE_URL ?>/modul8/kelola_tiket.php">
                                    <input type="hidden" name="action" value="approve_hub_ticket">
                                    <input type="hidden" name="id_tiket" value="<?= (int)$row['id_tiket'] ?>">
                                    <button type="submit" class="btn btn-primary">
                                        Setujui Servis Hub
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
