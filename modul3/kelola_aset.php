<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

$admin_id = (int)$_SESSION['user']['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_mitra_tool') {
        $id_alat = (int)($_POST['id_alat'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE aset_alat SET status_alat = 'Tersedia' WHERE id_alat = ? AND status_alat = 'Menunggu Verifikasi'");
        mysqli_stmt_bind_param($stmt, 'i', $id_alat);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'Alat titipan Mitra berhasil disetujui dan diaktifkan.');
        header('Location: ' . BASE_URL . '/modul3/kelola_aset.php');
        exit;
    }

    if ($action === 'toggle_maintenance') {
        $id_alat = (int)($_POST['id_alat'] ?? 0);
        $target_status = ($_POST['target_status'] ?? '') === 'Perawatan' ? 'Perawatan' : 'Tersedia';
        $stmt = mysqli_prepare($conn, "UPDATE aset_alat SET status_alat = ? WHERE id_alat = ? AND status_alat IN ('Tersedia', 'Perawatan')");
        mysqli_stmt_bind_param($stmt, 'si', $target_status, $id_alat);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('info', 'Status alat diubah menjadi ' . $target_status . '.');
        header('Location: ' . BASE_URL . '/modul3/kelola_aset.php');
        exit;
    }

    if ($action === 'delete') {
        $id_alat = (int)($_POST['id_alat'] ?? 0);
        $chk = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM peminjaman WHERE id_alat = ?');
        mysqli_stmt_bind_param($chk, 'i', $id_alat);
        mysqli_stmt_execute($chk);
        $used = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($chk))['total'] ?? 0);
        mysqli_stmt_close($chk);

        if ($used > 0) {
            set_flash('danger', 'Unit sudah memiliki riwayat peminjaman dan tidak dapat dihapus.');
        } else {
            $del = mysqli_prepare($conn, 'DELETE FROM aset_alat WHERE id_alat = ?');
            mysqli_stmt_bind_param($del, 'i', $id_alat);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            set_flash('success', 'Unit alat berhasil dihapus.');
        }
        header('Location: ' . BASE_URL . '/modul3/kelola_aset.php');
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $id_alat = (int)($_POST['id_alat'] ?? 0);
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $nama_alat = trim($_POST['nama_alat'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $harga_beli_baru = (float)($_POST['harga_beli_baru'] ?? 0);
        $biaya_perawatan = (float)($_POST['biaya_perawatan'] ?? 0);
        $max_hari_pinjam = max(1, (int)($_POST['max_hari_pinjam'] ?? 3));
        $min_skor = max(0, (int)($_POST['min_skor'] ?? 50));
        $status_alat = $_POST['status_alat'] ?? 'Tersedia';
        if (!in_array($status_alat, ['Menunggu Verifikasi', 'Tersedia', 'Dipinjam', 'Perawatan'], true)) {
            $status_alat = 'Tersedia';
        }

        if ($nama_alat === '' || $deskripsi === '' || $id_kategori <= 0) {
            set_flash('warning', 'Nama alat, kategori, dan deskripsi wajib diisi.');
            header('Location: ' . BASE_URL . '/modul3/kelola_aset.php');
            exit;
        }

        $foto_filename = $_POST['foto_lama'] ?? 'default_alat.jpg';
        if (isset($_FILES['foto_alat']) && $_FILES['foto_alat']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = upload_gambar($_FILES['foto_alat'], UPLOAD_ALAT_PATH, 'alat_');
            if (!$up['success']) {
                set_flash('danger', $up['message']);
                header('Location: ' . BASE_URL . '/modul3/kelola_aset.php');
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
        header('Location: ' . BASE_URL . '/modul3/kelola_aset.php');
        exit;
    }
}

$edit_alat = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt_e = mysqli_prepare($conn, 'SELECT * FROM aset_alat WHERE id_alat = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt_e, 'i', $edit_id);
    mysqli_stmt_execute($stmt_e);
    $edit_alat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_e));
    mysqli_stmt_close($stmt_e);
}

$res_kategori = mysqli_query($conn, 'SELECT id_kategori, nama_kategori FROM kategori_alat ORDER BY nama_kategori ASC');
$kategori_options = [];
while ($k = mysqli_fetch_assoc($res_kategori)) {
    $kategori_options[] = $k;
}

