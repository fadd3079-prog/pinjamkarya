<?php
/**
 * Halaman log riwayat inspeksi fisik aset (perbandingan Awal & Akhir).
 *
 * Tujuan : Menampilkan catatan pemeriksaan fisik serah-terima awal dan
 *          pengembalian akhir secara berdampingan per kode booking.
 * Akses  : Admin Hub (seluruh aset) dan Mitra Kontributor (khusus aset miliknya).
 * Alur   : Validasi peran -> filter opsional id_peminjaman -> kelompokkan log
 *          Awal & Akhir per transaksi -> tampilkan tabel komparasi.
 */

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

$sql = "SELECT i.*, p.kode_booking, p.tgl_mulai, p.tgl_selesai, p.status_peminjaman,
               a.nama_alat, a.id_pemilik, w.nama AS nama_peminjam, pt.nama AS nama_petugas
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

$logs_by_booking = [];
while ($row = mysqli_fetch_assoc($res_log)) {
    $pid = (int)$row['id_peminjaman'];
    if (!isset($logs_by_booking[$pid])) {
        $logs_by_booking[$pid] = [
            'id_peminjaman'     => $pid,
            'kode_booking'      => $row['kode_booking'],
            'nama_alat'         => $row['nama_alat'],
            'nama_peminjam'     => $row['nama_peminjam'],
            'tgl_mulai'         => $row['tgl_mulai'],
            'tgl_selesai'       => $row['tgl_selesai'],
            'status_peminjaman' => $row['status_peminjaman'],
            'awal'              => null,
            'akhir'             => null,
        ];
    }
    $stage = ($row['jenis_inspeksi'] === 'Akhir') ? 'akhir' : 'awal';
    if ($logs_by_booking[$pid][$stage] === null) {
        $logs_by_booking[$pid][$stage] = $row;
    }
}

