<?php
/**
 * Halaman detail unit perkakas.
 *
 * Tujuan : Menampilkan spesifikasi, panduan keselamatan, status, dan jadwal
 *          peminjaman aktif sebuah alat, serta tautan pengajuan peminjaman.
 * Akses  : Publik (warga maupun tamu).
 * Alur   : Validasi ID -> muat alat & jadwal terisi -> susun URL reservasi ->
 *          tampilkan detail atau redirect bila alat tidak ditemukan.
 */

require_once dirname(__DIR__) . '/config/app.php';

bersihkan_ghost_booking($conn);

$id_alat = (int)($_GET['id'] ?? ($_GET['id_alat'] ?? 0));
if ($id_alat <= 0) {
    set_flash('warning', 'Pilih unit perkakas terlebih dahulu.');
    header('Location: ' . BASE_URL . '/katalog/index.php');
    exit;
}

$alat = pk_stmt_one(
    $conn,
    "SELECT a.*, k.nama_kategori, k.rata_rata_berat_kg,
            a.panduan_keselamatan AS panduan_alat,
            k.panduan_keselamatan AS panduan_kategori,
            u.nama AS nama_pemilik, u.role AS role_pemilik
     FROM aset_alat a
     JOIN kategori_alat k ON a.id_kategori = k.id_kategori
     JOIN users u ON a.id_pemilik = u.id_user
     WHERE a.id_alat = ? LIMIT 1",
    'i',
    [$id_alat]
);

if (empty($alat)) {
    set_flash('danger', 'Unit perkakas tidak ditemukan.');
    header('Location: ' . BASE_URL . '/katalog/index.php');
    exit;
}

$is_tersedia = ($alat['status_alat'] === 'Tersedia');
$panduan_info = pk_get_panduan_keselamatan($alat);

// Ambil booking aktif yang belum selesai sebagai informasi jadwal terisi.
$jadwal_terisi = pk_stmt_all(
    $conn,
    "SELECT p.kode_booking, p.tgl_mulai, p.tgl_selesai, p.status_peminjaman, u.nama AS nama_peminjam
     FROM peminjaman p
     JOIN users u ON p.id_warga = u.id_user
     WHERE p.id_alat = ?
       AND p.status_peminjaman IN ('Dipesan', 'Dipinjam')
       AND p.tgl_selesai >= CURDATE()
     ORDER BY p.tgl_mulai ASC",
    'i',
    [$id_alat]
);

$tgl_mulai_q   = trim($_GET['tgl_mulai'] ?? '');
$tgl_selesai_q = trim($_GET['tgl_selesai'] ?? '');
$reservasi_url = BASE_URL . '/reservasi/reservasi.php?id_alat=' . (int)$alat['id_alat'];
if ($tgl_mulai_q !== '' && $tgl_selesai_q !== '') {
    $reservasi_url .= '&tgl_mulai=' . rawurlencode($tgl_mulai_q) . '&tgl_selesai=' . rawurlencode($tgl_selesai_q);
}

$page_title = $alat['nama_alat'];
$public_layout = !isset($_SESSION['user']);
$public_nav_active = 'katalog';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1><?= e($alat['nama_alat']) ?></h1>
    <a href="<?= BASE_URL ?>/katalog/index.php" class="btn btn-muted">Kembali</a>
</div>

