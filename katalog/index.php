<?php
require_once dirname(__DIR__) . '/config/app.php';

bersihkan_ghost_booking($conn);

$q = trim($_GET['q'] ?? '');
$id_kategori = (int)($_GET['id_kategori'] ?? 0);
$tgl_mulai = trim($_GET['tgl_mulai'] ?? '');
$tgl_selesai = trim($_GET['tgl_selesai'] ?? '');

$res_kat = mysqli_query($conn, "SELECT k.id_kategori, k.nama_kategori,
    (SELECT COUNT(*) FROM aset_alat a WHERE a.id_kategori = k.id_kategori) AS total_alat
    FROM kategori_alat k
    ORDER BY k.nama_kategori ASC");
$kategori_list = [];
$total_semua = 0;
while ($k = mysqli_fetch_assoc($res_kat)) {
    $kategori_list[] = $k;
    $total_semua += (int)$k['total_alat'];
}

$filter_tanggal_aktif = false;
$select_bentrok = "0 AS is_bentrok";
$types = '';
$params = [];

if ($tgl_mulai !== '' && $tgl_selesai !== '') {
    if ($tgl_selesai < $tgl_mulai) {
        $tgl_selesai = $tgl_mulai;
    }
    $filter_tanggal_aktif = true;
    $select_bentrok = "EXISTS (
        SELECT 1 FROM peminjaman p
        WHERE p.id_alat = a.id_alat
          AND p.status_peminjaman IN ('Dipesan', 'Dipinjam')
          AND (p.tgl_mulai <= ? AND p.tgl_selesai >= ?)
    ) AS is_bentrok";
    $types .= 'ss';
    $params[] = $tgl_selesai;
    $params[] = $tgl_mulai;
}

$sql = "SELECT a.*, k.nama_kategori, u.nama AS nama_pemilik, u.role AS role_pemilik, {$select_bentrok}
        FROM aset_alat a
        JOIN kategori_alat k ON a.id_kategori = k.id_kategori
        JOIN users u ON a.id_pemilik = u.id_user
        WHERE 1=1";

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

$sql .= " ORDER BY (a.status_alat = 'Tersedia') DESC, FIELD(a.status_alat, 'Tersedia', 'Dipinjam', 'Perawatan', 'Menunggu Verifikasi'), a.id_alat ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res_alat = mysqli_stmt_get_result($stmt);
$jumlah_hasil = mysqli_num_rows($res_alat);

function katalog_filter_url(array $overrides = []): string {
    $query = [
        'q'           => $_GET['q'] ?? '',
        'id_kategori' => (int)($_GET['id_kategori'] ?? 0),
        'tgl_mulai'   => $_GET['tgl_mulai'] ?? '',
        'tgl_selesai' => $_GET['tgl_selesai'] ?? '',
    ];
    foreach ($overrides as $k => $v) {
        $query[$k] = $v;
    }
    $clean = [];
    foreach ($query as $k => $v) {
        if ($v !== '' && $v !== 0) {
            $clean[$k] = $v;
        }
    }
    return BASE_URL . '/katalog/index.php' . (!empty($clean) ? '?' . http_build_query($clean) : '');
}

$page_title = 'Katalog Perkakas';
$public_layout = !isset($_SESSION['user']);
$public_nav_active = 'katalog';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-archive-head">
    <h1 class="pk-archive-title">Katalog.</h1>
    <p class="pk-archive-count" aria-live="polite">
        <strong><?= $jumlah_hasil ?></strong>
        <span>unit</span>
    </p>
</div>

