<?php
require_once dirname(__DIR__) . '/config/app.php';

if (isset($_SESSION['user'])) {
    refresh_user_session($conn);
}

$current_user = $_SESSION['user'] ?? null;
$page_title_full = isset($page_title) && $page_title !== ''
    ? $page_title . ' - PinjamKarya'
    : 'PinjamKarya';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PinjamKarya: Platform pinjam perkakas komunitas dan perbaikan barang warga.">
    <title><?= e($page_title_full) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pinjamkarya-ui.css">
</head>
<body>
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>

<header class="pk-navbar">
    <div class="pk-navbar-inner">
        <div class="pk-brand-wrap">
            <button type="button" class="pk-menu-toggle" id="pkSidebarToggle" aria-controls="pkSidebar" aria-expanded="false" aria-label="Buka navigasi">
                <?= svg_icon('menu', 18) ?>
                <span>Menu</span>
            </button>
            <a href="<?= BASE_URL ?>/index.php" class="pk-brand">
                <span class="pk-brand-icon"><?= svg_icon('wrench', 18) ?></span>
                <span>PinjamKarya</span>
            </a>
        </div>

        <div class="pk-navbar-actions">
            <?php if ($current_user): ?>
                <?php if ($current_user['role'] === 'warga'): ?>
                    <a href="<?= BASE_URL ?>/modul1/profil.php" class="pk-score-pill" title="Skor Kepercayaan">
                        <?= svg_icon('heart', 15) ?>
                        <span>Skor: <?= (int)$current_user['skor_kredibilitas'] ?></span>
                    </a>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/modul1/profil.php" class="pk-user-chip">
                    <?= svg_icon('user', 15) ?>
                    <span><?= e($current_user['nama']) ?></span>
                    <span class="pk-role-tag"><?= e($current_user['role']) ?></span>
                </a>

                <a href="<?= BASE_URL ?>/modul1/logout.php" class="btn btn-danger" style="min-height:38px; padding:6px 12px;">
                    <?= svg_icon('log-out', 15) ?>
                    <span>Keluar</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/modul1/login.php" class="btn btn-outline" style="min-height:38px; padding:6px 14px;">
                    <span>Masuk</span>
                </a>
                <a href="<?= BASE_URL ?>/modul1/register.php" class="btn btn-warm" style="min-height:38px; padding:6px 14px;">
                    <span>Daftar</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="pk-backdrop" id="pkBackdrop" aria-hidden="true"></div>

<div class="pk-app-shell">
    <?php require_once ROOT_PATH . '/includes/sidebar.php'; ?>
    <main id="main-content" class="pk-main">
        <div class="pk-container">
            <?= get_flash() ?>
