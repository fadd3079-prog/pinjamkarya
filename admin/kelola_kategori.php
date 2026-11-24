<?php
/**
 * Halaman master kategori alat.
 *
 * Tujuan : Mengelola data kategori beserta panduan keselamatan, berat rata-rata,
 *          dan estimasi harga baru yang dipakai katalog serta tiket perbaikan.
 * Akses  : Admin Hub (role 'admin').
 * Alur   : Aksi POST (create/update/delete) -> validasi -> query database ->
 *          flash + redirect. GET menampilkan daftar kategori dan modal form.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
        header('Location: ' . BASE_URL . '/admin/kelola_kategori.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id_kategori         = (int)($_POST['id_kategori'] ?? 0);
        $nama_kategori       = trim($_POST['nama_kategori'] ?? '');
        $panduan_keselamatan = trim($_POST['panduan_keselamatan'] ?? '');
        $rata_rata_berat_kg  = (float)($_POST['rata_rata_berat_kg'] ?? 1.00);
        $estimasi_harga_baru = (float)($_POST['estimasi_harga_baru'] ?? 0.00);

        // Validasi isian wajib dan batas nilai parameter kategori.
        if ($nama_kategori === '' || $panduan_keselamatan === '') {
            set_modal_feedback('warning', 'Nama kategori dan panduan keselamatan wajib diisi.', $_POST);
            $err_q = ($action === 'update' && $id_kategori > 0) ? '?edit=' . $id_kategori : '?open=form';
            header('Location: ' . BASE_URL . '/admin/kelola_kategori.php' . $err_q);
            exit;
        }

        if ($rata_rata_berat_kg <= 0 || $estimasi_harga_baru < 0) {
            set_modal_feedback('warning', 'Berat harus lebih dari 0 kg dan harga tidak boleh negatif.', $_POST);
            $err_q = ($action === 'update' && $id_kategori > 0) ? '?edit=' . $id_kategori : '?open=form';
            header('Location: ' . BASE_URL . '/admin/kelola_kategori.php' . $err_q);
            exit;
        }

        if ($action === 'create') {
            $stmt = mysqli_prepare($conn, 'INSERT INTO kategori_alat (nama_kategori, panduan_keselamatan, rata_rata_berat_kg, estimasi_harga_baru) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssdd', $nama_kategori, $panduan_keselamatan, $rata_rata_berat_kg, $estimasi_harga_baru);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            set_flash('success', 'Kategori baru berhasil ditambahkan.');
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE kategori_alat SET nama_kategori = ?, panduan_keselamatan = ?, rata_rata_berat_kg = ?, estimasi_harga_baru = ? WHERE id_kategori = ?');
            mysqli_stmt_bind_param($stmt, 'ssddi', $nama_kategori, $panduan_keselamatan, $rata_rata_berat_kg, $estimasi_harga_baru, $id_kategori);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            set_flash('success', 'Data kategori berhasil diperbarui.');
        }

        header('Location: ' . BASE_URL . '/admin/kelola_kategori.php');
        exit;
    }

    // Kategori hanya boleh dihapus bila tidak dipakai alat atau tiket.
    if ($action === 'delete') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);

        $row_alat  = pk_stmt_one($conn, 'SELECT COUNT(*) AS jml FROM aset_alat WHERE id_kategori = ?', 'i', [$id_kategori]);
        $row_tiket = pk_stmt_one($conn, 'SELECT COUNT(*) AS jml FROM tiket_perbaikan WHERE id_kategori = ?', 'i', [$id_kategori]);
        $c1 = (int)($row_alat['jml'] ?? 0);
        $c2 = (int)($row_tiket['jml'] ?? 0);

        if ($c1 > 0 || $c2 > 0) {
            set_flash('danger', 'Kategori masih terhubung dengan ' . $c1 . ' alat dan ' . $c2 . ' tiket.');
        } else {
            $del = mysqli_prepare($conn, 'DELETE FROM kategori_alat WHERE id_kategori = ?');
            mysqli_stmt_bind_param($del, 'i', $id_kategori);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            set_flash('success', 'Kategori berhasil dihapus.');
        }

        header('Location: ' . BASE_URL . '/admin/kelola_kategori.php');
        exit;
    }
}

// Muat data kategori yang sedang diedit (jika ada).
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id   = (int)$_GET['edit'];
    $edit_data = pk_stmt_one($conn, 'SELECT * FROM kategori_alat WHERE id_kategori = ? LIMIT 1', 'i', [$edit_id]);
    if (empty($edit_data)) {
        $edit_data = null;
    }
}

$modal_fb   = get_modal_feedback();
$form_val   = !empty($modal_fb['old']) ? $modal_fb['old'] : ($edit_data ?? []);
$modal_open = ($edit_data !== null) || isset($_GET['open']) || ($modal_fb !== null);

$kategori_list = pk_stmt_all(
    $conn,
    "SELECT k.*,
        (SELECT COUNT(*) FROM aset_alat a WHERE a.id_kategori = k.id_kategori) AS total_alat,
        (SELECT COUNT(*) FROM tiket_perbaikan t WHERE t.id_kategori = k.id_kategori) AS total_tiket
     FROM kategori_alat k ORDER BY k.id_kategori ASC"
);

$page_title = 'Master Kategori';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Master Kategori</h1>
    <button type="button" class="btn btn-primary" data-modal-target="#modalKategori">
        <?= svg_icon('plus', 16) ?>
        <span>Tambah Kategori</span>
    </button>
</div>

<section class="pk-table-card" aria-labelledby="list-kat-heading">
    <div class="pk-table-card-head">
        <h2 id="list-kat-heading" class="pk-card-title">
            <?= svg_icon('tag', 18) ?>
            <span>Daftar Kategori</span>
        </h2>
    </div>

    <div class="pk-table-scroll">
        <table class="pk-table">
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Parameter</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($kategori_list) === 0): ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted">Belum ada kategori terdaftar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kategori_list as $row): ?>
                        <tr>
                            <td class="pk-td-head">
                                <strong><?= e($row['nama_kategori']) ?></strong>
                                <div class="mt-1 d-flex gap-1 flex-wrap">
                                    <span class="pk-tag"><?= (int)$row['total_alat'] ?> Alat</span>
                                    <span class="pk-tag"><?= (int)$row['total_tiket'] ?> Tiket</span>
                                </div>
                            </td>
                            <td class="pk-nowrap" data-label="Parameter">
                                <?= number_format((float)$row['rata_rata_berat_kg'], 2, ',', '.') ?> Kg<br>
                                <span class="text-muted"><?= rupiah($row['estimasi_harga_baru']) ?></span>
                            </td>
                            <td class="pk-td-actions">
                                <div class="btn-group-wrap">
                                    <a href="<?= BASE_URL ?>/admin/kelola_kategori.php?edit=<?= (int)$row['id_kategori'] ?>" class="btn btn-outline btn-icon" title="Edit" aria-label="Edit <?= e($row['nama_kategori']) ?>">
                                        <?= svg_icon('edit', 16) ?>
                                    </a>
                                    <form method="post" action="<?= BASE_URL ?>/admin/kelola_kategori.php" data-confirm="Hapus kategori <?= e($row['nama_kategori']) ?>?" data-confirm-title="Hapus Kategori" data-confirm-btn="Hapus" data-confirm-tone="danger">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_kategori" value="<?= (int)$row['id_kategori'] ?>">
                                        <button type="submit" class="btn btn-danger btn-icon" title="Hapus" aria-label="Hapus <?= e($row['nama_kategori']) ?>" <?= ((int)$row['total_alat'] > 0 || (int)$row['total_tiket'] > 0) ? 'disabled' : '' ?>>
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

<div id="modalKategori"
     class="pk-modal<?= $modal_open ? ' open' : '' ?>"
     role="dialog"
     aria-modal="true"
     aria-labelledby="form-kat-heading"
     aria-hidden="<?= $modal_open ? 'false' : 'true' ?>"<?= $edit_data ? ' data-cancel-url="' . BASE_URL . '/admin/kelola_kategori.php"' : '' ?>>
    <div class="pk-modal-dialog">
        <div class="pk-card-header">
            <h2 id="form-kat-heading" class="pk-card-title">
                <?= svg_icon($edit_data ? 'clipboard' : 'plus', 18) ?>
                <span><?= $edit_data ? 'Edit Kategori #' . (int)$edit_data['id_kategori'] : 'Tambah Kategori' ?></span>
            </h2>
            <button type="button" class="btn btn-muted btn-icon" data-modal-close aria-label="Tutup form">
                <?= svg_icon('x', 16) ?>
            </button>
        </div>

        <?php if ($modal_fb): ?>
            <?= render_alert((string)$modal_fb['type'], (string)$modal_fb['message']) ?>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/admin/kelola_kategori.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit_data ? 'update' : 'create' ?>">
            <?php if ($edit_data): ?>
                <input type="hidden" name="id_kategori" value="<?= (int)$edit_data['id_kategori'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nama_kategori" class="form-label">Nama Kategori</label>
                <input type="text" id="nama_kategori" name="nama_kategori" class="form-control" value="<?= e($form_val['nama_kategori'] ?? '') ?>" required>
            </div>

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="rata_rata_berat_kg" class="form-label">Rata-rata Berat (Kg)</label>
                    <input type="number" step="0.01" min="0.1" id="rata_rata_berat_kg" name="rata_rata_berat_kg" class="form-control" value="<?= e($form_val['rata_rata_berat_kg'] ?? '2.50') ?>" required>
                </div>

                <div class="form-group">
                    <label for="estimasi_harga_baru" class="form-label">Estimasi Harga Baru (Rp)</label>
                    <input type="number" step="1000" min="0" id="estimasi_harga_baru" name="estimasi_harga_baru" class="form-control" value="<?= e(isset($form_val['estimasi_harga_baru']) ? (int)$form_val['estimasi_harga_baru'] : '500000') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="panduan_keselamatan" class="form-label">Panduan Keselamatan Kerja</label>
                <textarea id="panduan_keselamatan" name="panduan_keselamatan" class="form-control" rows="5" required><?= e($form_val['panduan_keselamatan'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <?= svg_icon('check-circle', 16) ?>
                <span><?= $edit_data ? 'Simpan Perubahan' : 'Tambah Kategori' ?></span>
            </button>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