<section class="pk-filters" aria-label="Filter Katalog">
    <div class="pk-filters-row">
        <div class="pk-pill-tabs" role="navigation" aria-label="Filter kategori">
            <a href="<?= e(katalog_filter_url(['id_kategori' => 0])) ?>" class="pk-pill<?= $id_kategori === 0 ? ' is-active' : '' ?>">
                <span>Semua</span>
                <span class="pk-pill-count"><?= $total_semua ?></span>
            </a>
            <?php foreach ($kategori_list as $kat): ?>
                <a href="<?= e(katalog_filter_url(['id_kategori' => (int)$kat['id_kategori']])) ?>" class="pk-pill<?= $id_kategori === (int)$kat['id_kategori'] ? ' is-active' : '' ?>">
                    <span><?= e($kat['nama_kategori']) ?></span>
                    <span class="pk-pill-count"><?= (int)$kat['total_alat'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="<?= BASE_URL ?>/katalog/index.php" class="pk-search-wrap">
            <?php if ($id_kategori > 0): ?>
                <input type="hidden" name="id_kategori" value="<?= $id_kategori ?>">
            <?php endif; ?>
            <?php if ($tgl_mulai !== ''): ?>
                <input type="hidden" name="tgl_mulai" value="<?= e($tgl_mulai) ?>">
            <?php endif; ?>
            <?php if ($tgl_selesai !== ''): ?>
                <input type="hidden" name="tgl_selesai" value="<?= e($tgl_selesai) ?>">
            <?php endif; ?>
            <div class="pk-search-field">
                <span class="pk-search-icon"><?= svg_icon('search', 16) ?></span>
                <label class="sr-only" for="q">Cari alat</label>
                <input type="search" id="q" name="q" class="form-control" placeholder="Cari alat..." value="<?= e($q) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Cari</button>
        </form>
    </div>

    <form method="get" action="<?= BASE_URL ?>/katalog/index.php" class="pk-date-strip">
        <?php if ($q !== ''): ?>
            <input type="hidden" name="q" value="<?= e($q) ?>">
        <?php endif; ?>
        <?php if ($id_kategori > 0): ?>
            <input type="hidden" name="id_kategori" value="<?= $id_kategori ?>">
        <?php endif; ?>
        <span class="pk-date-strip-label">Cek jadwal</span>
        <label class="sr-only" for="tgl_mulai">Mulai Tanggal</label>
        <input type="date" id="tgl_mulai" name="tgl_mulai" class="pk-date-chip" min="<?= date('Y-m-d') ?>" value="<?= e($tgl_mulai) ?>">
        <span class="text-muted">-</span>
        <label class="sr-only" for="tgl_selesai">Sampai Tanggal</label>
        <input type="date" id="tgl_selesai" name="tgl_selesai" class="pk-date-chip" min="<?= date('Y-m-d') ?>" value="<?= e($tgl_selesai) ?>">
        <button type="submit" class="btn btn-outline">Terapkan</button>
        <?php if ($q !== '' || $id_kategori > 0 || $filter_tanggal_aktif): ?>
            <a href="<?= BASE_URL ?>/katalog/index.php" class="btn btn-muted">Reset</a>
        <?php endif; ?>
    </form>
</section>

<?php if ($filter_tanggal_aktif): ?>
    <?= render_alert('info', 'Menampilkan alat tersedia pada ' . format_tanggal($tgl_mulai) . ' - ' . format_tanggal($tgl_selesai) . '.', false, 'calendar') ?>
<?php endif; ?>

<?php if ($jumlah_hasil === 0): ?>
    <div class="pk-empty-state">
        <div class="pk-empty-icon"><?= svg_icon('box', 22) ?></div>
        <h3>Tidak Ada Perkakas</h3>
        <div class="mt-2">
            <a href="<?= BASE_URL ?>/katalog/index.php" class="btn btn-primary">Tampilkan Semua</a>
        </div>
    </div>
<?php else: ?>
    <div class="pk-masonry" data-masonry>
        <?php while ($alat = mysqli_fetch_assoc($res_alat)): ?>
            <?php
            $alat['is_unavailable'] = ($alat['status_alat'] !== 'Tersedia') || ($filter_tanggal_aktif && !empty($alat['is_bentrok']));
            $detail_url = BASE_URL . '/katalog/detail_alat.php?id=' . (int)$alat['id_alat'];
            if ($filter_tanggal_aktif) {
                $detail_url .= '&tgl_mulai=' . rawurlencode($tgl_mulai) . '&tgl_selesai=' . rawurlencode($tgl_selesai);
            }
            ?>
            <?= render_tool_card($alat, $detail_url, 'h2') ?>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php
mysqli_stmt_close($stmt);
require_once ROOT_PATH . '/includes/footer.php';
?>
