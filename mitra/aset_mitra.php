<?php
/**
 * Halaman alat titipan Mitra Kontributor.
 *
 * Tujuan : Memungkinkan Mitra mengajukan unit alat baru, memantau status
 *          verifikasinya, dan melihat riwayat peminjaman alat miliknya.
 * Akses  : Mitra Kontributor Alat (role 'mitra' + is_kontributor).
 * Alur   : POST -> validasi isian & kontak -> unggah foto -> simpan sebagai
 *          "Menunggu Verifikasi" -> flash + redirect. GET memuat daftar alat
 *          dan riwayat peminjaman terakhir.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_role(['mitra']);
refresh_user_session($conn);

if (empty($_SESSION['user']['is_kontributor'])) {
    set_flash('warning', 'Halaman ini khusus Mitra Kontributor Alat.');
    header('Location: ' . BASE_URL . '/auth/profil.php');
    exit;
}

$id_mitra = (int)$_SESSION['user']['id_user'];
$modal_fb = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token()) {
    set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_kategori     = (int)($_POST['id_kategori'] ?? 0);
    $nama_alat       = trim($_POST['nama_alat'] ?? '');
    $deskripsi       = trim($_POST['deskripsi'] ?? '');
    $harga_beli_baru = (float)($_POST['harga_beli_baru'] ?? 0);
    $biaya_perawatan = (float)($_POST['biaya_perawatan'] ?? 0);
    $max_hari_pinjam = max(1, (int)($_POST['max_hari_pinjam'] ?? 3));
    $min_skor        = max(50, (int)($_POST['min_skor'] ?? 70));
    $status_awal     = 'Menunggu Verifikasi';

    if ($nama_alat === '' || $deskripsi === '' || $id_kategori <= 0 || $harga_beli_baru <= 0) {
        $modal_fb = ['type' => 'warning', 'message' => 'Mohon lengkapi nama alat, kategori, harga baru, dan deskripsi.'];
    } else {
        $kontak_res = lengkapi_kontak_user($conn, $_SESSION['user'], $_POST);
        if (!$kontak_res['ok']) {
            $modal_fb = ['type' => 'warning', 'message' => $kontak_res['message']];
        } else {
            $foto_filename = 'default_alat.jpg';
            $upload_ok     = true;
            if (isset($_FILES['foto_alat']) && $_FILES['foto_alat']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = upload_gambar($_FILES['foto_alat'], UPLOAD_ALAT_PATH, 'mitra_');
                if (!$up['success']) {
                    $modal_fb = ['type' => 'danger', 'message' => $up['message']];
                    $upload_ok = false;
                } else {
                    $foto_filename = $up['filename'];
                }
            }

            if ($upload_ok) {
                $stmt = mysqli_prepare($conn, 'INSERT INTO aset_alat (id_kategori, id_pemilik, nama_alat, deskripsi, foto_alat, harga_beli_baru, biaya_perawatan, max_hari_pinjam, min_skor, status_alat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iisssddiis', $id_kategori, $id_mitra, $nama_alat, $deskripsi, $foto_filename, $harga_beli_baru, $biaya_perawatan, $max_hari_pinjam, $min_skor, $status_awal);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                set_flash('success', 'Pengajuan titip alat dikirim dan menunggu verifikasi Admin Hub.');
                header('Location: ' . BASE_URL . '/mitra/aset_mitra.php');
                exit;
            }
        }
    }
}

$modal_open = ($modal_fb !== null) || isset($_GET['open']);
$form_val   = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];

$kategori_options = pk_stmt_all($conn, 'SELECT id_kategori, nama_kategori FROM kategori_alat ORDER BY nama_kategori ASC');

$mitra_alat_list = pk_stmt_all(
    $conn,
    "SELECT a.*, k.nama_kategori,
        (SELECT COUNT(*) FROM peminjaman p WHERE p.id_alat = a.id_alat AND p.status_peminjaman = 'Selesai') AS total_dipinjam
     FROM aset_alat a
     JOIN kategori_alat k ON a.id_kategori = k.id_kategori
     WHERE a.id_pemilik = ?
     ORDER BY a.id_alat DESC",
    'i',
    [$id_mitra]
);

$hist_list = pk_stmt_all(
    $conn,
    "SELECT p.*, a.nama_alat, u.nama AS nama_peminjam
     FROM peminjaman p
     JOIN aset_alat a ON p.id_alat = a.id_alat
     JOIN users u ON p.id_warga = u.id_user
     WHERE a.id_pemilik = ?
     ORDER BY p.created_at DESC LIMIT 10",
    'i',
    [$id_mitra]
);

$page_title = 'Alat Titipan Saya';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Alat Titipan Saya</h1>
    <div class="btn-group-wrap">
        <button type="button" class="btn btn-primary" data-modal-target="#modalTitipAset">
            <?= svg_icon('plus', 16) ?>
            <span>Titip Perkakas</span>
        </button>
        <a href="<?= BASE_URL ?>/admin/riwayat_inspeksi.php" class="btn btn-outline">
            <?= svg_icon('clipboard', 16) ?>
            <span>Log Inspeksi</span>
        </a>
    </div>
</div>

<section class="pk-table-card" aria-labelledby="daftar-titipan-heading">
    <div class="pk-table-card-head">
        <h2 id="daftar-titipan-heading" class="pk-card-title">
            <?= svg_icon('box', 18) ?>
            <span>Daftar Alat Saya</span>
        </h2>
    </div>

    <?php if (count($mitra_alat_list) === 0): ?>
        <div class="pk-empty-state">
            <div class="pk-empty-icon"><?= svg_icon('box', 22) ?></div>
            <h3>Belum Ada Alat Titipan</h3>
        </div>
    <?php else: ?>
        <div class="pk-table-scroll">
            <table class="pk-table">
                <thead>
                    <tr>
                        <th>Alat</th>
                        <th>Ketentuan</th>
                        <th>Status</th>
                        <th>Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mitra_alat_list as $a): ?>
                        <tr>
                            <td class="pk-td-head">
                                <div class="pk-td-head-row">
                                    <div class="pk-td-head-main">
                                        <strong><?= e($a['nama_alat']) ?></strong><br>
                                        <span class="pk-td-sub"><?= e($a['nama_kategori']) ?></span>
                                    </div>
                                    <span class="pk-mobile-badge"><?= badge_status_alat($a['status_alat']) ?></span>
                                </div>
                            </td>
                            <td data-label="Ketentuan">
                                Maks <?= (int)$a['max_hari_pinjam'] ?> hr &bull; Skor &ge; <?= (int)$a['min_skor'] ?>
                            </td>
                            <td class="pk-desktop-cell" data-label="Status"><?= badge_status_alat($a['status_alat']) ?></td>
                            <td data-label="Dipinjam"><strong><?= (int)$a['total_dipinjam'] ?>x</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="pk-table-card" aria-labelledby="riwayat-pakai-heading">
    <div class="pk-table-card-head">
        <h2 id="riwayat-pakai-heading" class="pk-card-title">
            <?= svg_icon('calendar', 18) ?>
            <span>Riwayat Peminjaman</span>
        </h2>
    </div>

    <?php if (count($hist_list) === 0): ?>
        <div class="pk-empty-state">
            <div class="text-muted">Belum ada transaksi peminjaman.</div>
        </div>
    <?php else: ?>
        <div class="pk-table-scroll">
            <table class="pk-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Alat &amp; Peminjam</th>
                        <th>Jadwal</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hist_list as $h): ?>
                        <tr>
                            <td class="pk-td-head">
                                <div class="pk-td-head-row">
                                    <div class="pk-td-head-main">
                                        <strong><?= e($h['kode_booking']) ?></strong>
                                        <div class="pk-mobile-inline"><br><?= e($h['nama_alat']) ?></div>
                                    </div>
                                    <span class="pk-mobile-badge"><?= badge_status_pinjam($h['status_peminjaman']) ?></span>
                                </div>
                            </td>
                            <td data-label="Peminjam">
                                <span class="pk-desktop-only-inline"><?= e($h['nama_alat']) ?><br></span>
                                <span><?= e($h['nama_peminjam']) ?></span>
                            </td>
                            <td data-label="Jadwal"><?= e(format_tanggal($h['tgl_mulai'])) ?> - <?= e(format_tanggal($h['tgl_selesai'])) ?></td>
                            <td class="pk-desktop-cell" data-label="Status"><?= badge_status_pinjam($h['status_peminjaman']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div id="modalTitipAset"
    class="pk-modal<?= $modal_open ? ' open' : '' ?>"
    role="dialog"
    aria-modal="true"
    aria-labelledby="titip-heading"
    aria-hidden="<?= $modal_open ? 'false' : 'true' ?>">
    <div class="pk-modal-dialog pk-modal-dialog--wide">
        <div class="pk-card-header">
            <h2 id="titip-heading" class="pk-card-title">
                <?= svg_icon('plus', 18) ?>
                <span>Titip Perkakas Baru</span>
            </h2>
            <button type="button" class="btn btn-muted btn-icon" data-modal-close aria-label="Tutup form">
                <?= svg_icon('x', 16) ?>
            </button>
        </div>

        <?php if ($modal_fb): ?>
            <?= render_alert((string)$modal_fb['type'], (string)$modal_fb['message']) ?>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/mitra/aset_mitra.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="nama_alat" class="form-label">Nama &amp; Merek Alat</label>
                    <input type="text" id="nama_alat" name="nama_alat" class="form-control" value="<?= e($form_val['nama_alat'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="id_kategori" class="form-label">Kategori</label>
                    <select id="id_kategori" name="id_kategori" class="form-select" required>
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($kategori_options as $k): ?>
                            <option value="<?= (int)$k['id_kategori'] ?>" <?= ((int)($form_val['id_kategori'] ?? 0) === (int)$k['id_kategori']) ? 'selected' : '' ?>>
                                <?= e($k['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="harga_beli_baru" class="form-label">Harga Baru (Rp)</label>
                    <input type="number" step="1000" min="10000" id="harga_beli_baru" name="harga_beli_baru" class="form-control" value="<?= e($form_val['harga_beli_baru'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="biaya_perawatan" class="form-label">Biaya Rawat (Rp)</label>
                    <input type="number" step="500" min="0" id="biaya_perawatan" name="biaya_perawatan" class="form-control" value="<?= e($form_val['biaya_perawatan'] ?? '0') ?>" required>
                </div>
            </div>

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="max_hari_pinjam" class="form-label">Maks. Pinjam (Hari)</label>
                    <input type="number" min="1" max="14" id="max_hari_pinjam" name="max_hari_pinjam" class="form-control" value="<?= e($form_val['max_hari_pinjam'] ?? '3') ?>" required>
                </div>

                <div class="form-group">
                    <label for="min_skor" class="form-label">Skor Minimal</label>
                    <input type="number" min="50" max="150" id="min_skor" name="min_skor" class="form-control" value="<?= e($form_val['min_skor'] ?? '80') ?>" required>
                </div>
            </div>

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="deskripsi" class="form-label">Spesifikasi &amp; Kelengkapan</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-control" required><?= e($form_val['deskripsi'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="foto_alat" class="form-label">Foto Alat (Opsional, maks. 2 MB)</label>
                    <input type="file" id="foto_alat" name="foto_alat" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                </div>
            </div>

            <?= render_kontak_fields_if_needed($_SESSION['user'], $_POST) ?>

            <button type="submit" class="btn btn-primary w-100">
                <?= svg_icon('check-circle', 16) ?>
                <span>Ajukan Titip Alat</span>
            </button>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>