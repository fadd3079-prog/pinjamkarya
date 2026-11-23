<?php
require_once dirname(__DIR__) . '/config/app.php';
require_login();
refresh_user_session($conn);
bersihkan_ghost_booking($conn);

$user = $_SESSION['user'];
$id_user = (int)$user['id_user'];
$role = (string)$user['role'];

$cards = [];
$actions = [];

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
        ['label' => 'Pinjaman Aktif', 'value' => $pinjam_aktif . ' Alat', 'href' => BASE_URL . '/modul5/riwayat_pinjam.php'],
        ['label' => 'Tiket Servis Aktif', 'value' => $tiket_aktif . ' Tiket', 'href' => BASE_URL . '/modul8/tiket_saya.php'],
        ['label' => 'Skor Kepercayaan', 'value' => (int)$user['skor_kredibilitas'] . ' Poin', 'href' => BASE_URL . '/modul1/profil.php'],
    ];

    $actions = [
        ['label' => 'Pinjam Perkakas', 'btn' => 'Lihat Katalog', 'href' => BASE_URL . '/modul4/katalog.php', 'primary' => true],
        ['label' => 'Perbaiki Barang Rusak', 'btn' => 'Ajukan Perbaikan', 'href' => BASE_URL . '/modul7/buat_tiket.php', 'primary' => false],
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

        $cards[] = ['label' => 'Alat Titipan', 'value' => $total_titipan . ' Unit', 'href' => BASE_URL . '/modul3/aset_mitra.php'];
        $cards[] = ['label' => 'Sedang Dipinjam', 'value' => $titipan_dipinjam . ' Unit', 'href' => BASE_URL . '/modul3/aset_mitra.php'];
        $actions[] = ['label' => 'Kelola Alat Titipan', 'btn' => 'Alat Titipan Saya', 'href' => BASE_URL . '/modul3/aset_mitra.php', 'primary' => true];
    }

    if (!empty($user['is_teknisi'])) {
        $q_antre = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE status_tiket = 'Menunggu Diagnosis'");
        $antrean = (int)(mysqli_fetch_assoc($q_antre)['total'] ?? 0);

        $stmt_tk = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE id_teknisi = ? AND status_tiket NOT IN ('Selesai', 'Dibatalkan')");
        mysqli_stmt_bind_param($stmt_tk, 'i', $id_user);
        mysqli_stmt_execute($stmt_tk);
        $tugas_teknisi = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tk))['total'] ?? 0);
        mysqli_stmt_close($stmt_tk);

        $cards[] = ['label' => 'Antrean Servis', 'value' => $antrean . ' Tiket', 'href' => BASE_URL . '/modul8/antrean_teknisi.php'];
        $cards[] = ['label' => 'Sedang Ditangani', 'value' => $tugas_teknisi . ' Tiket', 'href' => BASE_URL . '/modul8/antrean_teknisi.php'];
        $actions[] = ['label' => 'Perbaikan Warga', 'btn' => 'Meja Kerja Teknisi', 'href' => BASE_URL . '/modul8/antrean_teknisi.php', 'primary' => empty($actions)];
    }

    if (empty($actions)) {
        $actions[] = ['label' => 'Pinjam Perkakas', 'btn' => 'Lihat Katalog', 'href' => BASE_URL . '/modul4/katalog.php', 'primary' => true];
    }
} else {
    $alat_tersedia = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM aset_alat WHERE status_alat = 'Tersedia'"))['total'] ?? 0);
    $pinjam_aktif = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE status_peminjaman IN ('Dipesan', 'Dipinjam')"))['total'] ?? 0);
    $tiket_terbuka = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE status_tiket NOT IN ('Selesai', 'Dibatalkan')"))['total'] ?? 0);
    $alat_pending = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM aset_alat WHERE status_alat = 'Menunggu Verifikasi'"))['total'] ?? 0);
    $teknisi_pending = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE is_teknisi = 1 AND is_verified_teknisi = 0"))['total'] ?? 0);

    $cards = [
        ['label' => 'Alat Tersedia', 'value' => $alat_tersedia . ' Unit', 'href' => BASE_URL . '/modul3/kelola_aset.php'],
        ['label' => 'Pinjaman Aktif', 'value' => $pinjam_aktif . ' Transaksi', 'href' => BASE_URL . '/modul5/kelola_transaksi.php'],
        ['label' => 'Tiket Terbuka', 'value' => $tiket_terbuka . ' Tiket', 'href' => BASE_URL . '/modul8/kelola_tiket.php'],
        ['label' => 'Perlu Verifikasi', 'value' => ($alat_pending + $teknisi_pending) . ' Antrean', 'href' => BASE_URL . '/modul1/kelola_user.php'],
    ];

    $actions = [
        ['label' => 'Inventaris & Verifikasi Alat', 'btn' => 'Inventaris Alat', 'href' => BASE_URL . '/modul3/kelola_aset.php', 'primary' => true],
        ['label' => 'Transaksi & Inspeksi Pinjam', 'btn' => 'Transaksi Pinjam', 'href' => BASE_URL . '/modul5/kelola_transaksi.php', 'primary' => false],
    ];
}

$grid_cols = count($cards) >= 4 ? 'pk-grid--4' : (count($cards) === 2 ? 'pk-grid--2' : 'pk-grid--3');
$page_title = 'Home';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Halo, <?= e($user['nama']) ?></h1>
</div>

<section class="pk-grid <?= $grid_cols ?> mb-4" aria-label="Ringkasan Utama">
    <?php foreach ($cards as $c): ?>
        <a href="<?= e($c['href']) ?>" class="pk-card mb-0">
            <div class="text-muted"><?= e($c['label']) ?></div>
            <div class="fs-xl mt-1"><?= e($c['value']) ?></div>
        </a>
    <?php endforeach; ?>
</section>

<section class="pk-grid pk-grid--2" aria-label="Aksi Cepat">
    <?php foreach ($actions as $act): ?>
        <div class="pk-card mb-0 d-flex align-center justify-between flex-wrap gap-2">
            <strong><?= e($act['label']) ?></strong>
            <a href="<?= e($act['href']) ?>" class="btn <?= $act['primary'] ? 'btn-primary' : 'btn-outline' ?>">
                <?= e($act['btn']) ?>
            </a>
        </div>
    <?php endforeach; ?>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