<div class="pk-grid pk-grid--2">
    <div>
        <section class="pk-card" aria-labelledby="spesifikasi-heading">
            <div class="pk-tool-media pk-tool-media--framed<?= !$is_tersedia ? ' is-unavailable' : '' ?> mb-3">
                <img src="<?= e(get_gambar_alat($alat['foto_alat'], $alat['nama_alat'])) ?>"
                     alt="<?= e($alat['nama_alat']) ?>"
                     width="640"
                     height="400">
                <div class="pk-tool-badges">
                    <span class="pk-tag"><?= e($alat['nama_kategori']) ?></span>
                    <div class="pk-td-val">
                        <?= badge_status_katalog($alat['status_alat']) ?>
                    </div>
                </div>
            </div>

            <h2 id="spesifikasi-heading" class="pk-card-title mb-2">Spesifikasi</h2>
            <p class="text-muted"><?= e($alat['deskripsi']) ?></p>

            <ul class="pk-tool-meta mb-3">
                <li>
                    <span>Status</span>
                    <?= badge_status_katalog($alat['status_alat']) ?>
                </li>
                <li>
                    <span>Pemilik</span>
                    <strong><?= $alat['role_pemilik'] === 'admin' ? 'Inventaris Hub' : e($alat['nama_pemilik']) ?></strong>
                </li>
                <li>
                    <span>Maks. Pinjam</span>
                    <strong><?= (int)$alat['max_hari_pinjam'] ?> Hari</strong>
                </li>
                <li>
                    <span>Skor Minimal</span>
                    <strong><?= (int)$alat['min_skor'] ?> Poin</strong>
                </li>
                <li>
                    <span>Biaya Rawat</span>
                    <?php if ((float)$alat['biaya_perawatan'] <= 0): ?>
                        <strong class="text-secondary">Gratis</strong>
                    <?php else: ?>
                        <strong><?= rupiah($alat['biaya_perawatan']) ?></strong>
                    <?php endif; ?>
                </li>
                <li>
                    <span>Harga Baru</span>
                    <strong><?= rupiah($alat['harga_beli_baru']) ?></strong>
                </li>
            </ul>

            <?php if (!$is_tersedia): ?>
                <?php
                $msg_status = $alat['status_alat'] === 'Perawatan'
                    ? 'Unit sedang diperbaiki dan belum dapat dipesan.'
                    : ($alat['status_alat'] === 'Dipinjam'
                        ? 'Unit sedang dipinjam hingga jadwal berakhir.'
                        : 'Unit belum tersedia untuk dipinjam saat ini.');
                ?>
                <?= render_alert('warning', $msg_status, false, '', 'mb-2') ?>
                <button type="button" class="btn btn-muted w-100" disabled>Tidak Tersedia</button>
            <?php else: ?>
                <a href="<?= e($reservasi_url) ?>" class="btn btn-primary w-100">
                    <?= svg_icon('calendar', 16) ?>
                    <span>Ajukan Peminjaman</span>
                </a>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="pk-card" aria-labelledby="sop-heading">
            <div class="pk-card-header">
                <h2 id="sop-heading" class="pk-card-title">
                    <?= svg_icon('shield', 18) ?>
                    <span>Panduan Keselamatan</span>
                </h2>
                <span class="pk-tag"><?= $panduan_info['is_khusus'] ? 'Khusus Alat' : 'Umum Kategori' ?></span>
            </div>
            <div class="pk-guideline-box"><?= e($panduan_info['teks']) ?></div>
        </section>

        <section class="pk-table-card" aria-labelledby="jadwal-heading">
            <div class="pk-table-card-head">
                <h2 id="jadwal-heading" class="pk-card-title">
                    <?= svg_icon('calendar', 18) ?>
                    <span>Jadwal Terisi</span>
                </h2>
            </div>

            <?php if (empty($jadwal_terisi)): ?>
                <div class="d-flex align-center gap-2 fw-bold text-secondary" style="padding:18px 20px;">
                    <?= svg_icon('check-circle', 18) ?>
                    <span>Semua tanggal mulai hari ini tersedia.</span>
                </div>
            <?php else: ?>
                <div class="pk-table-scroll">
                    <table class="pk-table">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Mulai</th>
                                <th>Selesai</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal_terisi as $jdw): ?>
                                <tr>
                                    <td class="pk-td-head">
                                        <div class="pk-td-head-row">
                                            <strong class="pk-nowrap"><?= e($jdw['kode_booking']) ?></strong>
                                            <span class="pk-mobile-badge"><?= badge_status_pinjam($jdw['status_peminjaman']) ?></span>
                                        </div>
                                    </td>
                                    <td class="pk-nowrap" data-label="Mulai"><?= e(format_tanggal($jdw['tgl_mulai'])) ?></td>
                                    <td class="pk-nowrap" data-label="Selesai"><?= e(format_tanggal($jdw['tgl_selesai'])) ?></td>
                                    <td class="pk-desktop-cell" data-label="Status"><?= badge_status_pinjam($jdw['status_peminjaman']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
