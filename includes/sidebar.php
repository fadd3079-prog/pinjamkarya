<?php
$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? 'public';

function is_nav_active(string $needle, string $current): string {
    return strpos($current, $needle) !== false ? 'active' : '';
}

function nav_aria_current(string $needle, string $current): string {
    return strpos($current, $needle) !== false ? ' aria-current="page"' : '';
}
?>
<aside class="pk-sidebar" id="pkSidebar" aria-label="Navigasi Utama">
    <div>
        <div class="pk-sidebar-header">
            <span class="fw-bold">Navigasi</span>
            <button type="button" class="pk-sidebar-close" id="pkSidebarClose" aria-label="Tutup navigasi">
                <?= svg_icon('x', 18) ?>
            </button>
        </div>

        <nav aria-label="Menu PinjamKarya">
            <div class="pk-nav-group">
                <div class="pk-nav-group-title">Layanan Komunitas</div>
                <ul class="pk-nav-list">
                    <li>
                        <a href="<?= BASE_URL ?>/modul4/katalog.php" class="pk-nav-link <?= is_nav_active('modul4/', $script_path) ?>"<?= nav_aria_current('modul4/', $script_path) ?>>
                            <?= svg_icon('grid', 18) ?>
                            <span>Katalog Perkakas</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul2/panduan.php" class="pk-nav-link <?= is_nav_active('modul2/panduan.php', $script_path) ?>"<?= nav_aria_current('modul2/panduan.php', $script_path) ?>>
                            <?= svg_icon('shield', 18) ?>
                            <span>Panduan Keselamatan</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul9/dashboard.php" class="pk-nav-link <?= is_nav_active('modul9/dashboard.php', $script_path) ?>"<?= nav_aria_current('modul9/dashboard.php', $script_path) ?>>
                            <?= svg_icon('bar-chart', 18) ?>
                            <span>Dashboard Dampak</span>
                        </a>
                    </li>
                </ul>
            </div>

            <?php if ($role === 'warga'): ?>
            <div class="pk-nav-group">
                <div class="pk-nav-group-title">Aktivitas Warga</div>
                <ul class="pk-nav-list">
                    <li>
                        <a href="<?= BASE_URL ?>/modul5/riwayat_pinjam.php" class="pk-nav-link <?= is_nav_active('modul5/', $script_path) ?>"<?= nav_aria_current('modul5/', $script_path) ?>>
                            <?= svg_icon('calendar', 18) ?>
                            <span>Pinjaman Saya</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul7/buat_tiket.php" class="pk-nav-link <?= is_nav_active('modul7/buat_tiket.php', $script_path) ?>"<?= nav_aria_current('modul7/buat_tiket.php', $script_path) ?>>
                            <?= svg_icon('wrench', 18) ?>
                            <span>Ajukan Perbaikan</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul8/tiket_saya.php" class="pk-nav-link <?= is_nav_active('modul8/tiket_saya.php', $script_path) ?>"<?= nav_aria_current('modul8/tiket_saya.php', $script_path) ?>>
                            <?= svg_icon('clipboard', 18) ?>
                            <span>Tiket Servis Saya</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul1/profil.php" class="pk-nav-link <?= is_nav_active('modul1/profil.php', $script_path) ?>"<?= nav_aria_current('modul1/profil.php', $script_path) ?>>
                            <?= svg_icon('user', 18) ?>
                            <span>Profil &amp; Skor</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($role === 'mitra'): ?>
            <div class="pk-nav-group">
                <div class="pk-nav-group-title">Ruang Mitra</div>
                <ul class="pk-nav-list">
                    <?php if (!empty($user['is_kontributor'])): ?>
                    <li>
                        <a href="<?= BASE_URL ?>/modul3/aset_mitra.php" class="pk-nav-link <?= is_nav_active('modul3/aset_mitra.php', $script_path) ?>"<?= nav_aria_current('modul3/aset_mitra.php', $script_path) ?>>
                            <?= svg_icon('box', 18) ?>
                            <span>Alat Titipan Saya</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul6/riwayat_inspeksi.php" class="pk-nav-link <?= is_nav_active('modul6/riwayat_inspeksi.php', $script_path) ?>"<?= nav_aria_current('modul6/riwayat_inspeksi.php', $script_path) ?>>
                            <?= svg_icon('clipboard', 18) ?>
                            <span>Riwayat Inspeksi</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (!empty($user['is_teknisi'])): ?>
                    <li>
                        <a href="<?= BASE_URL ?>/modul8/antrean_teknisi.php" class="pk-nav-link <?= is_nav_active('modul8/antrean_teknisi.php', $script_path) ?>"<?= nav_aria_current('modul8/antrean_teknisi.php', $script_path) ?>>
                            <?= svg_icon('wrench', 18) ?>
                            <span>Meja Kerja Teknisi</span>
                            <?php if (empty($user['is_verified_teknisi'])): ?>
                                <span class="pk-nav-badge">Pending</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <li>
                        <a href="<?= BASE_URL ?>/modul1/profil.php" class="pk-nav-link <?= is_nav_active('modul1/profil.php', $script_path) ?>"<?= nav_aria_current('modul1/profil.php', $script_path) ?>>
                            <?= svg_icon('user', 18) ?>
                            <span>Profil Mitra</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
            <div class="pk-nav-group">
                <div class="pk-nav-group-title">Pengelolaan Hub</div>
                <ul class="pk-nav-list">
                    <li>
                        <a href="<?= BASE_URL ?>/modul1/kelola_user.php" class="pk-nav-link <?= is_nav_active('modul1/kelola_user.php', $script_path) ?>"<?= nav_aria_current('modul1/kelola_user.php', $script_path) ?>>
                            <?= svg_icon('users', 18) ?>
                            <span>Pengguna &amp; Teknisi</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul2/kelola_kategori.php" class="pk-nav-link <?= is_nav_active('modul2/kelola_kategori.php', $script_path) ?>"<?= nav_aria_current('modul2/kelola_kategori.php', $script_path) ?>>
                            <?= svg_icon('tag', 18) ?>
                            <span>Master Kategori</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul3/kelola_aset.php" class="pk-nav-link <?= is_nav_active('modul3/kelola_aset.php', $script_path) ?>"<?= nav_aria_current('modul3/kelola_aset.php', $script_path) ?>>
                            <?= svg_icon('box', 18) ?>
                            <span>Inventaris Alat</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul5/kelola_transaksi.php" class="pk-nav-link <?= is_nav_active('modul5/kelola_transaksi.php', $script_path) ?>"<?= nav_aria_current('modul5/kelola_transaksi.php', $script_path) ?>>
                            <?= svg_icon('calendar', 18) ?>
                            <span>Transaksi Pinjam</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul6/riwayat_inspeksi.php" class="pk-nav-link <?= is_nav_active('modul6/', $script_path) ?>"<?= nav_aria_current('modul6/', $script_path) ?>>
                            <?= svg_icon('clipboard', 18) ?>
                            <span>Log Inspeksi</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul8/kelola_tiket.php" class="pk-nav-link <?= is_nav_active('modul8/kelola_tiket.php', $script_path) ?>"<?= nav_aria_current('modul8/kelola_tiket.php', $script_path) ?>>
                            <?= svg_icon('wrench', 18) ?>
                            <span>Tiket Perbaikan</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul1/profil.php" class="pk-nav-link <?= is_nav_active('modul1/profil.php', $script_path) ?>"<?= nav_aria_current('modul1/profil.php', $script_path) ?>>
                            <?= svg_icon('user', 18) ?>
                            <span>Profil Admin</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!$user): ?>
            <div class="pk-nav-group">
                <div class="pk-nav-group-title">Akses Akun</div>
                <ul class="pk-nav-list">
                    <li>
                        <a href="<?= BASE_URL ?>/modul1/login.php" class="pk-nav-link <?= is_nav_active('modul1/login.php', $script_path) ?>"<?= nav_aria_current('modul1/login.php', $script_path) ?>>
                            <?= svg_icon('log-in', 18) ?>
                            <span>Masuk Akun</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/modul1/register.php" class="pk-nav-link <?= is_nav_active('modul1/register.php', $script_path) ?>"<?= nav_aria_current('modul1/register.php', $script_path) ?>>
                            <?= svg_icon('user', 18) ?>
                            <span>Daftar Akun</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </nav>
    </div>

    <div class="pk-sidebar-footer">
        <?php if ($user): ?>
            <div class="mb-2">
                <div class="fw-bold"><?= e($user['nama']) ?></div>
                <div class="text-muted" style="font-size:0.85rem;">Peran: <?= e(strtoupper($user['role'])) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/modul1/logout.php" class="btn btn-danger w-100">
                <?= svg_icon('log-out', 16) ?>
                <span>Keluar dari Akun</span>
            </a>
        <?php else: ?>
            <div class="d-flex flex-column gap-2">
                <a href="<?= BASE_URL ?>/modul1/login.php" class="btn btn-primary w-100">Masuk Akun</a>
                <a href="<?= BASE_URL ?>/modul1/register.php" class="btn btn-warm w-100">Daftar Akun</a>
            </div>
        <?php endif; ?>
    </div>
</aside>
