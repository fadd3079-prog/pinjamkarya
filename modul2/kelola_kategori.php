<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $panduan_keselamatan = trim($_POST['panduan_keselamatan'] ?? '');
        $rata_rata_berat_kg = (float)($_POST['rata_rata_berat_kg'] ?? 1.00);
        $estimasi_harga_baru = (float)($_POST['estimasi_harga_baru'] ?? 0.00);

        if ($nama_kategori === '' || $panduan_keselamatan === '') {
            set_flash('warning', 'Nama kategori dan panduan keselamatan wajib diisi.');
        } elseif ($rata_rata_berat_kg <= 0 || $estimasi_harga_baru < 0) {
            set_flash('warning', 'Berat harus lebih dari 0 kg dan harga tidak boleh negatif.');
        } else {
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
        }
        header('Location: ' . BASE_URL . '/modul2/kelola_kategori.php');
        exit;
    }

    if ($action === 'delete') {
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);

        $chk1 = mysqli_prepare($conn, 'SELECT COUNT(*) AS jml FROM aset_alat WHERE id_kategori = ?');
        mysqli_stmt_bind_param($chk1, 'i', $id_kategori);
        mysqli_stmt_execute($chk1);
        $c1 = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($chk1))['jml'] ?? 0);
        mysqli_stmt_close($chk1);

        $chk2 = mysqli_prepare($conn, 'SELECT COUNT(*) AS jml FROM tiket_perbaikan WHERE id_kategori = ?');
        mysqli_stmt_bind_param($chk2, 'i', $id_kategori);
        mysqli_stmt_execute($chk2);
        $c2 = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($chk2))['jml'] ?? 0);
        mysqli_stmt_close($chk2);

        if ($c1 > 0 || $c2 > 0) {
            set_flash('danger', 'Kategori masih terhubung dengan ' . $c1 . ' alat dan ' . $c2 . ' tiket.');
        } else {
            $del = mysqli_prepare($conn, 'DELETE FROM kategori_alat WHERE id_kategori = ?');
            mysqli_stmt_bind_param($del, 'i', $id_kategori);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            set_flash('success', 'Kategori berhasil dihapus.');
        }
        header('Location: ' . BASE_URL . '/modul2/kelola_kategori.php');
        exit;
    }
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt_e = mysqli_prepare($conn, 'SELECT * FROM kategori_alat WHERE id_kategori = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt_e, 'i', $edit_id);
    mysqli_stmt_execute($stmt_e);
    $edit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_e));
    mysqli_stmt_close($stmt_e);
}

$res_list = mysqli_query($conn, "SELECT k.*,
    (SELECT COUNT(*) FROM aset_alat a WHERE a.id_kategori = k.id_kategori) AS total_alat,
    (SELECT COUNT(*) FROM tiket_perbaikan t WHERE t.id_kategori = k.id_kategori) AS total_tiket
    FROM kategori_alat k ORDER BY k.id_kategori ASC");

$page_title = 'Master Kategori';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Master Kategori</h1>
</div>

<div class="pk-grid pk-grid--2">
    <section class="pk-card" aria-labelledby="form-kat-heading">
        <div class="pk-card-header">
            <h2 id="form-kat-heading" class="pk-card-title">
                <?= svg_icon($edit_data ? 'clipboard' : 'plus', 18) ?>
                <span><?= $edit_data ? 'Edit Kategori #' . (int)$edit_data['id_kategori'] : 'Tambah Kategori' ?></span>
            </h2>
            <?php if ($edit_data): ?>
                <a href="<?= BASE_URL ?>/modul2/kelola_kategori.php" class="btn btn-muted">Batal</a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= BASE_URL ?>/modul2/kelola_kategori.php">
            <input type="hidden" name="action" value="<?= $edit_data ? 'update' : 'create' ?>">
            <?php if ($edit_data): ?>
                <input type="hidden" name="id_kategori" value="<?= (int)$edit_data['id_kategori'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nama_kategori" class="form-label">Nama Kategori</label>
                <input type="text" id="nama_kategori" name="nama_kategori" class="form-control" value="<?= e($edit_data['nama_kategori'] ?? '') ?>" required>
            </div>

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="rata_rata_berat_kg" class="form-label">Rata-rata Berat (Kg)</label>
                    <input type="number" step="0.01" min="0.1" id="rata_rata_berat_kg" name="rata_rata_berat_kg" class="form-control" value="<?= e($edit_data['rata_rata_berat_kg'] ?? '2.50') ?>" required>
                </div>

                <div class="form-group">
                    <label for="estimasi_harga_baru" class="form-label">Estimasi Harga Baru (Rp)</label>
                    <input type="number" step="1000" min="0" id="estimasi_harga_baru" name="estimasi_harga_baru" class="form-control" value="<?= e(isset($edit_data['estimasi_harga_baru']) ? (int)$edit_data['estimasi_harga_baru'] : '500000') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="panduan_keselamatan" class="form-label">Panduan Keselamatan Kerja</label>
                <textarea id="panduan_keselamatan" name="panduan_keselamatan" class="form-control" rows="5" required><?= e($edit_data['panduan_keselamatan'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <?= svg_icon('check-circle', 16) ?>
                <span><?= $edit_data ? 'Simpan Perubahan' : 'Tambah Kategori' ?></span>
            </button>
        </form>
    </section>

    <section class="pk-card" aria-labelledby="list-kat-heading">
        <div class="pk-card-header">
            <h2 id="list-kat-heading" class="pk-card-title">
                <?= svg_icon('tag', 18) ?>
                <span>Daftar Kategori</span>
            </h2>
        </div>

        <div class="table-responsive">
            <table class="pk-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Parameter</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($res_list)): ?>
                        <tr>
                            <td>
                                <strong><?= e($row['nama_kategori']) ?></strong>
                                <div class="mt-1 d-flex gap-1 flex-wrap">
                                    <span class="pk-badge pk-badge--muted"><?= (int)$row['total_alat'] ?> Alat</span>
                                    <span class="pk-badge pk-badge--muted"><?= (int)$row['total_tiket'] ?> Tiket</span>
                                </div>
                            </td>
                            <td>
                                <div><?= number_format((float)$row['rata_rata_berat_kg'], 2, ',', '.') ?> Kg</div>
                                <div class="text-muted"><?= rupiah($row['estimasi_harga_baru']) ?></div>
                            </td>
                            <td>
                                <div class="btn-group-wrap">
                                    <a href="<?= BASE_URL ?>/modul2/kelola_kategori.php?edit=<?= (int)$row['id_kategori'] ?>" class="btn btn-outline">Edit</a>
                                    <form method="post" action="<?= BASE_URL ?>/modul2/kelola_kategori.php" onsubmit="return confirm('Hapus kategori ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id_kategori" value="<?= (int)$row['id_kategori'] ?>">
                                        <button type="submit" class="btn btn-danger" <?= ((int)$row['total_alat'] > 0 || (int)$row['total_tiket'] > 0) ? 'disabled' : '' ?>>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
