<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['warga']);
refresh_user_session($conn);

$id_warga = (int)$_SESSION['user']['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_tiket'])) {
    $id_tiket = (int)$_POST['id_tiket'];
    $action = $_POST['action'];

    if ($action === 'approve_cost') {
        $stmt = mysqli_prepare($conn, "UPDATE tiket_perbaikan SET persetujuan_warga = 1, status_tiket = 'Dikerjakan' WHERE id_tiket = ? AND id_pelapor = ? AND status_tiket = 'Menunggu Persetujuan'");
        mysqli_stmt_bind_param($stmt, 'ii', $id_tiket, $id_warga);
        mysqli_stmt_execute($stmt);
        $aff = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($aff > 0) {
            set_flash('success', 'Estimasi biaya disetujui. Teknisi memulai pengerjaan.');
        }
    } elseif ($action === 'reject_cost') {
        $stmt = mysqli_prepare($conn, "UPDATE tiket_perbaikan SET id_teknisi = NULL, diagnosis_teknisi = NULL, estimasi_biaya = 0.00, estimasi_hari = NULL, persetujuan_warga = 0, status_tiket = 'Menunggu Diagnosis' WHERE id_tiket = ? AND id_pelapor = ? AND status_tiket = 'Menunggu Persetujuan'");
        mysqli_stmt_bind_param($stmt, 'ii', $id_tiket, $id_warga);
        mysqli_stmt_execute($stmt);
        $aff = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($aff > 0) {
            set_flash('info', 'Estimasi ditolak. Tiket dikembalikan ke antrean teknisi terbuka.');
        }
    } elseif ($action === 'cancel_ticket') {
        $stmt = mysqli_prepare($conn, "UPDATE tiket_perbaikan SET status_tiket = 'Dibatalkan' WHERE id_tiket = ? AND id_pelapor = ? AND status_tiket IN ('Menunggu Diagnosis', 'Menunggu Persetujuan')");
        mysqli_stmt_bind_param($stmt, 'ii', $id_tiket, $id_warga);
        mysqli_stmt_execute($stmt);
        $aff = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($aff > 0) {
            set_flash('warning', 'Tiket perbaikan dibatalkan.');
        }
    } elseif ($action === 'confirm_done') {
        $stmt = mysqli_prepare($conn, "UPDATE tiket_perbaikan SET status_tiket = 'Selesai' WHERE id_tiket = ? AND id_pelapor = ? AND status_tiket = 'Siap Diambil'");
        mysqli_stmt_bind_param($stmt, 'ii', $id_tiket, $id_warga);
        mysqli_stmt_execute($stmt);
        $aff = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($aff > 0) {
            set_flash('success', 'Servis selesai dan tercatat pada Dashboard Dampak.');
        }
    }

    header('Location: ' . BASE_URL . '/perbaikan/tiket_saya.php');
    exit;
}

$sql = "SELECT t.*, k.nama_kategori, k.rata_rata_berat_kg, k.estimasi_harga_baru,
               tek.nama AS nama_teknisi, tek.no_hp AS hp_teknisi
        FROM tiket_perbaikan t
        JOIN kategori_alat k ON t.id_kategori = k.id_kategori
        LEFT JOIN users tek ON t.id_teknisi = tek.id_user
        WHERE t.id_pelapor = ?
        ORDER BY t.id_tiket DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id_warga);
mysqli_stmt_execute($stmt);
$res_tiket = mysqli_stmt_get_result($stmt);

function render_stepper_6_tahap(string $status): string {
    $steps = [
        1 => 'Lapor',
        2 => 'Diagnosis',
        3 => 'Setujui Biaya',
        4 => 'Dikerjakan',
        5 => 'Siap Diambil',
        6 => 'Selesai'
    ];

    if ($status === 'Dibatalkan') {
        return '<div class="pk-alert pk-alert--danger mb-2"><div>Tiket dibatalkan.</div></div>';
    }

    $current_stage = 2;
    $done_until = 1;
    switch ($status) {
        case 'Menunggu Diagnosis':
            $done_until = 1;
            $current_stage = 2;
            break;
        case 'Menunggu Persetujuan':
            $done_until = 2;
            $current_stage = 3;
            break;
        case 'Dikerjakan':
            $done_until = 3;
            $current_stage = 4;
            break;
        case 'Siap Diambil':
            $done_until = 4;
            $current_stage = 5;
            break;
        case 'Selesai':
            $done_until = 6;
            $current_stage = 0;
            break;
    }

    $html = '<ol class="pk-stepper" aria-label="Tahapan Perbaikan">';
    foreach ($steps as $num => $label) {
        $cls = '';
        if ($num <= $done_until) {
            $cls = 'is-done';
        } elseif ($num === $current_stage) {
            $cls = 'is-current';
        }
        $html .= '<li class="pk-step ' . $cls . '">'
               . '<span class="pk-step-num">' . $num . '</span>'
               . '<span>' . e($label) . '</span>'
               . '</li>';
    }
    $html .= '</ol>';
    return $html;
}

$page_title = 'Tiket Servis Saya';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Tiket Servis Saya</h1>
    <a href="<?= BASE_URL ?>/perbaikan/buat_tiket.php" class="btn btn-warm">
        <?= svg_icon('plus', 16) ?>
        <span>Ajukan Perbaikan</span>
    </a>
</div>

