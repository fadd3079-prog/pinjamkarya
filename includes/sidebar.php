<?php
/**
 * Komponen bilah navigasi samping (Sidebar) multi-peran PinjamKarya.
 *
 * Merender daftar menu kontekstual berdasarkan peran aktif (warga, mitra
 * kontributor/teknisi, admin hub, atau pengunjung), ringkasan akun,
 * serta tombol pintasan ke beranda publik dan keluar sesi.
 */
$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? 'public';

/**
 * Menentukan kelas CSS 'active' apabila jalur skrip saat ini cocok dengan target menu.
 */
function is_nav_active(string $needle, string $current): string {
    return strpos($current, $needle) !== false ? 'active' : '';
}
?>
<aside class="pk-sidebar" id="pkSidebar" aria-label="Navigasi Utama">
    <div class="pk-sidebar-header">
        <a href="<?= BASE_URL . ($user ? '/dashboard.php' : '/index.php') ?>" class="pk-brand pk-sidebar-brand" aria-label="PinjamKarya, beranda">
            <span class="pk-brand-icon"><?= brand_logo('light', 18) ?></span>
            <span>PinjamKarya</span>
        </a>
        <button type="button" class="pk-sidebar-close" id="pkSidebarClose" aria-label="Tutup navigasi">
            <?= svg_icon('x', 18) ?>
        </button>
    </div>

    <nav class="pk-sidebar-nav" aria-label="Menu PinjamKarya">
        <div class="pk-nav-group">
            <ul class="pk-nav-list">
                <li>
                    <a href="<?= BASE_URL . ($user ? '/dashboard.php' : '/index.php') ?>" class="pk-nav-link <?= is_nav_active('/dashboard.php', $script_path) ?>">
                        <?= svg_icon('home', 18) ?>
                        <span>Home</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/katalog/index.php" class="pk-nav-link <?= is_nav_active('/katalog/', $script_path) ?>">
                        <?= svg_icon('grid', 18) ?>
                        <span>Katalog</span>
                    </a>
                </li>
            </ul>
        </div>

        <?php if ($role === 'warga'): ?>
        <div class="pk-nav-group">
            <ul class="pk-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>/reservasi/riwayat_pinjam.php" class="pk-nav-link <?= is_nav_active('/reservasi/', $script_path) ?>">
                        <?= svg_icon('calendar', 18) ?>
                        <span>Pinjaman Saya</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/perbaikan/buat_tiket.php" class="pk-nav-link <?= is_nav_active('/perbaikan/buat_tiket.php', $script_path) ?>">
                        <?= svg_icon('wrench', 18) ?>
                        <span>Ajukan Perbaikan</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/perbaikan/tiket_saya.php" class="pk-nav-link <?= is_nav_active('/perbaikan/tiket_saya.php', $script_path) ?>">
                        <?= svg_icon('clipboard', 18) ?>
                        <span>Tiket Servis Saya</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/auth/profil.php" class="pk-nav-link <?= is_nav_active('/auth/profil.php', $script_path) ?>">
                        <?= svg_icon('user', 18) ?>
                        <span>Profil &amp; Skor</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($role === 'mitra'): ?>
        <div class="pk-nav-group">
            <ul class="pk-nav-list">
                <?php if (!empty($user['is_kontributor'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>/mitra/aset_mitra.php" class="pk-nav-link <?= is_nav_active('/mitra/aset_mitra.php', $script_path) ?>">
                        <?= svg_icon('box', 18) ?>
                        <span>Alat Titipan Saya</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/admin/riwayat_inspeksi.php" class="pk-nav-link <?= is_nav_active('/admin/riwayat_inspeksi.php', $script_path) ?>">
                        <?= svg_icon('clipboard', 18) ?>
                        <span>Riwayat Inspeksi</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (!empty($user['is_teknisi'])): ?>
                <li>
                    <a href="<?= BASE_URL ?>/mitra/antrean_teknisi.php" class="pk-nav-link <?= is_nav_active('/mitra/antrean_teknisi.php', $script_path) ?>">
                        <?= svg_icon('wrench', 18) ?>
                        <span>Meja Kerja Teknisi</span>
                        <?php if (empty($user['is_verified_teknisi'])): ?>
                            <span class="pk-nav-badge">Pending</span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <li>
                    <a href="<?= BASE_URL ?>/auth/profil.php" class="pk-nav-link <?= is_nav_active('/auth/profil.php', $script_path) ?>">
                        <?= svg_icon('user', 18) ?>
                        <span>Profil Mitra</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <div class="pk-nav-group">
            <ul class="pk-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>/admin/kelola_user.php" class="pk-nav-link <?= is_nav_active('/admin/kelola_user.php', $script_path) ?>">
                        <?= svg_icon('users', 18) ?>
                        <span>Pengguna &amp; Teknisi</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/admin/kelola_kategori.php" class="pk-nav-link <?= is_nav_active('/admin/kelola_kategori.php', $script_path) ?>">
                        <?= svg_icon('tag', 18) ?>
                        <span>Master Kategori</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/admin/kelola_aset.php" class="pk-nav-link <?= is_nav_active('/admin/kelola_aset.php', $script_path) ?>">
                        <?= svg_icon('box', 18) ?>
                        <span>Inventaris Alat</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/admin/kelola_transaksi.php" class="pk-nav-link <?= is_nav_active('/admin/kelola_transaksi.php', $script_path) ?>">
                        <?= svg_icon('calendar', 18) ?>
                        <span>Transaksi Pinjam</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/admin/riwayat_inspeksi.php" class="pk-nav-link <?= (is_nav_active('/admin/riwayat_inspeksi.php', $script_path) || is_nav_active('/admin/inspeksi.php', $script_path)) ? 'active' : '' ?>">
                        <?= svg_icon('clipboard', 18) ?>
                        <span>Log Inspeksi</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/admin/kelola_tiket.php" class="pk-nav-link <?= is_nav_active('/admin/kelola_tiket.php', $script_path) ?>">
                        <?= svg_icon('wrench', 18) ?>
                        <span>Tiket Perbaikan</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/auth/profil.php" class="pk-nav-link <?= is_nav_active('/auth/profil.php', $script_path) ?>">
                        <?= svg_icon('user', 18) ?>
                        <span>Profil Admin</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!$user): ?>
        <div class="pk-nav-group">
            <ul class="pk-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>/auth/login.php" class="pk-nav-link <?= is_nav_active('/auth/login.php', $script_path) ?>">
                        <?= svg_icon('log-in', 18) ?>
                        <span>Masuk Akun</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="pk-nav-link <?= is_nav_active('/auth/register.php', $script_path) ?>">
                        <?= svg_icon('user', 18) ?>
                        <span>Daftar Akun</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>

    <?php if ($user): ?>
        <div class="pk-sidebar-footer">
            <a href="<?= BASE_URL ?>/auth/profil.php" class="pk-sidebar-account" title="<?= e($user['nama']) ?> (Profil)">
                <div class="pk-sidebar-account-avatar" aria-hidden="true">
                    <?= svg_icon('user', 16) ?>
                </div>
                <div class="pk-sidebar-account-main">
                    <strong><?= e(format_nama_pengguna((string)$user['nama'])) ?></strong>
                    <div class="pk-sidebar-account-meta">
                        <?= render_role_badge($user) ?>
                        <?php if ($role === 'warga'): ?>
                            <span class="pk-sidebar-score">Skor <?= (int)$user['skor_kredibilitas'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-muted pk-sidebar-landing w-100">
                <?= svg_icon('globe', 16) ?>
                <span>Ke Landing Page</span>
            </a>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline pk-sidebar-logout w-100">
                <?= svg_icon('log-out', 16) ?>
                <span>Keluar Akun</span>
            </a>
        </div>
    <?php else: ?>
        <div class="pk-sidebar-footer">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-muted pk-sidebar-landing w-100">
                <?= svg_icon('globe', 16) ?>
                <span>Ke Landing Page</span>
            </a>
        </div>
    <?php endif; ?>
</aside>
