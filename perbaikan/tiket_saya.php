<?php
/**
 * Halaman daftar tiket servis milik Warga.
 *
 * Tujuan : Menampilkan progres 6 tahap perbaikan dan menyediakan aksi warga
 *          (setujui/tolak estimasi, batalkan tiket, konfirmasi selesai).
 * Akses  : Warga (role 'warga').
 * Alur   : Aksi POST (dengan CSRF) -> update status tiket -> redirect. GET memuat
 *          tiket milik pelapor beserta nama teknisi dan merender stepper.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_role(['warga']);
refresh_user_session($conn);

$id_warga = (int)$_SESSION['user']['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_tiket']) && !verify_csrf_token()) {
    set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_tiket'])) {
    $id_tiket = (int)$_POST['id_tiket'];
    $action   = $_POST['action'];

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
        // Tolak estimasi: kosongkan diagnosis dan kembalikan tiket ke antrean terbuka.
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

$tiket_list = pk_stmt_all(
    $conn,
    "SELECT t.*, k.nama_kategori, k.rata_rata_berat_kg, k.estimasi_harga_baru,
            tek.nama AS nama_teknisi, tek.no_hp AS hp_teknisi
     FROM tiket_perbaikan t
     JOIN kategori_alat k ON t.id_kategori = k.id_kategori
     LEFT JOIN users tek ON t.id_teknisi = tek.id_user
     WHERE t.id_pelapor = ?
     ORDER BY t.id_tiket DESC",
    'i',
    [$id_warga]
);

/**
 * Memetakan status tiket ke metadata tahapan stepper 6 langkah.
 *
 * @param string $status Status tiket saat ini.
 * @return array{done_until: int, current_stage: int, summary: string}
 */
function get_tahap_meta_tiket(string $status): array {
    switch ($status) {
        case 'Menunggu Diagnosis':
            return ['done_until' => 1, 'current_stage' => 2, 'summary' => 'Tahap 2 dari 6 · Menunggu diagnosis'];
        case 'Menunggu Persetujuan':
            return ['done_until' => 2, 'current_stage' => 3, 'summary' => 'Tahap 3 dari 6 · Perlu persetujuan biaya'];
        case 'Dikerjakan':
            return ['done_until' => 3, 'current_stage' => 4, 'summary' => 'Tahap 4 dari 6 · Sedang dikerjakan'];
        case 'Siap Diambil':
            return ['done_until' => 4, 'current_stage' => 5, 'summary' => 'Tahap 5 dari 6 · Siap diambil'];
        case 'Selesai':
            return ['done_until' => 6, 'current_stage' => 0, 'summary' => '6 dari 6 tahap selesai'];
        default:
            return ['done_until' => 0, 'current_stage' => 0, 'summary' => 'Tiket dibatalkan'];
    }
}