<?php if (mysqli_num_rows($res_tiket) === 0): ?>
    <div class="pk-empty-state">
        <div class="pk-empty-icon"><?= svg_icon('wrench', 22) ?></div>
        <h3>Belum Ada Tiket Servis</h3>
        <div class="mt-2">
            <a href="<?= BASE_URL ?>/perbaikan/buat_tiket.php" class="btn btn-warm">Ajukan Perbaikan</a>
        </div>
    </div>
<?php else: ?>
    <?php while ($t = mysqli_fetch_assoc($res_tiket)): ?>
        <article class="pk-card mb-4">
            <div class="pk-card-header">
                <div class="d-flex align-center gap-2 flex-wrap">
                    <span class="pk-badge pk-badge--primary"><?= e($t['kode_tiket']) ?></span>
                    <span class="pk-badge pk-badge--muted"><?= e($t['nama_kategori']) ?></span>
                    <span class="pk-badge pk-badge--muted"><?= e($t['metode_cek']) ?></span>
                </div>
                <div class="d-flex align-center gap-2">
                    <span class="text-muted" style="font-size:0.84rem;"><?= e(format_tanggal($t['created_at'])) ?></span>
                    <?= badge_status_tiket($t['status_tiket']) ?>
                </div>
            </div>

            <?= render_stepper_6_tahap($t['status_tiket']) ?>

            <div class="pk-grid pk-grid--2 mt-3">
                <div class="d-flex gap-3 align-start">
                    <img src="<?= e(get_gambar_tiket($t['foto_barang'], $t['nama_barang'])) ?>"
                         alt="<?= e($t['nama_barang']) ?>"
                         width="120"
                         height="84"
                         class="pk-thumb-ticket">
                    <div>
                        <h2 class="mb-1"><?= e($t['nama_barang']) ?></h2>
                        <div class="text-muted mb-2"><?= e($t['deskripsi_kerusakan']) ?></div>
                        <div style="font-size:0.85rem;">
                            <strong>Teknisi:</strong>
                            <?php if (!empty($t['nama_teknisi'])): ?>
                                <?= e($t['nama_teknisi']) ?> (<?= e($t['hp_teknisi']) ?>)
                            <?php else: ?>
                                <span class="text-muted">Menunggu klaim</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div>
                    <?php if ($t['status_tiket'] === 'Menunggu Persetujuan'): ?>
                        <div class="pk-card mb-0">
                            <h3 class="mb-2">Estimasi Teknisi</h3>
                            <div class="mb-2"><?= e($t['diagnosis_teknisi']) ?></div>
                            <div class="d-flex justify-between flex-wrap gap-2 mb-3">
                                <span>Biaya: <strong class="text-warm"><?= rupiah($t['estimasi_biaya']) ?></strong></span>
                                <span>Durasi: <strong><?= (int)$t['estimasi_hari'] ?> Hari</strong></span>
                            </div>

                            <div class="btn-group-wrap">
                                <form method="post" action="<?= BASE_URL ?>/perbaikan/tiket_saya.php">
                                    <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">
                                    <input type="hidden" name="action" value="approve_cost">
                                    <button type="submit" class="btn btn-secondary">
                                        <?= svg_icon('check-circle', 16) ?>
                                        <span>Setujui Biaya</span>
                                    </button>
                                </form>

                                <form method="post" action="<?= BASE_URL ?>/perbaikan/tiket_saya.php" onsubmit="return confirm('Tolak estimasi dan kembalikan tiket ke antrean terbuka?');">
                                    <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">
                                    <input type="hidden" name="action" value="reject_cost">
                                    <button type="submit" class="btn btn-muted">
                                        <span>Tolak Estimasi</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php elseif (!empty($t['diagnosis_teknisi'])): ?>
                        <div class="pk-guideline-box" style="white-space:normal;">
                            <div class="fw-bold mb-1">Diagnosis &amp; Biaya:</div>
                            <div class="mb-2"><?= e($t['diagnosis_teknisi']) ?></div>
                            <div class="d-flex justify-between flex-wrap gap-2">
                                <span>Biaya: <strong><?= rupiah($t['estimasi_biaya']) ?></strong></span>
                                <span>Durasi: <strong><?= (int)$t['estimasi_hari'] ?> Hari</strong></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="pk-guideline-box" style="white-space:normal;">
                            Menunggu diagnosis teknisi terverifikasi.
                        </div>
                    <?php endif; ?>

                    <div class="btn-group-wrap mt-3">
                        <?php if (in_array($t['status_tiket'], ['Menunggu Diagnosis', 'Menunggu Persetujuan'], true)): ?>
                            <form method="post" action="<?= BASE_URL ?>/perbaikan/tiket_saya.php" onsubmit="return confirm('Batalkan tiket perbaikan ini?');">
                                <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">
                                <input type="hidden" name="action" value="cancel_ticket">
                                <button type="submit" class="btn btn-danger">
                                    <?= svg_icon('x', 16) ?>
                                    <span>Batalkan Tiket</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($t['status_tiket'] === 'Siap Diambil'): ?>
                            <form method="post" action="<?= BASE_URL ?>/perbaikan/tiket_saya.php">
                                <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">
                                <input type="hidden" name="action" value="confirm_done">
                                <button type="submit" class="btn btn-primary">
                                    <?= svg_icon('check-circle', 16) ?>
                                    <span>Konfirmasi Selesai</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
    <?php endwhile; ?>
<?php endif; ?>

<?php
mysqli_stmt_close($stmt);
require_once ROOT_PATH . '/includes/footer.php';
?>
