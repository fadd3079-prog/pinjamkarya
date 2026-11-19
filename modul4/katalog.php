<?php
require_once dirname(__DIR__) . '/config/app.php';

bersihkan_ghost_booking($conn);

$q = trim($_GET['q'] ?? '');
$id_kategori = (int)($_GET['id_kategori'] ?? 0);
$tgl_mulai = trim($_GET['tgl_mulai'] ?? '');
$tgl_selesai = trim($_GET['tgl_selesai'] ?? '');

$res_kat = mysqli_query($conn, 'SELECT id_kategori, nama_kategori FROM kategori_alat ORDER BY nama_kategori ASC');
$kategori_list = [];
while ($k = mysqli_fetch_assoc($res_kat)) {
    $kategori_list[] = $k;
}

$sql = "SELECT a.*, k.nama_kategori, u.nama AS nama_pemilik, u.role AS role_pemilik
        FROM aset_alat a
        JOIN kategori_alat k ON a.id_kategori = k.id_kategori
        JOIN users u ON a.id_pemilik = u.id_user
        WHERE a.status_alat IN ('Tersedia', 'Dipinjam', 'Perawatan')";

$types = '';
$params = [];

if ($q !== '') {
    $sql .= " AND (a.nama_alat LIKE ? OR a.deskripsi LIKE ?)";
    $like = '%' . $q . '%';
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

if ($id_kategori > 0) {
    $sql .= " AND a.id_kategori = ?";
    $types .= 'i';
    $params[] = $id_kategori;
}

$filter_tanggal_aktif = false;
if ($tgl_mulai !== '' && $tgl_selesai !== '') {
    if ($tgl_selesai < $tgl_mulai) {
        $tgl_selesai = $tgl_mulai;
    }
    $filter_tanggal_aktif = true;
    $sql .= " AND a.status_alat != 'Perawatan'
              AND NOT EXISTS (
                  SELECT 1 FROM peminjaman p
                  WHERE p.id_alat = a.id_alat
                    AND p.status_peminjaman IN ('Dipesan', 'Dipinjam')
                    AND (p.tgl_mulai <= ? AND p.tgl_selesai >= ?)
              )";
    $types .= 'ss';
    $params[] = $tgl_selesai;
    $params[] = $tgl_mulai;
}

$sql .= " ORDER BY FIELD(a.status_alat, 'Tersedia', 'Dipinjam', 'Perawatan'), a.id_alat ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res_alat = mysqli_stmt_get_result($stmt);

$page_title = 'Katalog Perkakas';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Katalog Perkakas</h1>
    <a href="<?= BASE_URL ?>/modul2/panduan.php" class="btn btn-outline">
        <?= svg_icon('shield', 16) ?>
        <span>Panduan Keselamatan</span>
    </a>
</div>

<section class="pk-card mb-4" aria-label="Filter Katalog">
    <form method="get" action="<?= BASE_URL ?>/modul4/katalog.php">
        <div class="pk-grid pk-grid--4">
            <div class="form-group mb-0">
                <label for="q" class="form-label">Nama Alat</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Cari bor, tangga, kursi roda..." value="<?= e($q) ?>">
            </div>

            <div class="form-group mb-0">
                <label for="id_kategori" class="form-label">Kategori</label>
                <select id="id_kategori" name="id_kategori" class="form-select">
                    <option value="0">Semua Kategori</option>
                    <?php foreach ($kategori_list as $kat): ?>
                        <option value="<?= (int)$kat['id_kategori'] ?>" <?= $id_kategori === (int)$kat['id_kategori'] ? 'selected' : '' ?>>
                            <?= e($kat['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-0">
                <label for="tgl_mulai" class="form-label">Mulai Tanggal</label>
                <input type="date" id="tgl_mulai" name="tgl_mulai" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($tgl_mulai) ?>">
            </div>

            <div class="form-group mb-0">
                <label for="tgl_selesai" class="form-label">Sampai Tanggal</label>
                <input type="date" id="tgl_selesai" name="tgl_selesai" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($tgl_selesai) ?>">
            </div>
        </div>

        <div class="btn-group-wrap mt-3">
            <button type="submit" class="btn btn-primary">
                <?= svg_icon('search', 16) ?>
                <span>Terapkan Filter</span>
            </button>
            <?php if ($q !== '' || $id_kategori > 0 || $filter_tanggal_aktif): ?>
                <a href="<?= BASE_URL ?>/modul4/katalog.php" class="btn btn-muted">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if ($filter_tanggal_aktif): ?>
    <div class="pk-alert pk-alert--info">
        <div class="d-flex align-center gap-2">
            <?= svg_icon('calendar', 18) ?>
            <span>Tersedia pada <strong><?= e(format_tanggal($tgl_mulai)) ?></strong> - <strong><?= e(format_tanggal($tgl_selesai)) ?></strong>.</span>
        </div>
    </div>
<?php endif; ?>

<?php if (mysqli_num_rows($res_alat) === 0): ?>
    <div class="pk-empty-state">
        <div class="pk-empty-icon"><?= svg_icon('box', 22) ?></div>
        <h3>Tidak Ada Perkakas</h3>
        <div class="mt-2">
            <a href="<?= BASE_URL ?>/modul4/katalog.php" class="btn btn-primary">Tampilkan Semua</a>
        </div>
    </div>
<?php else: ?>
    <div class="pk-grid pk-grid--3">
        <?php while ($alat = mysqli_fetch_assoc($res_alat)): ?>
            <?php
            $detail_url = BASE_URL . '/modul4/detail_alat.php?id=' . (int)$alat['id_alat'];
            if ($filter_tanggal_aktif) {
                $detail_url .= '&tgl_mulai=' . rawurlencode($tgl_mulai) . '&tgl_selesai=' . rawurlencode($tgl_selesai);
            }
            ?>
            <article class="pk-tool-card">
                <div class="pk-tool-media">
                    <img src="<?= e(get_gambar_alat($alat['foto_alat'], $alat['nama_alat'])) ?>"
                         alt="<?= e($alat['nama_alat']) ?>"
                         width="640"
                         height="400"
                         loading="lazy">
                    <div class="pk-tool-badges">
                        <span class="pk-badge pk-badge--primary"><?= e($alat['nama_kategori']) ?></span>
                        <?= badge_status_alat($alat['status_alat']) ?>
                    </div>
                </div>

                <div class="pk-tool-body">
                    <h2 class="pk-tool-title"><?= e($alat['nama_alat']) ?></h2>
                    <p class="pk-tool-desc"><?= e($alat['deskripsi']) ?></p>

                    <ul class="pk-tool-meta">
                        <li>
                            <span>Maks. Pinjam</span>
                            <strong><?= (int)$alat['max_hari_pinjam'] ?> Hari</strong>
                        </li>
                        <li>
                            <span>Skor Minimal</span>
                            <strong><?= (int)$alat['min_skor'] ?> Poin</strong>
                        </li>
                        <li>
                            <span>Biaya Rawat</span>
                            <?php if ((float)$alat['biaya_perawatan'] <= 0): ?>
                                <strong class="text-secondary">Gratis</strong>
                            <?php else: ?>
                                <strong><?= rupiah($alat['biaya_perawatan']) ?></strong>
                            <?php endif; ?>
                        </li>
                        <li>
                            <span>Harga Baru</span>
                            <strong><?= rupiah($alat['harga_beli_baru']) ?></strong>
                        </li>
                    </ul>

                    <?php if ($alat['status_alat'] === 'Perawatan'): ?>
                        <a href="<?= e($detail_url) ?>" class="btn btn-muted w-100">
                            <?= svg_icon('wrench', 16) ?>
                            <span>Dalam Perawatan</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= e($detail_url) ?>" class="btn btn-primary w-100">
                            <?= svg_icon('calendar', 16) ?>
                            <span>Detail &amp; Pinjam</span>
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php
mysqli_stmt_close($stmt);
require_once ROOT_PATH . '/includes/footer.php';
?>
