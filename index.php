<?php
require_once __DIR__ . '/config/app.php';

if (isset($_SESSION['user'])) {
    if (($_SESSION['user']['role'] ?? '') === 'admin') {
        header('Location: ' . BASE_URL . '/modul9/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/modul4/katalog.php');
    }
    exit;
}

bersihkan_ghost_booking($conn);

$hero_banner_url = get_hero_banner();

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
    <meta name="description" content="PinjamKarya: Platform pinjam alat komunitas dan layanan perbaikan barang warga.">
    <title>PinjamKarya</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pinjamkarya-ui.css">
</head>
<body>
<a href="#layanan-kami" class="skip-link">Lewati ke konten utama</a>

<section class="pk-hero <?= $hero_banner_url === null ? 'pk-hero--fallback' : '' ?>" aria-label="PinjamKarya">
    <?php if ($hero_banner_url !== null): ?>
        <img src="<?= e($hero_banner_url) ?>"
             alt="Perkakas komunitas PinjamKarya"
             width="1600"
             height="900"
             fetchpriority="high"
             style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; z-index:0;">
    <?php endif; ?>

    <header class="pk-hero-top">
        <a href="<?= BASE_URL ?>/index.php" class="pk-hero-brand">PinjamKarya</a>
        <button type="button"
                class="pk-hero-menu-btn"
                id="pkHeroMenuBtn"
                aria-controls="pkHeroDrawer"
                aria-expanded="false">
            <?= svg_icon('menu', 18) ?>
            <span>Menu</span>
        </button>
    </header>

    <div class="pk-hero-center" aria-hidden="true">
        <?php if ($hero_banner_url === null): ?>
            <svg class="pk-hero-horizon-art" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 320" width="800" height="320" fill="none">
                <line x1="20" y1="160" x2="780" y2="160" stroke="rgba(255,255,255,0.55)" stroke-width="2" stroke-dasharray="8 6"/>
                <path d="M90 185 Q 150 177, 210 185 T 330 185" stroke="rgba(227,242,246,0.38)" stroke-width="2"/>
                <path d="M470 192 Q 540 184, 610 192 T 730 192" stroke="rgba(227,242,246,0.38)" stroke-width="2"/>
                <rect x="275" y="108" width="250" height="114" rx="14" fill="rgba(14,90,109,0.52)" stroke="#ffffff" stroke-width="3"/>
                <line x1="275" y1="152" x2="525" y2="152" stroke="#e4a77b" stroke-width="3"/>
                <path d="M355 108 L355 86 C355 78, 445 78, 445 86 L445 108" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round"/>
                <rect x="384" y="142" width="32" height="20" rx="4" fill="#e4a77b" stroke="#ffffff" stroke-width="2"/>
            </svg>
        <?php endif; ?>
    </div>

    <div class="pk-hero-bottom">
        <div>
            <h1 class="pk-hero-statement">
                Alat kerja tak harus beli sendiri.<br>
                Barang rusak tak harus dibuang.
            </h1>
            <div class="pk-hero-ctas">
                <a href="<?= BASE_URL ?>/modul4/katalog.php" class="pk-hero-btn-primary">
                    <?= svg_icon('grid', 18) ?>
                    <span>Jelajahi Katalog</span>
                </a>
                <a href="<?= BASE_URL ?>/modul1/login.php" class="pk-hero-btn-secondary">
                    <?= svg_icon('log-in', 18) ?>
                    <span>Masuk / Daftar</span>
                </a>
            </div>
        </div>

        <div class="pk-hero-scroll-wrap">
            <a href="#layanan-kami" class="pk-hero-scroll-link">
                <?= svg_icon('arrow-down', 16) ?>
                <span>Lihat Layanan</span>
            </a>
            <p class="pk-hero-caption">
                Layanan sosial pinjam perkakas<br>
                dan perbaikan barang warga.
            </p>
        </div>
    </div>
</section>

<aside class="pk-drawer" id="pkHeroDrawer" aria-label="Menu Navigasi" aria-hidden="true">
    <div>
        <div class="pk-drawer-header">
            <strong class="fs-lg">PinjamKarya</strong>
            <button type="button" class="btn btn-muted" id="pkHeroDrawerClose" aria-label="Tutup menu" style="min-width:48px; padding:10px;">
                <?= svg_icon('x', 18) ?>
            </button>
        </div>
        <nav aria-label="Menu Utama">
            <ul class="pk-drawer-list">
                <li>
                    <a href="<?= BASE_URL ?>/modul4/katalog.php" class="pk-drawer-link">
                        <?= svg_icon('grid', 18) ?>
                        <span>Katalog Perkakas</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/modul2/panduan.php" class="pk-drawer-link">
                        <?= svg_icon('shield', 18) ?>
                        <span>Panduan Keselamatan</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/modul9/dashboard.php" class="pk-drawer-link">
                        <?= svg_icon('bar-chart', 18) ?>
                        <span>Dashboard Dampak</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/modul1/login.php" class="pk-drawer-link">
                        <?= svg_icon('log-in', 18) ?>
                        <span>Masuk Akun</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/modul1/register.php" class="pk-drawer-link">
                        <?= svg_icon('user', 18) ?>
                        <span>Daftar Akun</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>
