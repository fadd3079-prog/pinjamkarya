<?php
require_once dirname(__DIR__) . '/config/app.php';

bersihkan_ghost_booking($conn);

$id_alat = (int)($_GET['id'] ?? ($_GET['id_alat'] ?? 0));
if ($id_alat <= 0) {
    set_flash('warning', 'Pilih unit perkakas terlebih dahulu.');
    header('Location: ' . BASE_URL . '/katalog/index.php');
    exit;
}

$sql = "SELECT a.*, k.nama_kategori, k.panduan_keselamatan, k.rata_rata_berat_kg,
               u.nama AS nama_pemilik, u.role AS role_pemilik
        FROM aset_alat a
        JOIN kategori_alat k ON a.id_kategori = k.id_kategori
        JOIN users u ON a.id_pemilik = u.id_user
        WHERE a.id_alat = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id_alat);
mysqli_stmt_execute($stmt);
$alat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$alat || $alat['status_alat'] === 'Menunggu Verifikasi') {
    set_flash('danger', 'Unit perkakas tidak ditemukan.');
    header('Location: ' . BASE_URL . '/katalog/index.php');
    exit;
}

$sql_jadwal = "SELECT p.kode_booking, p.tgl_mulai, p.tgl_selesai, p.status_peminjaman, u.nama AS nama_peminjam
               FROM peminjaman p
               JOIN users u ON p.id_warga = u.id_user
               WHERE p.id_alat = ?
                 AND p.status_peminjaman IN ('Dipesan', 'Dipinjam')
                 AND p.tgl_selesai >= CURDATE()
               ORDER BY p.tgl_mulai ASC";
$stmt_j = mysqli_prepare($conn, $sql_jadwal);
mysqli_stmt_bind_param($stmt_j, 'i', $id_alat);
mysqli_stmt_execute($stmt_j);
$res_jadwal = mysqli_stmt_get_result($stmt_j);
$jadwal_terisi = [];
while ($j = mysqli_fetch_assoc($res_jadwal)) {
    $jadwal_terisi[] = $j;
}
mysqli_stmt_close($stmt_j);

$tgl_mulai_q = trim($_GET['tgl_mulai'] ?? '');
$tgl_selesai_q = trim($_GET['tgl_selesai'] ?? '');
$reservasi_url = BASE_URL . '/reservasi/reservasi.php?id_alat=' . (int)$alat['id_alat'];
if ($tgl_mulai_q !== '' && $tgl_selesai_q !== '') {
    $reservasi_url .= '&tgl_mulai=' . rawurlencode($tgl_mulai_q) . '&tgl_selesai=' . rawurlencode($tgl_selesai_q);
}

$page_title = $alat['nama_alat'];
$public_layout = true;
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
            <div class="pk-tool-media pk-tool-media--framed mb-3">
                <img src="<?= e(get_gambar_alat($alat['foto_alat'], $alat['nama_alat'])) ?>"
                     alt="<?= e($alat['nama_alat']) ?>"
                     width="640"
                     height="400">
                <div class="pk-tool-badges">
                    <span class="pk-badge pk-badge--primary"><?= e($alat['nama_kategori']) ?></span>
                    <?= badge_status_alat($alat['status_alat']) ?>
                </div>
            </div>

            <h2 id="spesifikasi-heading" class="pk-card-title mb-2">Spesifikasi</h2>
            <p class="text-muted"><?= e($alat['deskripsi']) ?></p>

            <ul class="pk-tool-meta mb-3">
                <li>
                    <span>Status</span>
                    <?= badge_status_alat($alat['status_alat']) ?>
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

            <?php if ($alat['status_alat'] === 'Perawatan'): ?>
                <div class="pk-alert pk-alert--danger mb-0">
                    <div>Unit sedang dalam perawatan dan belum dapat dipesan.</div>
                </div>
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
            </div>
            <div class="pk-guideline-box"><?= e($alat['panduan_keselamatan']) ?></div>
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
