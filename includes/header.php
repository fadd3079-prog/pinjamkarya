<?php
require_once dirname(__DIR__) . '/config/app.php';

if (isset($_SESSION['user'])) {
    refresh_user_session($conn);
}

$current_user = $_SESSION['user'] ?? null;
$minimal_layout = !empty($minimal_layout);
$public_layout = !empty($public_layout);
$public_nav_active = $public_nav_active ?? 'katalog';
$page_title_full = isset($page_title) && $page_title !== ''
    ? $page_title . ' - PinjamKarya'
    : 'PinjamKarya';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f3f2ee">
    <meta name="description" content="PinjamKarya: Platform pinjam perkakas komunitas dan perbaikan barang warga.">
    <title><?= e($page_title_full) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/favicon.svg">
    <link rel="preload" href="<?= BASE_URL ?>/assets/fonts/AdwaitaSans-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pinjamkarya-ui.css?v=<?= filemtime(ROOT_PATH . '/assets/css/pinjamkarya-ui.css') ?>">
</head>
<body<?= $minimal_layout ? ' class="pk-auth-page"' : '' ?>>
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>

<?php if ($minimal_layout): ?>
<main id="main-content" class="pk-auth-main">
<?php elseif ($public_layout): ?>
<?= render_public_nav($public_nav_active) ?>
<main id="main-content" class="pk-page">
    <div class="pk-container">
        <?= get_flash() ?>
<?php else: ?>
<header class="pk-navbar">
    <div class="pk-navbar-inner">
        <a href="<?= BASE_URL . ($current_user ? '/dashboard.php' : '/index.php') ?>" class="pk-brand" aria-label="PinjamKarya, beranda">
            <span class="pk-brand-icon"><?= brand_logo('light', 18) ?></span>
            <span>PinjamKarya</span>
        </a>

        <div class="pk-navbar-actions">
            <?php if ($current_user): ?>
                <?php if ($current_user['role'] === 'warga'): ?>
                    <a href="<?= BASE_URL ?>/auth/profil.php" class="pk-score-pill" title="Skor Kepercayaan">
                        <?= svg_icon('heart', 14) ?>
                        <span class="pk-score-label">Skor:</span>
                        <span><?= (int)$current_user['skor_kredibilitas'] ?></span>
                    </a>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/auth/profil.php" class="pk-user-chip" title="<?= e($current_user['nama']) ?> (Profil)">
                    <?= svg_icon('user', 14) ?>
                    <span class="pk-user-name"><?= e($current_user['nama']) ?></span>
                    <span class="pk-role-tag"><?= e($current_user['role']) ?></span>
                </a>

                <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline pk-nav-logout">
                    <?= svg_icon('log-out', 14) ?>
                    <span>Keluar</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline">
                    <span>Masuk</span>
                </a>
                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary">
                    <span>Daftar</span>
                </a>
            <?php endif; ?>

            <button type="button" class="pk-menu-toggle" id="pkSidebarToggle" aria-controls="pkSidebar" aria-expanded="false" aria-label="Buka navigasi">
                <span class="pk-menu-label">Menu</span>
                <span class="site-nav_toggle-icon" aria-hidden="true"><span></span></span>
            </button>
        </div>
    </div>
</header>

<div class="pk-backdrop" id="pkBackdrop" aria-hidden="true"></div>

<div class="pk-app-shell">
    <?php require_once ROOT_PATH . '/includes/sidebar.php'; ?>
    <main id="main-content" class="pk-main">
        <div class="pk-container">
            <?= get_flash() ?>
<?php endif; ?>