<div class="pk-backdrop" id="pkBackdrop" aria-hidden="true"></div>

<main id="layanan-kami" class="pk-main">
    <div class="pk-container">
        <div class="pk-grid pk-grid--2 mb-4">
            <article class="pk-card pk-card--sky-soft d-flex flex-column justify-between gap-2 mb-0">
                <div>
                    <h2>Pinjam Perkakas &amp; Alat Kesehatan</h2>
                    <p class="text-muted mb-2">Gunakan alat pertukangan, kebun, hingga kursi roda darurat bersama warga tanpa harus membeli baru.</p>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/modul4/katalog.php" class="btn btn-primary btn-pill">
                        <?= svg_icon('grid', 16) ?>
                        <span>Lihat Katalog</span>
                    </a>
                </div>
            </article>

            <article class="pk-card pk-card--warm-soft d-flex flex-column justify-between gap-2 mb-0">
                <div>
                    <h2>Perbaiki Barang Rusak</h2>
                    <p class="text-muted mb-2">Konsultasikan elektronik atau perabot rusak ke teknisi komunitas dengan estimasi biaya transparan sebelum diservis.</p>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/modul1/login.php" class="btn btn-warm btn-pill">
                        <?= svg_icon('wrench', 16) ?>
                        <span>Ajukan Perbaikan</span>
                    </a>
                </div>
            </article>
        </div>

        <section class="pk-card mb-4" aria-labelledby="heading-dampak-live">
            <div class="pk-card-header">
                <h2 id="heading-dampak-live" class="pk-card-title">
                    <?= svg_icon('bar-chart', 18) ?>
                    <span>Ringkasan Dampak Komunitas</span>
                </h2>
                <a href="<?= BASE_URL ?>/modul9/dashboard.php" class="btn btn-outline">
                    <span>Laporan Lengkap</span>
                </a>
            </div>

            <div class="pk-grid pk-grid--3">
                <div class="pk-card pk-card--primary-soft mb-0">
                    <div class="text-muted">Alat Tersedia</div>
                    <div class="fs-xl fw-bold text-primary mt-1"><?= $jml_alat_tersedia ?> Unit</div>
                </div>
                <div class="pk-card pk-card--secondary-soft mb-0">
                    <div class="text-muted">Barang Diperbaiki</div>
                    <div class="fs-xl fw-bold text-secondary mt-1"><?= $jml_barang_diperbaiki ?> Unit</div>
                </div>
                <div class="pk-card pk-card--warm-soft mb-0">
                    <div class="text-muted">Total Penghematan Warga</div>
                    <div class="fs-xl fw-bold text-warm mt-1"><?= rupiah($total_penghematan) ?></div>
                </div>
            </div>
        </section>

        <section aria-labelledby="heading-pratinjau-alat">
            <div class="d-flex align-center justify-between flex-wrap gap-2 mb-3">
                <h2 id="heading-pratinjau-alat" class="mb-0">Perkakas Siap Pakai</h2>
                <a href="<?= BASE_URL ?>/modul4/katalog.php" class="btn btn-outline">Semua Katalog</a>
            </div>

            <div class="pk-grid pk-grid--3">
                <?php while ($item = mysqli_fetch_assoc($q_preview)): ?>
                    <article class="pk-tool-card">
                        <div class="pk-tool-media">
                            <img src="<?= e(get_gambar_alat($item['foto_alat'], $item['nama_alat'])) ?>"
                                 alt="<?= e($item['nama_alat']) ?>"
                                 width="640"
                                 height="400"
                                 loading="lazy">
                            <div class="pk-tool-badges">
                                <span class="pk-badge pk-badge--primary"><?= e($item['nama_kategori']) ?></span>
                                <?= badge_status_alat($item['status_alat']) ?>
                            </div>
                        </div>
                        <div class="pk-tool-body">
                            <h3 class="pk-tool-title"><?= e($item['nama_alat']) ?></h3>
                            <p class="pk-tool-desc"><?= e($item['deskripsi']) ?></p>
                            <ul class="pk-tool-meta">
                                <li>
                                    <span>Maks. Pinjam</span>
                                    <strong><?= (int)$item['max_hari_pinjam'] ?> Hari</strong>
                                </li>
                                <li>
                                    <span>Skor Minimal</span>
                                    <strong><?= (int)$item['min_skor'] ?> Poin</strong>
                                </li>
                                <li>
                                    <span>Biaya Rawat</span>
                                    <?php if ((float)$item['biaya_perawatan'] <= 0): ?>
                                        <strong class="text-secondary">Gratis</strong>
                                    <?php else: ?>
                                        <strong><?= rupiah($item['biaya_perawatan']) ?></strong>
                                    <?php endif; ?>
                                </li>
                            </ul>
                            <a href="<?= BASE_URL ?>/modul4/detail_alat.php?id=<?= (int)$item['id_alat'] ?>" class="btn btn-primary w-100">
                                Detail &amp; Jadwal
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        </section>
    </div>
</main>

<footer class="pk-footer">
    <div class="pk-footer-inner">
        <div><strong>PinjamKarya</strong></div>
        <div>Informatika Universitas Jenderal Soedirman (2026)</div>
    </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/pinjamkarya.js" defer></script>
</body>
</html>
