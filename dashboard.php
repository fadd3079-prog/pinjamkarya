<?php
require_once __DIR__ . '/config/app.php';
require_login();
refresh_user_session($conn);
bersihkan_ghost_booking($conn);

$user = $_SESSION['user'];
$id_user = (int)$user['id_user'];
$role = (string)$user['role'];

$cards = [];
$actions = [];
$quick_links = [];

if ($role === 'warga') {
    $stmt_p = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE id_warga = ? AND status_peminjaman IN ('Dipesan', 'Dipinjam')");
    mysqli_stmt_bind_param($stmt_p, 'i', $id_user);
    mysqli_stmt_execute($stmt_p);
    $pinjam_aktif = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))['total'] ?? 0);
    mysqli_stmt_close($stmt_p);

    $stmt_t = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE id_pelapor = ? AND status_tiket NOT IN ('Selesai', 'Dibatalkan')");
    mysqli_stmt_bind_param($stmt_t, 'i', $id_user);
    mysqli_stmt_execute($stmt_t);
    $tiket_aktif = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t))['total'] ?? 0);
    mysqli_stmt_close($stmt_t);

    $cards = [
        ['label' => 'Pinjaman Aktif', 'value' => $pinjam_aktif . ' Alat', 'icon' => 'calendar', 'attention' => $pinjam_aktif > 0, 'href' => BASE_URL . '/reservasi/riwayat_pinjam.php'],
        ['label' => 'Tiket Servis', 'value' => $tiket_aktif . ' Tiket', 'icon' => 'wrench', 'attention' => $tiket_aktif > 0, 'href' => BASE_URL . '/perbaikan/tiket_saya.php'],
        ['label' => 'Skor Kepercayaan', 'value' => (int)$user['skor_kredibilitas'] . ' Poin', 'icon' => 'heart', 'attention' => false, 'href' => BASE_URL . '/auth/profil.php'],
    ];

    $actions = [
        ['label' => 'Pinjam Perkakas', 'btn' => 'Lihat Katalog', 'icon' => 'grid', 'href' => BASE_URL . '/katalog/index.php', 'primary' => true],
        ['label' => 'Perbaiki Barang Rusak', 'btn' => 'Ajukan Perbaikan', 'icon' => 'wrench', 'href' => BASE_URL . '/perbaikan/buat_tiket.php', 'primary' => false],
    ];

    $quick_links = [
        ['label' => 'Katalog Alat', 'icon' => 'grid', 'href' => BASE_URL . '/katalog/index.php'],
        ['label' => 'Pinjaman Saya', 'icon' => 'calendar', 'href' => BASE_URL . '/reservasi/riwayat_pinjam.php'],
        ['label' => 'Tiket Servis', 'icon' => 'clipboard', 'href' => BASE_URL . '/perbaikan/tiket_saya.php'],
        ['label' => 'Profil & Skor', 'icon' => 'user', 'href' => BASE_URL . '/auth/profil.php'],
    ];
} elseif ($role === 'mitra') {
    if (!empty($user['is_kontributor'])) {
        $stmt_a = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM aset_alat WHERE id_pemilik = ?");
        mysqli_stmt_bind_param($stmt_a, 'i', $id_user);
        mysqli_stmt_execute($stmt_a);
        $total_titipan = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_a))['total'] ?? 0);
        mysqli_stmt_close($stmt_a);

        $stmt_ap = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM aset_alat WHERE id_pemilik = ? AND status_alat = 'Dipinjam'");
        mysqli_stmt_bind_param($stmt_ap, 'i', $id_user);
        mysqli_stmt_execute($stmt_ap);
        $titipan_dipinjam = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ap))['total'] ?? 0);
        mysqli_stmt_close($stmt_ap);

        $cards[] = ['label' => 'Alat Titipan', 'value' => $total_titipan . ' Unit', 'icon' => 'box', 'attention' => false, 'href' => BASE_URL . '/mitra/aset_mitra.php'];
        $cards[] = ['label' => 'Sedang Dipinjam', 'value' => $titipan_dipinjam . ' Unit', 'icon' => 'calendar', 'attention' => $titipan_dipinjam > 0, 'href' => BASE_URL . '/mitra/aset_mitra.php'];
        $actions[] = ['label' => 'Kelola Alat Titipan', 'btn' => 'Alat Titipan Saya', 'icon' => 'box', 'href' => BASE_URL . '/mitra/aset_mitra.php', 'primary' => true];
        $quick_links[] = ['label' => 'Alat Titipan', 'icon' => 'box', 'href' => BASE_URL . '/mitra/aset_mitra.php'];
        $quick_links[] = ['label' => 'Log Inspeksi', 'icon' => 'clipboard', 'href' => BASE_URL . '/admin/riwayat_inspeksi.php'];
    }

    if (!empty($user['is_teknisi'])) {
        $q_antre = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE status_tiket = 'Menunggu Diagnosis'");
        $antrean = (int)(mysqli_fetch_assoc($q_antre)['total'] ?? 0);

        $stmt_tk = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE id_teknisi = ? AND status_tiket NOT IN ('Selesai', 'Dibatalkan')");
        mysqli_stmt_bind_param($stmt_tk, 'i', $id_user);
        mysqli_stmt_execute($stmt_tk);
        $tugas_teknisi = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tk))['total'] ?? 0);
        mysqli_stmt_close($stmt_tk);

        $cards[] = ['label' => 'Antrean Servis', 'value' => $antrean . ' Tiket', 'icon' => 'alert-triangle', 'attention' => $antrean > 0, 'href' => BASE_URL . '/mitra/antrean_teknisi.php'];
        $cards[] = ['label' => 'Sedang Ditangani', 'value' => $tugas_teknisi . ' Tiket', 'icon' => 'wrench', 'attention' => $tugas_teknisi > 0, 'href' => BASE_URL . '/mitra/antrean_teknisi.php'];
        $actions[] = ['label' => 'Perbaikan Warga', 'btn' => 'Meja Kerja Teknisi', 'icon' => 'wrench', 'href' => BASE_URL . '/mitra/antrean_teknisi.php', 'primary' => empty($actions)];
        $quick_links[] = ['label' => 'Meja Teknisi', 'icon' => 'wrench', 'href' => BASE_URL . '/mitra/antrean_teknisi.php'];
    }

    $quick_links[] = ['label' => 'Profil Mitra', 'icon' => 'user', 'href' => BASE_URL . '/auth/profil.php'];
    if (empty($actions)) {
        $actions[] = ['label' => 'Pinjam Perkakas', 'btn' => 'Lihat Katalog', 'icon' => 'grid', 'href' => BASE_URL . '/katalog/index.php', 'primary' => true];
    }
} else {
    $alat_tersedia = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM aset_alat WHERE status_alat = 'Tersedia'"))['total'] ?? 0);
    $pinjam_aktif = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE status_peminjaman IN ('Dipesan', 'Dipinjam')"))['total'] ?? 0);
    $tiket_terbuka = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE status_tiket NOT IN ('Selesai', 'Dibatalkan')"))['total'] ?? 0);
    $alat_pending = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM aset_alat WHERE status_alat = 'Menunggu Verifikasi'"))['total'] ?? 0);
    $teknisi_pending = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE is_teknisi = 1 AND is_verified_teknisi = 0"))['total'] ?? 0);
    $total_verif = $alat_pending + $teknisi_pending;

    $cards = [
        ['label' => 'Alat Tersedia', 'value' => $alat_tersedia . ' Unit', 'icon' => 'box', 'attention' => false, 'href' => BASE_URL . '/admin/kelola_aset.php'],
        ['label' => 'Pinjaman Aktif', 'value' => $pinjam_aktif . ' Transaksi', 'icon' => 'calendar', 'attention' => $pinjam_aktif > 0, 'href' => BASE_URL . '/admin/kelola_transaksi.php'],
        ['label' => 'Tiket Terbuka', 'value' => $tiket_terbuka . ' Tiket', 'icon' => 'wrench', 'attention' => $tiket_terbuka > 0, 'href' => BASE_URL . '/admin/kelola_tiket.php'],
        ['label' => 'Perlu Verifikasi', 'value' => $total_verif . ' Antrean', 'icon' => 'users', 'attention' => $total_verif > 0, 'href' => BASE_URL . '/admin/kelola_user.php'],
    ];

    $actions = [
        ['label' => 'Inventaris & Verifikasi Alat', 'btn' => 'Inventaris Alat', 'icon' => 'box', 'href' => BASE_URL . '/admin/kelola_aset.php', 'primary' => true],
        ['label' => 'Transaksi & Inspeksi Pinjam', 'btn' => 'Transaksi Pinjam', 'icon' => 'clipboard', 'href' => BASE_URL . '/admin/kelola_transaksi.php', 'primary' => false],
    ];

    $quick_links = [
        ['label' => 'Pengguna', 'icon' => 'users', 'href' => BASE_URL . '/admin/kelola_user.php'],
        ['label' => 'Kategori', 'icon' => 'tag', 'href' => BASE_URL . '/admin/kelola_kategori.php'],
        ['label' => 'Inventaris', 'icon' => 'box', 'href' => BASE_URL . '/admin/kelola_aset.php'],
        ['label' => 'Transaksi', 'icon' => 'calendar', 'href' => BASE_URL . '/admin/kelola_transaksi.php'],
        ['label' => 'Log Inspeksi', 'icon' => 'clipboard', 'href' => BASE_URL . '/admin/riwayat_inspeksi.php'],
        ['label' => 'Tiket Servis', 'icon' => 'wrench', 'href' => BASE_URL . '/admin/kelola_tiket.php'],
    ];
}