$kondisi_rank = [
    'Baik'         => 0,
    'Rusak Ringan' => 1,
    'Rusak Berat'  => 2,
];

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
    <?php if (empty($logs_by_booking)): ?>
        <div class="pk-empty-state">
            <div class="pk-empty-icon"><?= svg_icon('clipboard', 22) ?></div>
            <h3>Belum Ada Catatan Inspeksi</h3>
        </div>
    <?php else: ?>
        <div class="pk-table-scroll">
            <table class="pk-table pk-table--inspeksi">
                <thead>
                    <tr>
                        <th style="width: 28%;">Booking &amp; Alat</th>
                        <th>Perbandingan Kondisi (Awal &rarr; Akhir)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs_by_booking as $log): ?>
                        <?php
                        $awal = $log['awal'];
                        $akhir = $log['akhir'];
                        $latest = $akhir ?: $awal;
                        $rank_awal = $awal ? ($kondisi_rank[$awal['kondisi_fisik']] ?? 0) : null;
                        $rank_akhir = $akhir ? ($kondisi_rank[$akhir['kondisi_fisik']] ?? 0) : null;
                        $is_degraded = ($rank_awal !== null && $rank_akhir !== null && $rank_akhir > $rank_awal);
                        $is_changed = ($awal && $akhir && $awal['kondisi_fisik'] !== $akhir['kondisi_fisik']);

                        if ($awal && $akhir) {
                            if ($is_degraded) {
                                $summary_label = 'Kondisi menurun: ' . $awal['kondisi_fisik'] . ' -> ' . $akhir['kondisi_fisik'];
                                $summary_icon = 'alert-triangle';
                            } elseif ($is_changed) {
                                $summary_label = 'Kondisi berubah: ' . $awal['kondisi_fisik'] . ' -> ' . $akhir['kondisi_fisik'];
                                $summary_icon = 'info';
                            } else {
                                $summary_label = 'Kondisi tetap ' . $akhir['kondisi_fisik'];
                                $summary_icon = 'check-circle';
                            }
                        } elseif ($awal) {
                            $summary_label = 'Menunggu inspeksi pengembalian';
                            $summary_icon = 'clock';
                        } else {
                            $summary_label = 'Hanya inspeksi akhir';
                            $summary_icon = 'info';
                        }
                        ?>
                        <tr>
                            <td class="pk-td-head">
                                <div class="pk-td-head-row">
                                    <div class="pk-td-head-main">
                                        <span class="pk-code-tag"><?= e($log['kode_booking']) ?></span>
                                        <div class="mt-1"><strong><?= e($log['nama_alat']) ?></strong></div>
                                        <div class="pk-td-sub mt-1">Peminjam: <?= e($log['nama_peminjam']) ?></div>
                                        <div class="pk-td-sub"><?= e(format_tanggal($log['tgl_mulai'])) ?> &ndash; <?= e(format_tanggal($log['tgl_selesai'])) ?></div>
                                        <div class="pk-inspect-summary<?= $is_degraded ? ' is-changed' : '' ?> mt-2">
                                            <?= svg_icon($summary_icon, 13) ?>
                                            <span><?= e($summary_label) ?></span>
                                        </div>
                                    </div>
                                    <?php if ($latest): ?>
                                        <span class="pk-mobile-badge">
                                            <?= badge_kondisi_fisik($latest['kondisi_fisik']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="pk-td-compare">
                                <div class="pk-inspect-compare">
                                    <?php if ($awal): ?>
                                        <div class="pk-inspect-pane pk-inspect-pane--awal">
                                            <div class="pk-inspect-pane-head">
                                                <span class="pk-inspect-stage">
                                                    <span class="pk-inspect-step-dot" aria-hidden="true"></span>
                                                    <span>Awal (Serah) &bull; #<?= (int)$awal['id_inspeksi'] ?></span>
                                                </span>
                                                <?= badge_kondisi_fisik($awal['kondisi_fisik']) ?>
                                            </div>
                                            <p class="pk-inspect-note"><?= e($awal['catatan_inspeksi']) ?></p>
                                            <div class="pk-inspect-meta">
                                                <span>Petugas: <?= e($awal['nama_petugas']) ?></span>
                                                <span><?= e(format_tanggal($awal['waktu_inspeksi'])) ?>, <?= e(date('H:i', strtotime($awal['waktu_inspeksi']))) ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="pk-inspect-pane pk-inspect-pane--empty">
                                            <span class="pk-inspect-stage">Awal (Serah)</span>
                                            <p class="pk-inspect-note text-muted">Data inspeksi awal tidak tercatat.</p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="pk-inspect-arrow<?= $is_degraded ? ' pk-inspect-arrow--changed' : '' ?>" aria-hidden="true">
                                        <?= svg_icon('arrow-right', 16) ?>
                                    </div>

                                    <?php if ($akhir): ?>
                                        <div class="pk-inspect-pane pk-inspect-pane--akhir<?= $is_degraded ? ' pk-inspect-pane--changed' : '' ?>">
                                            <div class="pk-inspect-pane-head">
                                                <span class="pk-inspect-stage">
                                                    <span class="pk-inspect-step-dot" aria-hidden="true"></span>
                                                    <span>Akhir (Kembali) &bull; #<?= (int)$akhir['id_inspeksi'] ?></span>
                                                </span>
                                                <?= badge_kondisi_fisik($akhir['kondisi_fisik']) ?>
                                            </div>
                                            <p class="pk-inspect-note"><?= e($akhir['catatan_inspeksi']) ?></p>
                                            <div class="pk-inspect-meta">
                                                <span>Petugas: <?= e($akhir['nama_petugas']) ?></span>
                                                <span><?= e(format_tanggal($akhir['waktu_inspeksi'])) ?>, <?= e(date('H:i', strtotime($akhir['waktu_inspeksi']))) ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="pk-inspect-pane pk-inspect-pane--empty">
                                            <span class="pk-inspect-stage">Akhir (Kembali)</span>
                                            <p class="pk-inspect-note text-muted">Menunggu inspeksi pengembalian.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
mysqli_stmt_close($stmt);
require_once ROOT_PATH . '/includes/footer.php';
?>