$res_pending = mysqli_query($conn, "SELECT a.*, k.nama_kategori, u.nama AS nama_pemilik, u.no_hp
    FROM aset_alat a
    JOIN kategori_alat k ON a.id_kategori = k.id_kategori
    JOIN users u ON a.id_pemilik = u.id_user
    WHERE a.status_alat = 'Menunggu Verifikasi'
    ORDER BY a.created_at ASC");

$res_all = mysqli_query($conn, "SELECT a.*, k.nama_kategori, u.nama AS nama_pemilik, u.role AS role_pemilik,
    (SELECT COUNT(*) FROM peminjaman p WHERE p.id_alat = a.id_alat) AS jml_pinjam
    FROM aset_alat a
    JOIN kategori_alat k ON a.id_kategori = k.id_kategori
    JOIN users u ON a.id_pemilik = u.id_user
    ORDER BY a.id_alat DESC");

$page_title = 'Inventaris & Verifikasi Alat';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Inventaris &amp; Verifikasi Alat</h1>
</div>

<?php if (mysqli_num_rows($res_pending) > 0): ?>
<section class="pk-card mb-4" aria-labelledby="pending-mitra-heading">
    <div class="pk-card-header">
        <h2 id="pending-mitra-heading" class="pk-card-title">
            <?= svg_icon('alert-triangle', 18) ?>
            <span>Antrean Verifikasi Alat Mitra (<?= mysqli_num_rows($res_pending) ?>)</span>
        </h2>
    </div>

    <div class="table-responsive">
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
                <?php while ($p = mysqli_fetch_assoc($res_pending)): ?>
                    <tr>
                        <td style="width:88px;">
                            <img src="<?= e(get_gambar_alat($p['foto_alat'], $p['nama_alat'])) ?>" alt="<?= e($p['nama_alat']) ?>" width="76" height="50" style="border-radius:6px; object-fit:cover;">
                        </td>
                        <td>
                            <strong><?= e($p['nama_alat']) ?></strong>
                            <div><span class="pk-badge pk-badge--primary mt-1"><?= e($p['nama_kategori']) ?></span></div>
                        </td>
                        <td>
                            <strong><?= e($p['nama_pemilik']) ?></strong><br>
                            <span class="text-muted" style="font-size:0.82rem;"><?= e($p['no_hp']) ?></span>
                        </td>
                        <td>
                            <div>Harga Baru: <?= rupiah($p['harga_beli_baru']) ?></div>
                            <div>Rawat: <?= (float)$p['biaya_perawatan'] <= 0 ? 'Gratis' : rupiah($p['biaya_perawatan']) ?></div>
                            <div>Maks <?= (int)$p['max_hari_pinjam'] ?> hr &bull; Skor &ge; <?= (int)$p['min_skor'] ?></div>
                        </td>
                        <td>
                            <div class="btn-group-wrap">
                                <form method="post" action="<?= BASE_URL ?>/modul3/kelola_aset.php">
                                    <input type="hidden" name="action" value="approve_mitra_tool">
                                    <input type="hidden" name="id_alat" value="<?= (int)$p['id_alat'] ?>">
                                    <button type="submit" class="btn btn-secondary">Setujui</button>
                                </form>
                                <form method="post" action="<?= BASE_URL ?>/modul3/kelola_aset.php" onsubmit="return confirm('Tolak dan hapus pengajuan alat ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_alat" value="<?= (int)$p['id_alat'] ?>">
                                    <button type="submit" class="btn btn-danger">Tolak</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="pk-card mb-4" aria-labelledby="form-aset-heading">
    <div class="pk-card-header">
        <h2 id="form-aset-heading" class="pk-card-title">
            <?= svg_icon($edit_alat ? 'clipboard' : 'plus', 18) ?>
            <span><?= $edit_alat ? 'Edit Alat #' . (int)$edit_alat['id_alat'] : 'Tambah Alat' ?></span>
        </h2>
        <?php if ($edit_alat): ?>
            <a href="<?= BASE_URL ?>/modul3/kelola_aset.php" class="btn btn-muted">Batal</a>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= BASE_URL ?>/modul3/kelola_aset.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $edit_alat ? 'update' : 'create' ?>">
        <?php if ($edit_alat): ?>
            <input type="hidden" name="id_alat" value="<?= (int)$edit_alat['id_alat'] ?>">
            <input type="hidden" name="foto_lama" value="<?= e($edit_alat['foto_alat']) ?>">
        <?php endif; ?>

        <div class="pk-grid pk-grid--3">
            <div class="form-group">
                <label for="nama_alat" class="form-label">Nama Alat</label>
                <input type="text" id="nama_alat" name="nama_alat" class="form-control" value="<?= e($edit_alat['nama_alat'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="id_kategori" class="form-label">Kategori</label>
                <select id="id_kategori" name="id_kategori" class="form-select" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($kategori_options as $kat): ?>
                        <option value="<?= (int)$kat['id_kategori'] ?>" <?= ((int)($edit_alat['id_kategori'] ?? 0) === (int)$kat['id_kategori']) ? 'selected' : '' ?>>
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
                    $curr_st = $edit_alat['status_alat'] ?? 'Tersedia';
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
                <input type="number" step="1000" min="0" id="harga_beli_baru" name="harga_beli_baru" class="form-control" value="<?= e(isset($edit_alat['harga_beli_baru']) ? (int)$edit_alat['harga_beli_baru'] : '600000') ?>" required>
            </div>

            <div class="form-group">
                <label for="biaya_perawatan" class="form-label">Biaya Rawat (Rp)</label>
                <input type="number" step="500" min="0" id="biaya_perawatan" name="biaya_perawatan" class="form-control" value="<?= e(isset($edit_alat['biaya_perawatan']) ? (int)$edit_alat['biaya_perawatan'] : '0') ?>" required>
            </div>

            <div class="form-group">
                <label for="max_hari_pinjam" class="form-label">Maks. Pinjam (Hari)</label>
                <input type="number" min="1" max="30" id="max_hari_pinjam" name="max_hari_pinjam" class="form-control" value="<?= e($edit_alat['max_hari_pinjam'] ?? '3') ?>" required>
            </div>

            <div class="form-group">
                <label for="min_skor" class="form-label">Skor Minimal</label>
                <input type="number" min="0" max="200" id="min_skor" name="min_skor" class="form-control" value="<?= e($edit_alat['min_skor'] ?? '50') ?>" required>
            </div>
        </div>

        <div class="pk-grid pk-grid--2">
            <div class="form-group">
                <label for="deskripsi" class="form-label">Spesifikasi &amp; Kelengkapan</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" required><?= e($edit_alat['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="foto_alat" class="form-label">Foto Alat (Opsional, maks. 2 MB)</label>
                <input type="file" id="foto_alat" name="foto_alat" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?= svg_icon('check-circle', 16) ?>
            <span><?= $edit_alat ? 'Simpan Perubahan' : 'Simpan Alat' ?></span>
        </button>
    </form>
</section>

<section class="pk-card" aria-labelledby="all-aset-heading">
    <div class="pk-card-header">
        <h2 id="all-aset-heading" class="pk-card-title">
            <?= svg_icon('box', 18) ?>
            <span>Daftar Inventaris</span>
        </h2>
    </div>

    <div class="table-responsive">
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
                <?php while ($row = mysqli_fetch_assoc($res_all)): ?>
                    <tr>
                        <td style="width:88px;">
                            <img src="<?= e(get_gambar_alat($row['foto_alat'], $row['nama_alat'])) ?>" alt="<?= e($row['nama_alat']) ?>" width="76" height="50" style="border-radius:6px; object-fit:cover;">
                        </td>
                        <td>
                            <strong><?= e($row['nama_alat']) ?></strong>
                            <div><span class="pk-badge pk-badge--primary mt-1"><?= e($row['nama_kategori']) ?></span></div>
                        </td>
                        <td>
                            <strong><?= e($row['nama_pemilik']) ?></strong><br>
                            <span class="text-muted" style="font-size:0.82rem;"><?= $row['role_pemilik'] === 'admin' ? 'Hub' : 'Mitra' ?></span>
                        </td>
                        <td>
                            <div>Harga Baru: <?= rupiah($row['harga_beli_baru']) ?></div>
                            <div>Rawat: <?= (float)$row['biaya_perawatan'] <= 0 ? 'Gratis' : rupiah($row['biaya_perawatan']) ?></div>
                            <div>Maks <?= (int)$row['max_hari_pinjam'] ?> hr &bull; Skor &ge; <?= (int)$row['min_skor'] ?></div>
                        </td>
                        <td><?= badge_status_alat($row['status_alat']) ?></td>
                        <td>
                            <div class="btn-group-wrap">
                                <a href="<?= BASE_URL ?>/modul3/kelola_aset.php?edit=<?= (int)$row['id_alat'] ?>" class="btn btn-outline">Edit</a>

                                <?php if ($row['status_alat'] === 'Tersedia'): ?>
                                    <form method="post" action="<?= BASE_URL ?>/modul3/kelola_aset.php">
                                        <input type="hidden" name="action" value="toggle_maintenance">
                                        <input type="hidden" name="id_alat" value="<?= (int)$row['id_alat'] ?>">
                                        <input type="hidden" name="target_status" value="Perawatan">
                                        <button type="submit" class="btn btn-warm">Perawatan</button>
                                    </form>
                                <?php elseif ($row['status_alat'] === 'Perawatan'): ?>
                                    <form method="post" action="<?= BASE_URL ?>/modul3/kelola_aset.php">
                                        <input type="hidden" name="action" value="toggle_maintenance">
                                        <input type="hidden" name="id_alat" value="<?= (int)$row['id_alat'] ?>">
                                        <input type="hidden" name="target_status" value="Tersedia">
                                        <button type="submit" class="btn btn-secondary">Aktifkan</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="<?= BASE_URL ?>/modul3/kelola_aset.php" onsubmit="return confirm('Hapus unit alat ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_alat" value="<?= (int)$row['id_alat'] ?>">
                                    <button type="submit" class="btn btn-danger" <?= (int)$row['jml_pinjam'] > 0 ? 'disabled' : '' ?>>
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

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
