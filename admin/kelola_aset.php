<?php
/**
 * Halaman inventaris & verifikasi alat (panel Admin Hub).
 *
 * Tujuan : Mengelola seluruh unit aset, menyetujui alat titipan Mitra,
 *          mengubah status perawatan, serta menambah/mengubah/menghapus alat.
 * Akses  : Admin Hub (role 'admin').
 * Alur   : Aksi POST (approve/toggle/delete/simpan) -> validasi & unggah foto ->
 *          query database -> flash + redirect. GET menampilkan antrean verifikasi,
 *          modal form, dan tabel inventaris.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

$admin_id = (int)$_SESSION['user']['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
        header('Location: ' . BASE_URL . '/admin/kelola_aset.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Setujui alat titipan Mitra agar tampil di katalog.
    if ($action === 'approve_mitra_tool') {
        $id_alat = (int)($_POST['id_alat'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE aset_alat SET status_alat = 'Tersedia' WHERE id_alat = ? AND status_alat = 'Menunggu Verifikasi'");
        mysqli_stmt_bind_param($stmt, 'i', $id_alat);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'Alat titipan Mitra berhasil disetujui dan diaktifkan.');
        header('Location: ' . BASE_URL . '/admin/kelola_aset.php');
        exit;
    }

    // Aktifkan/nonaktifkan status perawatan unit.
    if ($action === 'toggle_maintenance') {
        $id_alat       = (int)($_POST['id_alat'] ?? 0);
        $target_status = ($_POST['target_status'] ?? '') === 'Perawatan' ? 'Perawatan' : 'Tersedia';
        $stmt = mysqli_prepare($conn, "UPDATE aset_alat SET status_alat = ? WHERE id_alat = ? AND status_alat IN ('Tersedia', 'Perawatan')");
        mysqli_stmt_bind_param($stmt, 'si', $target_status, $id_alat);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash($target_status === 'Tersedia' ? 'success' : 'warning', 'Status alat diubah menjadi ' . $target_status . '.');
        header('Location: ' . BASE_URL . '/admin/kelola_aset.php');
        exit;
    }

    // Hapus unit hanya jika belum pernah masuk riwayat peminjaman.
    if ($action === 'delete') {
        $id_alat = (int)($_POST['id_alat'] ?? 0);
        $row = pk_stmt_one($conn, 'SELECT COUNT(*) AS total FROM peminjaman WHERE id_alat = ?', 'i', [$id_alat]);
        $used = (int)($row['total'] ?? 0);

        if ($used > 0) {
            set_flash('danger', 'Unit sudah memiliki riwayat peminjaman dan tidak dapat dihapus.');
        } else {
            $del = mysqli_prepare($conn, 'DELETE FROM aset_alat WHERE id_alat = ?');
            mysqli_stmt_bind_param($del, 'i', $id_alat);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            set_flash('success', 'Unit alat berhasil dihapus.');
        }
        header('Location: ' . BASE_URL . '/admin/kelola_aset.php');
        exit;
    }

    // Simpan alat baru atau perubahan data alat.
    if ($action === 'create' || $action === 'update') {
        $id_alat          = (int)($_POST['id_alat'] ?? 0);
        $id_kategori      = (int)($_POST['id_kategori'] ?? 0);
        $nama_alat        = trim($_POST['nama_alat'] ?? '');
        $deskripsi        = trim($_POST['deskripsi'] ?? '');
        $harga_beli_baru  = (float)($_POST['harga_beli_baru'] ?? 0);
        $biaya_perawatan  = (float)($_POST['biaya_perawatan'] ?? 0);
        $max_hari_pinjam  = max(1, (int)($_POST['max_hari_pinjam'] ?? 3));
        $min_skor         = max(0, (int)($_POST['min_skor'] ?? 50));
        $status_alat      = $_POST['status_alat'] ?? 'Tersedia';

        if (!in_array($status_alat, ['Menunggu Verifikasi', 'Tersedia', 'Dipinjam', 'Perawatan'], true)) {
            $status_alat = 'Tersedia';
        }

        if ($nama_alat === '' || $deskripsi === '' || $id_kategori <= 0) {
            set_modal_feedback('warning', 'Nama alat, kategori, dan deskripsi wajib diisi.', $_POST);
            $err_q = ($action === 'update' && $id_alat > 0) ? '?edit=' . $id_alat : '?open=form';
            header('Location: ' . BASE_URL . '/admin/kelola_aset.php' . $err_q);
            exit;
        }

        // Foto lama dipertahankan bila admin tidak mengunggah berkas baru.
        $foto_filename = $_POST['foto_lama'] ?? 'default_alat.jpg';
        if (isset($_FILES['foto_alat']) && $_FILES['foto_alat']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = upload_gambar($_FILES['foto_alat'], UPLOAD_ALAT_PATH, 'alat_');
            if (!$up['success']) {
                set_modal_feedback('danger', $up['message'], $_POST);
                $err_q = ($action === 'update' && $id_alat > 0) ? '?edit=' . $id_alat : '?open=form';
                header('Location: ' . BASE_URL . '/admin/kelola_aset.php' . $err_q);
                exit;
            }
            $foto_filename = $up['filename'];
        }

        if ($action === 'create') {
            $stmt = mysqli_prepare($conn, 'INSERT INTO aset_alat (id_kategori, id_pemilik, nama_alat, deskripsi, foto_alat, harga_beli_baru, biaya_perawatan, max_hari_pinjam, min_skor, status_alat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'iisssddiis', $id_kategori, $admin_id, $nama_alat, $deskripsi, $foto_filename, $harga_beli_baru, $biaya_perawatan, $max_hari_pinjam, $min_skor, $status_alat);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            set_flash('success', 'Unit perkakas baru berhasil ditambahkan.');
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE aset_alat SET id_kategori = ?, nama_alat = ?, deskripsi = ?, foto_alat = ?, harga_beli_baru = ?, biaya_perawatan = ?, max_hari_pinjam = ?, min_skor = ?, status_alat = ? WHERE id_alat = ?');
            mysqli_stmt_bind_param($stmt, 'isssddiisi', $id_kategori, $nama_alat, $deskripsi, $foto_filename, $harga_beli_baru, $biaya_perawatan, $max_hari_pinjam, $min_skor, $status_alat, $id_alat);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            set_flash('success', 'Data alat berhasil diperbarui.');
        }
        header('Location: ' . BASE_URL . '/admin/kelola_aset.php');
        exit;
    }
}

// Muat data alat yang sedang diedit (jika ada).
$edit_alat = null;
if (isset($_GET['edit'])) {
    $edit_id   = (int)$_GET['edit'];
    $edit_alat = pk_stmt_one($conn, 'SELECT * FROM aset_alat WHERE id_alat = ? LIMIT 1', 'i', [$edit_id]);
    if (empty($edit_alat)) {
        $edit_alat = null;
    }
}

$modal_fb   = get_modal_feedback();
$form_val   = !empty($modal_fb['old']) ? $modal_fb['old'] : ($edit_alat ?? []);
$modal_open = ($edit_alat !== null) || isset($_GET['open']) || ($modal_fb !== null);

$kategori_options = pk_stmt_all($conn, 'SELECT id_kategori, nama_kategori FROM kategori_alat ORDER BY nama_kategori ASC');

$pending_list = pk_stmt_all(
    $conn,
    "SELECT a.*, k.nama_kategori, u.nama AS nama_pemilik, u.no_hp
     FROM aset_alat a
     JOIN kategori_alat k ON a.id_kategori = k.id_kategori
     JOIN users u ON a.id_pemilik = u.id_user
     WHERE a.status_alat = 'Menunggu Verifikasi'
     ORDER BY a.created_at ASC"
);

$all_aset = pk_stmt_all(
    $conn,
    "SELECT a.*, k.nama_kategori, u.nama AS nama_pemilik, u.role AS role_pemilik,
        (SELECT COUNT(*) FROM peminjaman p WHERE p.id_alat = a.id_alat) AS jml_pinjam
     FROM aset_alat a
     JOIN kategori_alat k ON a.id_kategori = k.id_kategori
     JOIN users u ON a.id_pemilik = u.id_user
     ORDER BY a.id_alat DESC"
);

$page_title = 'Inventaris & Verifikasi Alat';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Inventaris &amp; Verifikasi Alat</h1>
    <button type="button" class="btn btn-primary" data-modal-target="#modalAset">
        <?= svg_icon('plus', 16) ?>
        <span>Tambah Alat</span>
    </button>
</div>

<?php if (count($pending_list) > 0): ?>
<section class="pk-table-card" aria-labelledby="pending-mitra-heading">
    <div class="pk-table-card-head">
        <h2 id="pending-mitra-heading" class="pk-card-title">
            <?= svg_icon('alert-triangle', 18) ?>
            <span>Antrean Verifikasi Alat Mitra (<?= count($pending_list) ?>)</span>
        </h2>
    </div>

    <div class="pk-table-scroll">
        <table class="pk-table">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Alat</th>
                    <th>Mitra</th>
                    <th>Ketentuan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_list as $p): ?>
                    <tr class="pk-tr-media">
                        <td class="pk-td-media">
                            <img src="<?= e(get_gambar_alat($p['foto_alat'], $p['nama_alat'])) ?>" alt="<?= e($p['nama_alat']) ?>" class="pk-thumb-table">
                        </td>
                        <td class="pk-td-head">
                            <strong><?= e($p['nama_alat']) ?></strong>
                            <div><span class="pk-tag mt-1"><?= e($p['nama_kategori']) ?></span></div>
                        </td>
                        <td data-label="Mitra">
                            <strong><?= e(format_nama_pengguna((string)$p['nama_pemilik'])) ?></strong><br>
                            <span class="pk-td-sub"><?= e($p['no_hp']) ?></span>
                        </td>
                        <td data-label="Ketentuan">
                            <div>Beli <?= rupiah($p['harga_beli_baru']) ?> &bull; Rawat <?= (float)$p['biaya_perawatan'] <= 0 ? 'Gratis' : rupiah($p['biaya_perawatan']) ?></div>
                            <div class="text-muted">Maks <?= (int)$p['max_hari_pinjam'] ?> hr &bull; Skor &ge; <?= (int)$p['min_skor'] ?></div>
                        </td>
                        <td class="pk-td-actions">
                            <div class="btn-group-wrap">
                                <form method="post" action="<?= BASE_URL ?>/admin/kelola_aset.php" data-confirm="Setujui <?= e($p['nama_alat']) ?> tampil di katalog?" data-confirm-title="Setujui Alat" data-confirm-btn="Setujui" data-confirm-tone="ok">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="approve_mitra_tool">
                                    <input type="hidden" name="id_alat" value="<?= (int)$p['id_alat'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-icon" title="Setujui" aria-label="Setujui <?= e($p['nama_alat']) ?>">
                                        <?= svg_icon('check-circle', 16) ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= BASE_URL ?>/admin/kelola_aset.php" data-confirm="Tolak dan hapus pengajuan <?= e($p['nama_alat']) ?>?" data-confirm-title="Tolak Pengajuan" data-confirm-btn="Tolak &amp; Hapus" data-confirm-tone="danger">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_alat" value="<?= (int)$p['id_alat'] ?>">
                                    <button type="submit" class="btn btn-danger btn-icon" title="Tolak" aria-label="Tolak <?= e($p['nama_alat']) ?>">
                                        <?= svg_icon('x', 16) ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<div id="modalAset"
     class="pk-modal<?= $modal_open ? ' open' : '' ?>"
     role="dialog"
     aria-modal="true"
     aria-labelledby="form-aset-heading"
     aria-hidden="<?= $modal_open ? 'false' : 'true' ?>"<?= $edit_alat ? ' data-cancel-url="' . BASE_URL . '/admin/kelola_aset.php"' : '' ?>>
    <div class="pk-modal-dialog pk-modal-dialog--wide">
        <div class="pk-card-header">
            <h2 id="form-aset-heading" class="pk-card-title">
                <?= svg_icon($edit_alat ? 'clipboard' : 'plus', 18) ?>
                <span><?= $edit_alat ? 'Edit Alat #' . (int)$edit_alat['id_alat'] : 'Tambah Alat' ?></span>
            </h2>
            <button type="button" class="btn btn-muted btn-icon" data-modal-close aria-label="Tutup form">
                <?= svg_icon('x', 16) ?>
            </button>
        </div>

        <?php if ($modal_fb): ?>
            <?= render_alert((string)$modal_fb['type'], (string)$modal_fb['message']) ?>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/admin/kelola_aset.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit_alat ? 'update' : 'create' ?>">
            <?php if ($edit_alat): ?>
                <input type="hidden" name="id_alat" value="<?= (int)$edit_alat['id_alat'] ?>">
                <input type="hidden" name="foto_lama" value="<?= e($edit_alat['foto_alat']) ?>">
            <?php endif; ?>

            <div class="pk-grid pk-grid--3">
                <div class="form-group">
                    <label for="nama_alat" class="form-label">Nama Alat</label>
                    <input type="text" id="nama_alat" name="nama_alat" class="form-control" value="<?= e($form_val['nama_alat'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="id_kategori" class="form-label">Kategori</label>
                    <select id="id_kategori" name="id_kategori" class="form-select" required>
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($kategori_options as $kat): ?>
                            <option value="<?= (int)$kat['id_kategori'] ?>" <?= ((int)($form_val['id_kategori'] ?? 0) === (int)$kat['id_kategori']) ? 'selected' : '' ?>>
                                <?= e($kat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status_alat" class="form-label">Status</label>
                    <select id="status_alat" name="status_alat" class="form-select">
                        <?php
                        $statuses = ['Tersedia', 'Perawatan', 'Menunggu Verifikasi', 'Dipinjam'];
                        $curr_st  = $form_val['status_alat'] ?? 'Tersedia';
                        foreach ($statuses as $st):
                        ?>
                            <option value="<?= e($st) ?>" <?= $curr_st === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="pk-grid pk-grid--4">
                <div class="form-group">
                    <label for="harga_beli_baru" class="form-label">Harga Baru (Rp)</label>
                    <input type="number" step="1000" min="0" id="harga_beli_baru" name="harga_beli_baru" class="form-control" value="<?= e(isset($form_val['harga_beli_baru']) ? (int)$form_val['harga_beli_baru'] : '600000') ?>" required>
                </div>

                <div class="form-group">
                    <label for="biaya_perawatan" class="form-label">Biaya Rawat (Rp)</label>
                    <input type="number" step="500" min="0" id="biaya_perawatan" name="biaya_perawatan" class="form-control" value="<?= e(isset($form_val['biaya_perawatan']) ? (int)$form_val['biaya_perawatan'] : '0') ?>" required>
                </div>

                <div class="form-group">
                    <label for="max_hari_pinjam" class="form-label">Maks. Pinjam (Hari)</label>
                    <input type="number" min="1" max="30" id="max_hari_pinjam" name="max_hari_pinjam" class="form-control" value="<?= e($form_val['max_hari_pinjam'] ?? '3') ?>" required>
                </div>

                <div class="form-group">
                    <label for="min_skor" class="form-label">Skor Minimal</label>
                    <input type="number" min="0" max="200" id="min_skor" name="min_skor" class="form-control" value="<?= e($form_val['min_skor'] ?? '50') ?>" required>
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

            <button type="submit" class="btn btn-primary w-100">
                <?= svg_icon('check-circle', 16) ?>
                <span><?= $edit_alat ? 'Simpan Perubahan' : 'Simpan Alat' ?></span>
            </button>
        </form>
    </div>
</div>

<section class="pk-table-card" aria-labelledby="all-aset-heading">
    <div class="pk-table-card-head">
        <h2 id="all-aset-heading" class="pk-card-title">
            <?= svg_icon('box', 18) ?>
            <span>Daftar Inventaris</span>
        </h2>
    </div>

    <div class="pk-table-scroll">
        <table class="pk-table">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Alat</th>
                    <th>Pemilik</th>
                    <th>Ketentuan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($all_aset) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">Belum ada unit alat terdaftar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_aset as $row): ?>
                        <tr class="pk-tr-media">
                            <td class="pk-td-media">
                                <img src="<?= e(get_gambar_alat($row['foto_alat'], $row['nama_alat'])) ?>" alt="<?= e($row['nama_alat']) ?>" class="pk-thumb-table">
                            </td>
                            <td class="pk-td-head">
                                <div class="pk-td-head-row">
                                    <div class="pk-td-head-main">
                                        <strong><?= e($row['nama_alat']) ?></strong>
                                        <div><span class="pk-tag mt-1"><?= e($row['nama_kategori']) ?></span></div>
                                    </div>
                                    <span class="pk-mobile-badge"><?= badge_status_alat($row['status_alat']) ?></span>
                                </div>
                            </td>
                            <td data-label="Pemilik">
                                <strong><?= e(format_nama_pengguna((string)$row['nama_pemilik'])) ?></strong>
                                <span class="pk-td-sub"> &bull; <?= $row['role_pemilik'] === 'admin' ? 'Hub' : 'Mitra' ?></span>
                            </td>
                            <td data-label="Ketentuan">
                                <div>Beli <?= rupiah($row['harga_beli_baru']) ?> &bull; Rawat <?= (float)$row['biaya_perawatan'] <= 0 ? 'Gratis' : rupiah($row['biaya_perawatan']) ?></div>
                                <div class="text-muted">Maks <?= (int)$row['max_hari_pinjam'] ?> hr &bull; Skor &ge; <?= (int)$row['min_skor'] ?></div>
                            </td>
                            <td class="pk-desktop-cell" data-label="Status"><?= badge_status_alat($row['status_alat']) ?></td>
                            <td class="pk-td-actions">
                                <div class="btn-group-wrap">
                                    <a href="<?= BASE_URL ?>/admin/kelola_aset.php?edit=<?= (int)$row['id_alat'] ?>" class="btn btn-outline btn-icon" title="Edit" aria-label="Edit <?= e($row['nama_alat']) ?>">
                                        <?= svg_icon('edit', 16) ?>
                                    </a>

                                    <?php if ($row['status_alat'] === 'Tersedia'): ?>
                                        <form method="post" action="<?= BASE_URL ?>/admin/kelola_aset.php" data-confirm="Ubah status <?= e($row['nama_alat']) ?> ke Perawatan?" data-confirm-title="Ubah ke Perawatan" data-confirm-btn="Perawatan" data-confirm-tone="warm">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_maintenance">
                                            <input type="hidden" name="id_alat" value="<?= (int)$row['id_alat'] ?>">
                                            <input type="hidden" name="target_status" value="Perawatan">
                                            <button type="submit" class="btn btn-warm btn-icon" title="Perawatan" aria-label="Perawatan <?= e($row['nama_alat']) ?>">
                                                <?= svg_icon('wrench', 16) ?>
                                            </button>
                                        </form>
                                    <?php elseif ($row['status_alat'] === 'Perawatan'): ?>
                                        <form method="post" action="<?= BASE_URL ?>/admin/kelola_aset.php" data-confirm="Aktifkan kembali <?= e($row['nama_alat']) ?> menjadi Tersedia?" data-confirm-title="Aktifkan Alat" data-confirm-btn="Aktifkan" data-confirm-tone="ok">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_maintenance">
                                            <input type="hidden" name="id_alat" value="<?= (int)$row['id_alat'] ?>">
                                            <input type="hidden" name="target_status" value="Tersedia">
                                            <button type="submit" class="btn btn-secondary btn-icon" title="Aktifkan" aria-label="Aktifkan <?= e($row['nama_alat']) ?>">
                                                <?= svg_icon('check-circle', 16) ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" action="<?= BASE_URL ?>/admin/kelola_aset.php" data-confirm="Hapus unit <?= e($row['nama_alat']) ?> secara permanen?" data-confirm-title="Hapus Alat" data-confirm-btn="Hapus" data-confirm-tone="danger">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_alat" value="<?= (int)$row['id_alat'] ?>">
                                        <button type="submit" class="btn btn-danger btn-icon" title="Hapus" aria-label="Hapus <?= e($row['nama_alat']) ?>" <?= (int)$row['jml_pinjam'] > 0 ? 'disabled' : '' ?>>
                                            <?= svg_icon('trash', 16) ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
