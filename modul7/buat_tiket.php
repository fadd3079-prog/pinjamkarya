<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['warga']);
refresh_user_session($conn);

$id_pelapor = (int)$_SESSION['user']['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $deskripsi_kerusakan = trim($_POST['deskripsi_kerusakan'] ?? '');
    $metode_cek = ($_POST['metode_cek'] ?? '') === 'Kunjungan Teknisi' ? 'Kunjungan Teknisi' : 'Diantar ke Hub';

    if ($id_kategori <= 0 || $nama_barang === '' || $deskripsi_kerusakan === '') {
        set_flash('warning', 'Kategori, nama barang, dan keluhan kerusakan wajib diisi.');
    } else {
        $foto_filename = 'default_tiket.jpg';
        $upload_ok = true;

        if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = upload_gambar($_FILES['foto_barang'], UPLOAD_TIKET_PATH, 'tiket_');
            if (!$up['success']) {
                set_flash('danger', $up['message']);
                $upload_ok = false;
            } else {
                $foto_filename = $up['filename'];
            }
        }

        if ($upload_ok) {
            $kode_tiket = generate_kode_tiket($conn);
            $status_awal = 'Menunggu Diagnosis';

            $stmt = mysqli_prepare($conn, 'INSERT INTO tiket_perbaikan (kode_tiket, id_pelapor, id_kategori, id_teknisi, nama_barang, deskripsi_kerusakan, foto_barang, metode_cek, status_tiket) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'siisssss', $kode_tiket, $id_pelapor, $id_kategori, $nama_barang, $deskripsi_kerusakan, $foto_filename, $metode_cek, $status_awal);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            set_flash('success', 'Tiket perbaikan diterbitkan: ' . $kode_tiket . '.');
            header('Location: ' . BASE_URL . '/modul8/tiket_saya.php');
            exit;
        }
    }
}

$res_kat = mysqli_query($conn, 'SELECT id_kategori, nama_kategori, rata_rata_berat_kg, estimasi_harga_baru FROM kategori_alat ORDER BY nama_kategori ASC');

$page_title = 'Ajukan Perbaikan';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Ajukan Perbaikan Barang</h1>
    <a href="<?= BASE_URL ?>/modul8/tiket_saya.php" class="btn btn-outline">
        <?= svg_icon('clipboard', 16) ?>
        <span>Tiket Saya</span>
    </a>
</div>

<div style="max-width: 680px;">
    <section class="pk-card" aria-labelledby="form-tiket-heading">
        <div class="pk-card-header">
            <h2 id="form-tiket-heading" class="pk-card-title">
                <?= svg_icon('wrench', 18) ?>
                <span>Data Barang &amp; Keluhan</span>
            </h2>
        </div>

        <form method="post" action="<?= BASE_URL ?>/modul7/buat_tiket.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nama_barang" class="form-label">Nama &amp; Merek Barang</label>
                <input type="text" id="nama_barang" name="nama_barang" class="form-control" placeholder="Contoh: Blender Philips HR2115" value="<?= e($_POST['nama_barang'] ?? '') ?>" required>
            </div>

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="id_kategori" class="form-label">Kategori</label>
                    <select id="id_kategori" name="id_kategori" class="form-select" required>
                        <option value="">Pilih Kategori</option>
                        <?php while ($k = mysqli_fetch_assoc($res_kat)): ?>
                            <option value="<?= (int)$k['id_kategori'] ?>" <?= ((int)($_POST['id_kategori'] ?? 0) === (int)$k['id_kategori']) ? 'selected' : '' ?>>
                                <?= e($k['nama_kategori']) ?> (<?= number_format((float)$k['rata_rata_berat_kg'], 1, ',', '.') ?> Kg)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="metode_cek" class="form-label">Metode Pengecekan</label>
                    <select id="metode_cek" name="metode_cek" class="form-select" required>
                        <option value="Diantar ke Hub" <?= (($_POST['metode_cek'] ?? '') === 'Diantar ke Hub') ? 'selected' : '' ?>>Diantar ke Hub</option>
                        <option value="Kunjungan Teknisi" <?= (($_POST['metode_cek'] ?? '') === 'Kunjungan Teknisi') ? 'selected' : '' ?>>Kunjungan Teknisi</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="deskripsi_kerusakan" class="form-label">Keluhan / Gejala Kerusakan</label>
                <textarea id="deskripsi_kerusakan" name="deskripsi_kerusakan" class="form-control" rows="4" placeholder="Bagian yang tidak berfungsi atau gejala kerusakan..." required><?= e($_POST['deskripsi_kerusakan'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="foto_barang" class="form-label">Foto Barang (Opsional, maks. 2 MB)</label>
                <input type="file" id="foto_barang" name="foto_barang" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <button type="submit" class="btn btn-warm w-100">
                <?= svg_icon('check-circle', 16) ?>
                <span>Kirim Pengajuan</span>
            </button>
        </form>
    </section>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
