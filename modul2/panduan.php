<?php
require_once dirname(__DIR__) . '/config/app.php';

$sql = "SELECT k.*,
        (SELECT COUNT(*) FROM aset_alat a WHERE a.id_kategori = k.id_kategori AND a.status_alat = 'Tersedia') AS unit_tersedia
        FROM kategori_alat k
        ORDER BY k.id_kategori ASC";
$res_kat = mysqli_query($conn, $sql);

$page_title = 'Panduan Keselamatan';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Panduan Keselamatan Kerja</h1>
    <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
        <a href="<?= BASE_URL ?>/modul2/kelola_kategori.php" class="btn btn-primary">
            <?= svg_icon('tag', 16) ?>
            <span>Kelola Kategori</span>
        </a>
    <?php endif; ?>
</div>

<div class="pk-grid pk-grid--2">
    <?php while ($kat = mysqli_fetch_assoc($res_kat)): ?>
        <article class="pk-card d-flex flex-column justify-between mb-0">
            <div>
                <div class="d-flex align-center justify-between flex-wrap gap-2 mb-2">
                    <h2 class="mb-0"><?= e($kat['nama_kategori']) ?></h2>
                    <span class="pk-badge pk-badge--success"><?= (int)$kat['unit_tersedia'] ?> Tersedia</span>
                </div>

                <div class="pk-guideline-box mb-3"><?= e($kat['panduan_keselamatan']) ?></div>
            </div>

            <div>
                <ul class="pk-tool-meta">
                    <li>
                        <span>Rata-rata Berat Acuan</span>
                        <strong><?= number_format((float)$kat['rata_rata_berat_kg'], 2, ',', '.') ?> Kg</strong>
                    </li>
                    <li>
                        <span>Estimasi Harga Baru</span>
                        <strong><?= rupiah($kat['estimasi_harga_baru']) ?></strong>
                    </li>
                </ul>

                <a href="<?= BASE_URL ?>/modul4/katalog.php?id_kategori=<?= (int)$kat['id_kategori'] ?>" class="btn btn-outline w-100">
                    <span>Lihat Alat</span>
                </a>
            </div>
        </article>
    <?php endwhile; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
