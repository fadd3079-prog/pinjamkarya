<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['mitra']);
refresh_user_session($conn);

if (empty($_SESSION['user']['is_kontributor'])) {
    set_flash('warning', 'Halaman ini khusus Mitra Kontributor Alat.');
    header('Location: ' . BASE_URL . '/modul1/profil.php');
    exit;
}

$id_mitra = (int)$_SESSION['user']['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $nama_alat = trim($_POST['nama_alat'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga_beli_baru = (float)($_POST['harga_beli_baru'] ?? 0);
    $biaya_perawatan = (float)($_POST['biaya_perawatan'] ?? 0);
    $max_hari_pinjam = max(1, (int)($_POST['max_hari_pinjam'] ?? 3));
    $min_skor = max(50, (int)($_POST['min_skor'] ?? 70));
    $status_awal = 'Menunggu Verifikasi';

    if ($nama_alat === '' || $deskripsi === '' || $id_kategori <= 0 || $harga_beli_baru <= 0) {
        set_flash('warning', 'Mohon lengkapi nama alat, kategori, harga baru, dan deskripsi.');
    } else {
        $kontak_res = lengkapi_kontak_user($conn, $_SESSION['user'], $_POST);
        if (!$kontak_res['ok']) {
            set_flash('warning', $kontak_res['message']);
        } else {
            $foto_filename = 'default_alat.jpg';
            $upload_ok = true;
            if (isset($_FILES['foto_alat']) && $_FILES['foto_alat']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = upload_gambar($_FILES['foto_alat'], UPLOAD_ALAT_PATH, 'mitra_');
                if (!$up['success']) {
                    set_flash('danger', $up['message']);
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
                header('Location: ' . BASE_URL . '/modul3/aset_mitra.php');
                exit;
            }
        }
    }
}

$res_kat = mysqli_query($conn, 'SELECT id_kategori, nama_kategori FROM kategori_alat ORDER BY nama_kategori ASC');

$stmt_list = mysqli_prepare($conn, "SELECT a.*, k.nama_kategori,
    (SELECT COUNT(*) FROM peminjaman p WHERE p.id_alat = a.id_alat AND p.status_peminjaman = 'Selesai') AS total_dipinjam
    FROM aset_alat a
    JOIN kategori_alat k ON a.id_kategori = k.id_kategori
    WHERE a.id_pemilik = ?
    ORDER BY a.id_alat DESC");
mysqli_stmt_bind_param($stmt_list, 'i', $id_mitra);
mysqli_stmt_execute($stmt_list);
$res_mitra_alat = mysqli_stmt_get_result($stmt_list);

$stmt_hist = mysqli_prepare($conn, "SELECT p.*, a.nama_alat, u.nama AS nama_peminjam
    FROM peminjaman p
    JOIN aset_alat a ON p.id_alat = a.id_alat
    JOIN users u ON p.id_warga = u.id_user
    WHERE a.id_pemilik = ?
    ORDER BY p.created_at DESC LIMIT 10");
mysqli_stmt_bind_param($stmt_hist, 'i', $id_mitra);
mysqli_stmt_execute($stmt_hist);
$res_hist = mysqli_stmt_get_result($stmt_hist);

$page_title = 'Alat Titipan Saya';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Alat Titipan Saya</h1>
    <a href="<?= BASE_URL ?>/modul6/riwayat_inspeksi.php" class="btn btn-outline">
        <?= svg_icon('clipboard', 16) ?>
        <span>Log Inspeksi</span>
    </a>
</div>

<div class="pk-grid pk-grid--2">
    <section class="pk-card" aria-labelledby="titip-heading">
        <div class="pk-card-header">
            <h2 id="titip-heading" class="pk-card-title">
                <?= svg_icon('plus', 18) ?>
                <span>Titip Perkakas Baru</span>
            </h2>
        </div>

        <form method="post" action="<?= BASE_URL ?>/modul3/aset_mitra.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nama_alat" class="form-label">Nama &amp; Merek Alat</label>
                <input type="text" id="nama_alat" name="nama_alat" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="id_kategori" class="form-label">Kategori</label>
                <select id="id_kategori" name="id_kategori" class="form-select" required>
                    <option value="">Pilih Kategori</option>
                    <?php while ($k = mysqli_fetch_assoc($res_kat)): ?>
                        <option value="<?= (int)$k['id_kategori'] ?>"><?= e($k['nama_kategori']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="harga_beli_baru" class="form-label">Harga Baru (Rp)</label>
                    <input type="number" step="1000" min="10000" id="harga_beli_baru" name="harga_beli_baru" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="biaya_perawatan" class="form-label">Biaya Rawat (Rp)</label>
                    <input type="number" step="500" min="0" id="biaya_perawatan" name="biaya_perawatan" class="form-control" value="0" required>
                </div>
            </div>

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="max_hari_pinjam" class="form-label">Maks. Pinjam (Hari)</label>
                    <input type="number" min="1" max="14" id="max_hari_pinjam" name="max_hari_pinjam" class="form-control" value="3" required>
                </div>

                <div class="form-group">
                    <label for="min_skor" class="form-label">Skor Minimal</label>
                    <input type="number" min="50" max="150" id="min_skor" name="min_skor" class="form-control" value="80" required>
                </div>
            </div>

            <div class="form-group">
                <label for="deskripsi" class="form-label">Spesifikasi &amp; Kelengkapan</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" required></textarea>
            </div>

            <div class="form-group">
                <label for="foto_alat" class="form-label">Foto Alat (Opsional, maks. 2 MB)</label>
                <input type="file" id="foto_alat" name="foto_alat" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <?= render_kontak_fields_if_needed($_SESSION['user'], $_POST) ?>

            <button type="submit" class="btn btn-primary w-100">
                <?= svg_icon('check-circle', 16) ?>
                <span>Ajukan Titip Alat</span>
            </button>
        </form>
    </section>

    <div>
        <section class="pk-card" aria-labelledby="daftar-titipan-heading">
            <div class="pk-card-header">
                <h2 id="daftar-titipan-heading" class="pk-card-title">
                    <?= svg_icon('box', 18) ?>
                    <span>Daftar Alat Saya</span>
                </h2>
            </div>

            <?php if (mysqli_num_rows($res_mitra_alat) === 0): ?>
                <div class="pk-empty-state">
                    <div class="pk-empty-icon"><?= svg_icon('box', 22) ?></div>
                    <h3>Belum Ada Alat Titipan</h3>
                </div>
            <?php else: ?>
                <div class="table-responsive">
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
                            <?php while ($a = mysqli_fetch_assoc($res_mitra_alat)): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($a['nama_alat']) ?></strong><br>
                                        <span class="text-muted" style="font-size:0.82rem;"><?= e($a['nama_kategori']) ?></span>
                                    </td>
                                    <td>
                                        Maks <?= (int)$a['max_hari_pinjam'] ?> hr<br>
                                        Skor &ge; <?= (int)$a['min_skor'] ?>
                                    </td>
                                    <td><?= badge_status_alat($a['status_alat']) ?></td>
                                    <td><strong><?= (int)$a['total_dipinjam'] ?>x</strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="pk-card" aria-labelledby="riwayat-pakai-heading">
            <div class="pk-card-header">
                <h2 id="riwayat-pakai-heading" class="pk-card-title">
                    <?= svg_icon('calendar', 18) ?>
                    <span>Riwayat Peminjaman</span>
                </h2>
            </div>

            <?php if (mysqli_num_rows($res_hist) === 0): ?>
                <div class="text-muted">Belum ada transaksi peminjaman.</div>
            <?php else: ?>
                <div class="table-responsive">
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
                            <?php while ($h = mysqli_fetch_assoc($res_hist)): ?>
                                <tr>
                                    <td><strong><?= e($h['kode_booking']) ?></strong></td>
                                    <td>
                                        <?= e($h['nama_alat']) ?><br>
                                        <span class="text-muted" style="font-size:0.82rem;"><?= e($h['nama_peminjam']) ?></span>
                                    </td>
                                    <td><?= e(format_tanggal($h['tgl_mulai'])) ?> - <?= e(format_tanggal($h['tgl_selesai'])) ?></td>
                                    <td><?= badge_status_pinjam($h['status_peminjaman']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php
mysqli_stmt_close($stmt_list);
mysqli_stmt_close($stmt_hist);
require_once ROOT_PATH . '/includes/footer.php';
?>