$stat_cols_class = count($cards) >= 4 ? 'pk-dash-stats--4' : (count($cards) === 3 ? 'pk-dash-stats--3' : '');
$page_title = 'Home';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Halo, <?= e($user['nama']) ?></h1>
</div>

<section class="pk-dash-stats <?= $stat_cols_class ?> mb-4" aria-label="Ringkasan Utama">
    <?php foreach ($cards as $c): ?>
        <a href="<?= e($c['href']) ?>" class="pk-stat-card<?= !empty($c['attention']) ? ' is-attention' : '' ?>">
            <div class="pk-stat-top">
                <span class="pk-stat-label"><?= e($c['label']) ?></span>
                <span class="pk-stat-icon"><?= svg_icon($c['icon'], 15) ?></span>
            </div>
            <div class="pk-stat-value"><?= e($c['value']) ?></div>
        </a>
    <?php endforeach; ?>
</section>

<section class="pk-grid pk-grid--2 mb-4" aria-label="Aksi Cepat">
    <?php foreach ($actions as $act): ?>
        <div class="pk-action-card">
            <div class="pk-action-head">
                <span class="pk-action-icon"><?= svg_icon($act['icon'], 18) ?></span>
                <strong><?= e($act['label']) ?></strong>
            </div>
            <a href="<?= e($act['href']) ?>" class="btn <?= $act['primary'] ? 'btn-primary' : 'btn-outline' ?>">
                <?= e($act['btn']) ?>
            </a>
        </div>
    <?php endforeach; ?>
</section>

<?php if (!empty($quick_links)): ?>
<section aria-label="Pintasan Menu" class="pk-mobile-only">
    <div class="pk-quick-grid">
        <?php foreach ($quick_links as $ql): ?>
            <a href="<?= e($ql['href']) ?>" class="pk-quick-tile">
                <?= svg_icon($ql['icon'], 18) ?>
                <span><?= e($ql['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
