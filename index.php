<?php
require_once __DIR__ . '/config/app.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

bersihkan_ghost_booking($conn);

$q_alat = mysqli_query($conn, "SELECT COUNT(*) AS total FROM aset_alat WHERE status_alat = 'Tersedia'");
$jml_alat_tersedia = (int)(mysqli_fetch_assoc($q_alat)['total'] ?? 0);

$q_servis = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE status_tiket = 'Selesai'");
$jml_barang_diperbaiki = (int)(mysqli_fetch_assoc($q_servis)['total'] ?? 0);

$q_hemat_pinjam = mysqli_query($conn, "SELECT COALESCE(SUM(a.harga_beli_baru - a.biaya_perawatan), 0) AS total
    FROM peminjaman p
    JOIN aset_alat a ON p.id_alat = a.id_alat
    WHERE p.status_peminjaman = 'Selesai'");
$hemat_pinjam = (float)(mysqli_fetch_assoc($q_hemat_pinjam)['total'] ?? 0);

$q_hemat_servis = mysqli_query($conn, "SELECT COALESCE(SUM(k.estimasi_harga_baru - t.estimasi_biaya), 0) AS total
    FROM tiket_perbaikan t
    JOIN kategori_alat k ON t.id_kategori = k.id_kategori
    WHERE t.status_tiket = 'Selesai'");
$hemat_servis = (float)(mysqli_fetch_assoc($q_hemat_servis)['total'] ?? 0);

$total_penghematan = $hemat_pinjam + $hemat_servis;

$q_preview = mysqli_query($conn, "SELECT a.*, k.nama_kategori
    FROM aset_alat a
    JOIN kategori_alat k ON a.id_kategori = k.id_kategori
    WHERE a.status_alat = 'Tersedia'
    ORDER BY a.id_alat ASC
    LIMIT 3");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f3f2ee">
    <meta name="description" content="PinjamKarya: Platform pinjam alat komunitas dan layanan perbaikan barang warga.">
    <title>Pinjam Karya.</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/favicon.svg">
    <link rel="preload" href="<?= BASE_URL ?>/assets/fonts/AdwaitaSans-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pinjamkarya-ui.css?v=<?= filemtime(__DIR__ . '/assets/css/pinjamkarya-ui.css') ?>">
</head>
<body>
<a href="#layanan-kami" class="skip-link">Lewati ke konten utama</a>

<?= render_public_nav('home', true) ?>

<main id="main-content">
    <section class="pk-hero" id="hero" aria-labelledby="hero-title">
        <div class="pk-hero-shell">
            <div class="pk-hero-canvas">
                <div class="pk-hero-head">
                    <h1 id="hero-title" class="pk-hero-title">Pinjam Karya.</h1>
                    <p class="pk-hero-subtitle">Pinjam perkakas kerja, mesin produksi UMKM, dan alat bantu kesehatan secara terjadwal bersama hub serta mitra komunitas. Tersedia juga layanan perbaikan barang elektronik dan rumah tangga oleh teknisi terverifikasi.</p>
                </div>

                <div class="pk-hero-stage">
                    <img src="<?= BASE_URL ?>/assets/hero.svg"
                         alt="Ilustrasi perkakas komunitas Pinjam Karya"
                         class="pk-hero-artwork"
                         width="583"
                         height="348"
                         fetchpriority="high"
                         decoding="async">
                    <div class="pk-hero-actions">
                        <a class="btn btn-primary" href="<?= BASE_URL ?>/katalog/index.php">Lihat Katalog</a>
                        <a class="btn btn-outline" href="<?= BASE_URL ?>/auth/login.php">Masuk / Daftar</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="layanan-kami" class="pk-landing-section" aria-labelledby="heading-layanan">
        <div class="pk-container">
            <div class="pk-section-head">
                <h2 id="heading-layanan" class="pk-section-title">Layanan.</h2>
            </div>

            <div class="pk-grid pk-grid--2">
                <article class="pk-service-card">
                    <div class="pk-service-media">
                        <img src="<?= BASE_URL ?>/assets/perkakas.webp"
                             alt=""
                             width="1200"
                             height="800"
                             loading="lazy"
                             aria-hidden="true">
                    </div>
                    <div class="pk-service-shade" aria-hidden="true"></div>
                    <div class="pk-service-body">
                        <h3 class="pk-service-title">Pinjam Perkakas &amp; Alat Kesehatan</h3>
                        <a href="<?= BASE_URL ?>/katalog/index.php" class="btn btn-primary">
                            <?= svg_icon('grid', 15) ?>
                            <span>Lihat Katalog</span>
                        </a>
                    </div>
                </article>

                <article class="pk-service-card">
                    <div class="pk-service-media">
                        <img src="<?= BASE_URL ?>/assets/service.webp"
                             alt=""
                             width="1200"
                             height="800"
                             loading="lazy"
                             aria-hidden="true">
                    </div>
                    <div class="pk-service-shade" aria-hidden="true"></div>
                    <div class="pk-service-body">
                        <h3 class="pk-service-title">Perbaiki Barang Rusak</h3>
                        <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline">
                            <?= svg_icon('wrench', 15) ?>
                            <span>Ajukan Perbaikan</span>
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="pk-landing-section pk-landing-section--soft" aria-labelledby="heading-dampak-live">
        <div class="pk-container">
            <div class="pk-section-head">
                <h2 id="heading-dampak-live" class="pk-section-title">Ringkasan Komunitas.</h2>
            </div>

            <div class="pk-grid pk-grid--3 pk-grid--stats">
                <div class="pk-card mb-0">
                    <div class="text-muted">Alat Tersedia</div>
                    <div class="fs-xl mt-1"><?= $jml_alat_tersedia ?> Unit</div>
                </div>
                <div class="pk-card mb-0">
                    <div class="text-muted">Barang Diperbaiki</div>
                    <div class="fs-xl mt-1"><?= $jml_barang_diperbaiki ?> Unit</div>
                </div>
                <div class="pk-card mb-0">
                    <div class="text-muted">Total Penghematan Warga</div>
                    <div class="fs-xl mt-1"><?= rupiah($total_penghematan) ?></div>
                </div>
            </div>
        </div>
    </section>

    <section class="pk-landing-section" aria-labelledby="heading-pratinjau-alat">
        <div class="pk-container">
            <div class="pk-section-head">
                <h2 id="heading-pratinjau-alat" class="pk-section-title">Perkakas Siap Pakai.</h2>
                <a href="<?= BASE_URL ?>/katalog/index.php" class="btn btn-outline">Lihat Katalog</a>
            </div>

            <div class="pk-grid pk-grid--3">
                <?php while ($item = mysqli_fetch_assoc($q_preview)): ?>
                    <?= render_tool_card($item, BASE_URL . '/katalog/detail_alat.php?id=' . (int)$item['id_alat'], 'h3') ?>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
</main>

<?= render_public_footer() ?>

<script src="<?= BASE_URL ?>/assets/js/pinjamkarya.js?v=<?= filemtime(__DIR__ . '/assets/js/pinjamkarya.js') ?>" defer></script>
</body>
</html>