/**
 * Merender markup stepper 6 tahap perbaikan berdasarkan status tiket.
 *
 * @param string $status Status tiket saat ini.
 * @return string Markup HTML <ol class="pk-stepper"> atau alert pembatalan.
 */
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
        return render_alert('danger', 'Tiket perbaikan dibatalkan.', false, '', 'mb-0');
    }

    $meta = get_tahap_meta_tiket($status);
    $done_until = $meta['done_until'];
    $current_stage = $meta['current_stage'];
    $is_warn_step = ($status === 'Menunggu Persetujuan');

    $html = '<ol class="pk-stepper" aria-label="Tahapan Perbaikan">';
    foreach ($steps as $num => $label) {
        $cls = 'is-upcoming';
        $state_text = 'Menunggu';
        $aria_current = '';

        if ($num <= $done_until) {
            $cls = 'is-done';
            $state_text = 'Selesai';
        } elseif ($num === $current_stage) {
            $cls = 'is-current' . ($is_warn_step ? ' is-warn' : '');
            $state_text = $is_warn_step ? 'Perlu Setuju' : 'Berjalan';
            $aria_current = ' aria-current="step"';
        }

        $next_done = ($num < 6 && $num < $done_until) || ($num === $done_until && $current_stage > 0) || ($done_until >= 6);
        $prev_done = ($num > 1 && ($num - 1) <= $done_until);
        $link_cls = ($prev_done ? ' has-prev-done' : '') . ($next_done ? ' has-next-done' : '');

        $html .= '<li class="pk-step ' . $cls . $link_cls . '"' . $aria_current . '>'
               . '<div class="pk-step-node">'
               . '<span class="pk-step-num" aria-hidden="true">' . $num . '</span>'
               . '</div>'
               . '<div class="pk-step-body">'
               . '<span class="pk-step-label">' . e($label) . '</span>'
               . '<span class="pk-step-state">' . e($state_text) . '</span>'
               . '</div>'
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

<?php if (count($tiket_list) === 0): ?>
    <div class="pk-empty-state">
        <div class="pk-empty-icon"><?= svg_icon('wrench', 22) ?></div>
        <h3>Belum Ada Tiket Servis</h3>
        <div class="mt-2">
            <a href="<?= BASE_URL ?>/perbaikan/buat_tiket.php" class="btn btn-warm">Ajukan Perbaikan</a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($tiket_list as $t): ?>
        <?php $tahap_meta = get_tahap_meta_tiket((string)$t['status_tiket']); ?>
        <article class="pk-card pk-ticket-card mb-4">
            <div class="pk-ticket-hero">
                <div class="pk-ticket-media">
                    <img src="<?= e(get_gambar_tiket($t['foto_barang'], $t['nama_barang'])) ?>"
                         alt="<?= e($t['nama_barang']) ?>"
                         width="188"
                         height="168"
                         class="pk-ticket-thumb"
                         loading="lazy">
                </div>

                <div class="pk-ticket-summary">
                    <div class="pk-ticket-head">
                        <div class="d-flex align-center gap-2 flex-wrap">
                            <span class="pk-code-tag"><?= e($t['kode_tiket']) ?></span>
                            <span class="pk-tag"><?= e($t['nama_kategori']) ?></span>
                            <span class="pk-tag"><?= e($t['metode_cek']) ?></span>
                        </div>
                        <div class="d-flex align-center gap-2 flex-wrap">
                            <span class="pk-ticket-date"><?= e(format_tanggal($t['created_at'])) ?></span>
                            <?= badge_status_tiket($t['status_tiket']) ?>
                        </div>
                    </div>

                    <h2 class="pk-ticket-title"><?= e($t['nama_barang']) ?></h2>
                    <p class="pk-ticket-desc"><?= e($t['deskripsi_kerusakan']) ?></p>

                    <div class="pk-ticket-tech">
                        <strong>Teknisi:</strong>
                        <?php if (!empty($t['nama_teknisi'])): ?>
                            <span><?= e(format_nama_pengguna((string)$t['nama_teknisi'])) ?></span>
                            <?php if (!empty($t['hp_teknisi'])): ?>
                                <span class="text-muted">(<?= e($t['hp_teknisi']) ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Menunggu klaim</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="pk-ticket-progress">
                <div class="pk-ticket-progress-head">
                    <span class="pk-ticket-progress-title">Tahapan Perbaikan</span>
                    <span class="pk-ticket-progress-meta"><?= e($tahap_meta['summary']) ?></span>
                </div>
                <?= render_stepper_6_tahap($t['status_tiket']) ?>
            </div>

            <div class="pk-ticket-footer">
                <?php if ($t['status_tiket'] === 'Menunggu Persetujuan'): ?>
                    <div class="pk-card pk-card--notice mb-0">
                        <div class="pk-ticket-diag-head">
                            <h3>Estimasi Biaya Teknisi</h3>
                            <div class="pk-ticket-diag-stats">
                                <span>Biaya: <strong class="text-warm"><?= rupiah($t['estimasi_biaya']) ?></strong></span>
                                <span>Durasi: <strong><?= (int)$t['estimasi_hari'] ?> Hari</strong></span>
                            </div>
                        </div>
                        <p class="mb-3"><?= e($t['diagnosis_teknisi']) ?></p>

                        <div class="btn-group-wrap">
                            <form method="post" action="<?= BASE_URL ?>/perbaikan/tiket_saya.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">
                                <input type="hidden" name="action" value="approve_cost">
                                <button type="submit" class="btn btn-secondary">
                                    <?= svg_icon('check-circle', 16) ?>
                                    <span>Setujui Biaya</span>
                                </button>
                            </form>

                            <form method="post" action="<?= BASE_URL ?>/perbaikan/tiket_saya.php" data-confirm="Tolak estimasi biaya dan kembalikan tiket ke antrean?" data-confirm-title="Tolak Estimasi" data-confirm-btn="Tolak" data-confirm-tone="danger">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">
                                <input type="hidden" name="action" value="reject_cost">
                                <button type="submit" class="btn btn-muted">
                                    <span>Tolak Estimasi</span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php elseif (!empty($t['diagnosis_teknisi'])): ?>
                    <div class="pk-guideline-box pk-ticket-diag-box" style="white-space:normal;">
                        <div class="pk-ticket-diag-head">
                            <span class="fw-bold">Diagnosis &amp; Biaya</span>
                            <div class="pk-ticket-diag-stats">
                                <span>Biaya: <strong><?= rupiah($t['estimasi_biaya']) ?></strong></span>
                                <span>Durasi: <strong><?= (int)$t['estimasi_hari'] ?> Hari</strong></span>
                            </div>
                        </div>
                        <div><?= e($t['diagnosis_teknisi']) ?></div>
                    </div>
                <?php else: ?>
                    <div class="pk-guideline-box" style="white-space:normal;">
                        Menunggu diagnosis teknisi terverifikasi.
                    </div>
                <?php endif; ?>

                <?php $can_cancel = in_array($t['status_tiket'], ['Menunggu Diagnosis', 'Menunggu Persetujuan'], true); ?>
                <?php $can_confirm = ($t['status_tiket'] === 'Siap Diambil'); ?>
                <?php if ($can_cancel || $can_confirm): ?>
                    <div class="btn-group-wrap mt-3">
                        <?php if ($can_cancel): ?>
                            <form method="post" action="<?= BASE_URL ?>/perbaikan/tiket_saya.php" data-confirm="Batalkan tiket perbaikan <?= e($t['kode_tiket']) ?>?" data-confirm-title="Batalkan Tiket" data-confirm-btn="Batalkan" data-confirm-tone="danger">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">
                                <input type="hidden" name="action" value="cancel_ticket">
                                <button type="submit" class="btn btn-danger">
                                    <?= svg_icon('x', 16) ?>
                                    <span>Batalkan Tiket</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($can_confirm): ?>
                            <form method="post" action="<?= BASE_URL ?>/perbaikan/tiket_saya.php" data-confirm="Konfirmasi perbaikan <?= e($t['kode_tiket']) ?> sudah diterima?" data-confirm-title="Konfirmasi Selesai" data-confirm-btn="Selesai" data-confirm-tone="primary">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">
                                <input type="hidden" name="action" value="confirm_done">
                                <button type="submit" class="btn btn-primary">
                                    <?= svg_icon('check-circle', 16) ?>
                                    <span>Konfirmasi Selesai</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
