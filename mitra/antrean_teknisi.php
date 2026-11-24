<?php
/**
 * Halaman meja kerja Mitra Teknisi.
 *
 * Tujuan : Menampilkan antrean tiket terbuka, memungkinkan teknisi terverifikasi
 *          mengklaim tiket & mengirim diagnosis, serta menandai pekerjaan siap diambil.
 * Akses  : Mitra Teknisi Perbaikan (role 'mitra' + is_teknisi).
 * Alur   : Aksi POST (claim_and_diagnose/mark_ready) -> update tiket -> flash +
 *          redirect. GET memuat antrean terbuka dan pekerjaan milik teknisi.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_role(['mitra']);
refresh_user_session($conn);

$user        = $_SESSION['user'];
if (empty($user['is_teknisi'])) {
    set_flash('warning', 'Meja Kerja Teknisi khusus untuk Mitra Teknisi Perbaikan.');
    header('Location: ' . BASE_URL . '/auth/profil.php');
    exit;
}

$id_teknisi  = (int)$user['id_user'];
$is_verified = !empty($user['is_verified_teknisi']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_verified && !verify_csrf_token()) {
    set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_verified) {
    $action   = $_POST['action'] ?? '';
    $id_tiket = (int)($_POST['id_tiket'] ?? 0);

    if ($action === 'claim_and_diagnose') {
        $diagnosis_teknisi = trim($_POST['diagnosis_teknisi'] ?? '');
        $estimasi_biaya    = (float)($_POST['estimasi_biaya'] ?? -1);
        $estimasi_hari     = (int)($_POST['estimasi_hari'] ?? 0);

        if ($diagnosis_teknisi === '' || $estimasi_biaya < 0 || $estimasi_hari <= 0) {
            set_flash('warning', 'Isi diagnosis, estimasi biaya, dan estimasi hari pengerjaan.');
        } else {
            // Klaim hanya berhasil bila tiket masih terbuka dan belum dipegang teknisi lain.
            $stmt = mysqli_prepare($conn, "UPDATE tiket_perbaikan
                SET id_teknisi = ?, diagnosis_teknisi = ?, estimasi_biaya = ?, estimasi_hari = ?, status_tiket = 'Menunggu Persetujuan'
                WHERE id_tiket = ? AND id_teknisi IS NULL AND status_tiket = 'Menunggu Diagnosis'");
            mysqli_stmt_bind_param($stmt, 'isdii', $id_teknisi, $diagnosis_teknisi, $estimasi_biaya, $estimasi_hari, $id_tiket);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected > 0) {
                set_flash('success', 'Tiket diklaim dan estimasi biaya telah dikirim.');
            } else {
                set_flash('danger', 'Tiket sudah diklaim teknisi lain.');
            }
        }
        header('Location: ' . BASE_URL . '/mitra/antrean_teknisi.php');
        exit;
    }

    if ($action === 'mark_ready') {
        // Hanya tiket milik teknisi ini yang sudah disetujui warga boleh ditandai siap.
        $stmt = mysqli_prepare($conn, "UPDATE tiket_perbaikan
            SET status_tiket = 'Siap Diambil'
            WHERE id_tiket = ? AND id_teknisi = ? AND status_tiket = 'Dikerjakan' AND persetujuan_warga = 1");
        mysqli_stmt_bind_param($stmt, 'ii', $id_tiket, $id_teknisi);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected > 0) {
            set_flash('success', 'Status diperbarui menjadi Siap Diambil.');
        } else {
            set_flash('danger', 'Pastikan biaya sudah disetujui warga dan status tiket Dikerjakan.');
        }
        header('Location: ' . BASE_URL . '/mitra/antrean_teknisi.php?tab=myjobs');
        exit;
    }
}

$open_tickets = [];
$my_jobs      = [];

if ($is_verified) {
    $open_tickets = pk_stmt_all(
        $conn,
        "SELECT t.*, k.nama_kategori, u.nama AS nama_pelapor, u.no_hp, u.alamat
         FROM tiket_perbaikan t
         JOIN kategori_alat k ON t.id_kategori = k.id_kategori
         JOIN users u ON t.id_pelapor = u.id_user
         WHERE t.status_tiket = 'Menunggu Diagnosis' AND t.id_teknisi IS NULL
         ORDER BY t.id_tiket ASC"
    );

    $my_jobs = pk_stmt_all(
        $conn,
        "SELECT t.*, k.nama_kategori, u.nama AS nama_pelapor, u.no_hp, u.alamat
         FROM tiket_perbaikan t
         JOIN kategori_alat k ON t.id_kategori = k.id_kategori
         JOIN users u ON t.id_pelapor = u.id_user
         WHERE t.id_teknisi = ?
         ORDER BY FIELD(t.status_tiket, 'Dikerjakan', 'Menunggu Persetujuan', 'Siap Diambil', 'Selesai', 'Dibatalkan'), t.id_tiket DESC",
        'i',
        [$id_teknisi]
    );
}

$active_tab = ($_GET['tab'] ?? 'open') === 'myjobs' ? 'myjobs' : 'open';

$page_title = 'Meja Kerja Teknisi';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Meja Kerja Teknisi</h1>
</div>

<?php if (!$is_verified): ?>
    <section class="pk-card" aria-labelledby="unverified-heading">
        <div class="d-flex align-center gap-2 mb-3">
            <?= svg_icon('alert-triangle', 20) ?>
            <h2 id="unverified-heading" class="mb-0">Menunggu Verifikasi Admin Hub</h2>
        </div>
        <a href="<?= BASE_URL ?>/auth/profil.php" class="btn btn-primary">Lihat Profil</a>
    </section>
<?php else: ?>
    <?php
    $cnt_open = count($open_tickets);
    $cnt_jobs = count($my_jobs);
    ?>
    <div class="pk-tabs" role="tablist" aria-label="Meja Kerja Teknisi">
        <button type="button"
            class="pk-tab-btn <?= $active_tab === 'open' ? 'active' : '' ?>"
            role="tab"
            aria-selected="<?= $active_tab === 'open' ? 'true' : 'false' ?>"
            data-tab-target="#tabOpenTickets">
            <?= svg_icon('clipboard', 16) ?>
            <span>Antrean Terbuka (<?= $cnt_open ?>)</span>
        </button>

        <button type="button"
            class="pk-tab-btn <?= $active_tab === 'myjobs' ? 'active' : '' ?>"
            role="tab"
            aria-selected="<?= $active_tab === 'myjobs' ? 'true' : 'false' ?>"
            data-tab-target="#tabMyJobs">
            <?= svg_icon('wrench', 16) ?>
            <span>Pekerjaan Saya (<?= $cnt_jobs ?>)</span>
        </button>
    </div>

    <div id="tabOpenTickets" class="pk-tab-panel <?= $active_tab === 'open' ? 'active' : '' ?>" role="tabpanel">
        <?php if ($cnt_open === 0): ?>
            <div class="pk-empty-state">
                <div class="pk-empty-icon"><?= svg_icon('check-circle', 22) ?></div>
                <h3>Tidak Ada Antrean Tiket Terbuka</h3>
            </div>
        <?php else: ?>
            <?php foreach ($open_tickets as $t): ?>
                <article class="pk-card mb-4">
                    <div class="pk-card-header">
                        <div class="d-flex align-center gap-2 flex-wrap">
                            <span class="pk-code-tag"><?= e($t['kode_tiket']) ?></span>
                            <span class="pk-tag"><?= e($t['nama_kategori']) ?></span>
                            <span class="pk-tag"><?= e($t['metode_cek']) ?></span>
                            <?php if (!empty($t['id_alat_hub'])): ?>
                                <span class="pk-tag pk-tag--accent">Inventaris Hub</span>
                            <?php endif; ?>
                        </div>
                        <?= badge_status_tiket($t['status_tiket']) ?>
                    </div>

                    <div class="pk-grid pk-grid--2">
                        <div>
                            <div class="d-flex gap-3 align-start mb-3">
                                <img src="<?= e(get_gambar_tiket($t['foto_barang'], $t['nama_barang'])) ?>"
                                    alt="<?= e($t['nama_barang']) ?>"
                                    width="112"
                                    height="80"
                                    class="pk-thumb-ticket">
                                <div>
                                    <h2 class="mb-1"><?= e($t['nama_barang']) ?></h2>
                                    <div class="text-muted"><?= e($t['deskripsi_kerusakan']) ?></div>
                                </div>
                            </div>

                            <ul class="pk-tool-meta mb-0">
                                <li>
                                    <span>Pelapor</span>
                                    <strong><?= e($t['nama_pelapor']) ?> (<?= e($t['no_hp']) ?>)</strong>
                                </li>
                                <li>
                                    <span>Lokasi</span>
                                    <strong><?= e($t['metode_cek']) ?> &bull; <?= e($t['alamat']) ?></strong>
                                </li>
                            </ul>
                        </div>

                        <div class="pk-card mb-0">
                            <h3 class="mb-2">Klaim &amp; Kirim Diagnosis</h3>
                            <form method="post" action="<?= BASE_URL ?>/mitra/antrean_teknisi.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="claim_and_diagnose">
                                <input type="hidden" name="id_tiket" value="<?= (int)$t['id_tiket'] ?>">

                                <div class="form-group">
                                    <label for="diag_<?= (int)$t['id_tiket'] ?>" class="form-label">Diagnosis &amp; Solusi</label>
                                    <textarea id="diag_<?= (int)$t['id_tiket'] ?>" name="diagnosis_teknisi" class="form-control" rows="3" placeholder="Komponen rusak dan tindakan perbaikan..." required></textarea>
                                </div>

                                <div class="pk-grid pk-grid--2">
                                    <div class="form-group">
                                        <label for="biaya_<?= (int)$t['id_tiket'] ?>" class="form-label">Estimasi Biaya (Rp)</label>
                                        <input type="number" step="1000" min="0" id="biaya_<?= (int)$t['id_tiket'] ?>" name="estimasi_biaya" class="form-control" placeholder="45000" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="hari_<?= (int)$t['id_tiket'] ?>" class="form-label">Durasi (Hari)</label>
                                        <input type="number" min="1" max="30" id="hari_<?= (int)$t['id_tiket'] ?>" name="estimasi_hari" class="form-control" value="1" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <?= svg_icon('check-circle', 16) ?>
                                    <span>Klaim &amp; Kirim Estimasi</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="tabMyJobs" class="pk-tab-panel <?= $active_tab === 'myjobs' ? 'active' : '' ?>" role="tabpanel">
        <?php if ($cnt_jobs === 0): ?>
            <div class="pk-empty-state">
                <div class="pk-empty-icon"><?= svg_icon('wrench', 22) ?></div>
                <h3>Belum Ada Pekerjaan Servis</h3>
            </div>
        <?php else: ?>
            <section class="pk-table-card" aria-label="Daftar Pekerjaan Servis">
                <div class="pk-table-scroll">
                    <table class="pk-table">
                        <thead>
                            <tr>
                                <th>Kode &amp; Barang</th>
                                <th>Pelapor</th>
                                <th>Diagnosis &amp; Biaya</th>
                                <th>Persetujuan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_jobs as $job): ?>
                                <?php $has_job_action = ($job['status_tiket'] === 'Dikerjakan' && (int)$job['persetujuan_warga'] === 1); ?>
                                <tr>
                                    <td class="pk-td-head">
                                        <div class="pk-td-head-row">
                                            <div class="pk-td-head-main">
                                                <span class="pk-code-tag"><?= e($job['kode_tiket']) ?></span>
                                                <div class="mt-1"><strong><?= e($job['nama_barang']) ?></strong></div>
                                                <?php if (!empty($job['id_alat_hub'])): ?>
                                                    <div class="mt-1"><span class="pk-tag pk-tag--accent">Inventaris Hub</span></div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="pk-mobile-badge"><?= badge_status_tiket($job['status_tiket']) ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Pelapor">
                                        <strong><?= e($job['nama_pelapor']) ?></strong><br>
                                        <span class="pk-td-sub"><?= e($job['no_hp']) ?> &bull; <?= e($job['metode_cek']) ?></span>
                                    </td>
                                    <td data-label="Diagnosis">
                                        <div><?= e($job['diagnosis_teknisi']) ?></div>
                                        <div class="mt-1"><strong><?= rupiah($job['estimasi_biaya']) ?></strong> (<?= (int)$job['estimasi_hari'] ?> hr)</div>
                                    </td>
                                    <td data-label="Setuju">
                                        <?php if ((int)$job['persetujuan_warga'] === 1): ?>
                                            <span class="pk-badge pk-badge--success">Disetujui</span>
                                        <?php else: ?>
                                            <span class="pk-badge pk-badge--warning">Menunggu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pk-desktop-cell" data-label="Status"><?= badge_status_tiket($job['status_tiket']) ?></td>
                                    <td class="pk-td-actions<?= $has_job_action ? '' : ' is-empty' ?>">
                                        <?php if ($job['status_tiket'] === 'Dikerjakan' && (int)$job['persetujuan_warga'] === 1): ?>
                                            <form method="post" action="<?= BASE_URL ?>/mitra/antrean_teknisi.php" data-confirm="Tandai perbaikan <?= e($job['kode_tiket']) ?> siap diambil?" data-confirm-title="Siap Diambil" data-confirm-btn="Tandai Siap" data-confirm-tone="ok">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="mark_ready">
                                                <input type="hidden" name="id_tiket" value="<?= (int)$job['id_tiket'] ?>">
                                                <button type="submit" class="btn btn-secondary">
                                                    <?= svg_icon('check-circle', 16) ?>
                                                    <span>Siap Diambil</span>
                                                </button>
                                            </form>
                                        <?php elseif ($job['status_tiket'] === 'Menunggu Persetujuan'): ?>
                                            <span class="text-muted" style="font-size:0.84rem;">Menunggu persetujuan biaya</span>
                                        <?php elseif ($job['status_tiket'] === 'Siap Diambil'): ?>
                                            <span class="text-secondary fw-bold" style="font-size:0.84rem;">Menunggu pengambilan</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>