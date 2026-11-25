<?php
/**
 * Halaman katalog publik perkakas.
 *
 * Tujuan : Menampilkan seluruh unit alat dengan pencarian, filter kategori, dan
 *          pengecekan ketersediaan berdasarkan rentang tanggal.
 * Akses  : Publik (warga maupun tamu).
 * Alur   : Bersihkan ghost booking -> bangun query dinamis (kata kunci, kategori,
 *          tanggal) -> tampilkan kategori & kartu alat -> tautan ke detail.
 */

require_once dirname(__DIR__) . '/config/app.php';

bersihkan_ghost_booking($conn);

$q           = trim($_GET['q'] ?? '');
$id_kategori = (int)($_GET['id_kategori'] ?? 0);
$status      = trim($_GET['status'] ?? '');
$tgl_mulai   = trim($_GET['tgl_mulai'] ?? '');
$tgl_selesai = trim($_GET['tgl_selesai'] ?? '');

$status_options = [
    'Tersedia'  => 'Tersedia',
    'Dipinjam'  => 'Sedang Dipinjam',
    'Perawatan' => 'Sedang Diperbaiki',
];
if (!isset($status_options[$status])) {
    $status = '';
}

$status_counts = [];
$status_rows = pk_stmt_all($conn, 'SELECT status_alat, COUNT(*) AS jml FROM aset_alat GROUP BY status_alat');
foreach ($status_rows as $sr) {
    $status_counts[(string)$sr['status_alat']] = (int)$sr['jml'];
}

$kategori_list = pk_stmt_all(
    $conn,
    "SELECT k.id_kategori, k.nama_kategori,
        (SELECT COUNT(*) FROM aset_alat a WHERE a.id_kategori = k.id_kategori) AS total_alat
     FROM kategori_alat k
     ORDER BY k.nama_kategori ASC"
);
$total_semua = 0;
foreach ($kategori_list as $k) {
    $total_semua += (int)$k['total_alat'];
}

$filter_tanggal_aktif = false;
$select_bentrok       = "0 AS is_bentrok";
$types                = '';
$params               = [];

if ($tgl_mulai !== '' && $tgl_selesai !== '') {
    if ($tgl_selesai < $tgl_mulai) {
        $tgl_selesai = $tgl_mulai;
    }
    $filter_tanggal_aktif = true;
    // Subquery menandai alat yang sudah dibooking pada rentang tanggal terpilih.
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

if ($status !== '') {
    $sql .= " AND a.status_alat = ?";
    $types .= 's';
    $params[] = $status;
}

$sql .= " ORDER BY (a.status_alat = 'Tersedia') DESC, FIELD(a.status_alat, 'Tersedia', 'Dipinjam', 'Perawatan', 'Menunggu Verifikasi'), a.id_alat ASC";

$alat_list          = pk_stmt_all($conn, $sql, $types, $params);
$jumlah_hasil       = count($alat_list);
$has_active_filters = ($q !== '' || $id_kategori > 0 || $status !== '' || $filter_tanggal_aktif);

/**
 * Membangun URL katalog dengan mempertahankan filter aktif dan menimpa nilai tertentu.
 *
 * @param array<string, string|int> $overrides Nilai filter yang ingin diganti.
 * @return string URL katalog lengkap dengan query string.
 */
function katalog_filter_url(array $overrides = []): string {
    $query = [
        'q'           => $_GET['q'] ?? '',
        'id_kategori' => (int)($_GET['id_kategori'] ?? 0),
        'status'      => $_GET['status'] ?? '',
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
    <form method="get" action="<?= BASE_URL ?>/katalog/index.php" class="pk-filter-panel">
        <div class="pk-filter-bar-grid">
            <div class="pk-search-row">
                <div class="pk-search-field">
                    <span class="pk-search-icon" aria-hidden="true"><?= svg_icon('search', 16) ?></span>
                    <label class="sr-only" for="q">Cari alat</label>
                    <input type="search" id="q" name="q" class="form-control" placeholder="Cari perkakas..." value="<?= e($q) ?>">
                </div>
                <button type="submit" class="btn btn-primary pk-mobile-submit">Cari</button>
            </div>

            <div class="pk-filter-selects">
                <div class="pk-filter-select-wrap">
                    <label class="sr-only" for="id_kategori">Filter Kategori</label>
                    <select id="id_kategori" name="id_kategori" class="form-select" data-auto-submit="1" aria-label="Pilih kategori perkakas">
                        <option value="0" data-count="<?= $total_semua ?>"<?= $id_kategori === 0 ? ' selected' : '' ?>>Semua Kategori</option>
                        <?php foreach ($kategori_list as $kat): ?>
                            <option value="<?= (int)$kat['id_kategori'] ?>" data-count="<?= (int)$kat['total_alat'] ?>"<?= $id_kategori === (int)$kat['id_kategori'] ? ' selected' : '' ?>>
                                <?= e($kat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pk-filter-select-wrap">
                    <label class="sr-only" for="status">Filter Status</label>
                    <select id="status" name="status" class="form-select" data-auto-submit="1" aria-label="Filter status ketersediaan">
                        <option value="" data-count="<?= $total_semua ?>"<?= $status === '' ? ' selected' : '' ?>>Semua Status</option>
                        <?php foreach ($status_options as $st_val => $st_label): ?>
                            <option value="<?= e($st_val) ?>" data-count="<?= (int)($status_counts[$st_val] ?? 0) ?>"<?= $status === $st_val ? ' selected' : '' ?>>
                                <?= e($st_label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="pk-schedule-group" id="pkScheduleGroup">
                <div class="pk-daterange"
                     data-pk-daterange="1"
                     data-min="<?= date('Y-m-d') ?>"
                     data-placeholder="Pilih Tanggal"
                     data-mobile-placeholder="Cek ketersediaan tanggal">
                    <input type="hidden" id="tgl_mulai" name="tgl_mulai" value="<?= e($tgl_mulai) ?>">
                    <input type="hidden" id="tgl_selesai" name="tgl_selesai" value="<?= e($tgl_selesai) ?>">
                    <button type="button"
                            class="pk-daterange-trigger<?= $filter_tanggal_aktif ? ' has-value' : '' ?>"
                            id="pkDateRangeTrigger"
                            aria-haspopup="dialog"
                            aria-expanded="false"
                            aria-controls="pkDateRangeMenu">
                        <span class="pk-daterange-main">
                            <span class="pk-daterange-icon" aria-hidden="true"><?= svg_icon('calendar', 15) ?></span>
                            <span class="pk-daterange-label" id="pkDateRangeLabel">
                                <?= $filter_tanggal_aktif ? e(format_tanggal($tgl_mulai) . ' – ' . format_tanggal($tgl_selesai)) : 'Pilih Tanggal' ?>
                            </span>
                        </span>
                        <span class="pk-select-chevron" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    </button>
                    <div class="pk-datepicker-menu" id="pkDateRangeMenu" role="dialog" aria-label="Pilih rentang tanggal ketersediaan"></div>
                </div>

                <?php if ($has_active_filters): ?>
                    <a href="<?= BASE_URL ?>/katalog/index.php" class="pk-mobile-reset-link">Reset filter</a>
                <?php endif; ?>

                <div class="pk-filter-actions">
                    <button type="submit" class="btn btn-primary">Terapkan</button>
                    <?php if ($has_active_filters): ?>
                        <a href="<?= BASE_URL ?>/katalog/index.php" class="btn btn-outline">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

    <?php if ($has_active_filters): ?>
        <div class="pk-active-filters" aria-label="Filter aktif">
            <?php if ($q !== ''): ?>
                <a href="<?= e(katalog_filter_url(['q' => ''])) ?>" class="pk-filter-chip" title="Hapus pencarian">
                    <span>Cari: <?= e($q) ?></span>
                    <?= svg_icon('x', 13) ?>
                </a>
            <?php endif; ?>
            <?php if ($id_kategori > 0): ?>
                <?php
                $active_kat_name = 'Kategori';
                foreach ($kategori_list as $kat) {
                    if ((int)$kat['id_kategori'] === $id_kategori) {
                        $active_kat_name = (string)$kat['nama_kategori'];
                        break;
                    }
                }
                ?>
                <a href="<?= e(katalog_filter_url(['id_kategori' => 0])) ?>" class="pk-filter-chip" title="Tampilkan semua kategori">
                    <span><?= e($active_kat_name) ?></span>
                    <?= svg_icon('x', 13) ?>
                </a>
            <?php endif; ?>
            <?php if ($status !== ''): ?>
                <a href="<?= e(katalog_filter_url(['status' => ''])) ?>" class="pk-filter-chip" title="Tampilkan semua status">
                    <span><?= e($status_options[$status]) ?></span>
                    <?= svg_icon('x', 13) ?>
                </a>
            <?php endif; ?>
            <?php if ($filter_tanggal_aktif): ?>
                <a href="<?= e(katalog_filter_url(['tgl_mulai' => '', 'tgl_selesai' => ''])) ?>" class="pk-filter-chip" title="Hapus filter jadwal">
                    <span><?= e(format_tanggal($tgl_mulai)) ?> &ndash; <?= e(format_tanggal($tgl_selesai)) ?></span>
                    <?= svg_icon('x', 13) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($jumlah_hasil === 0): ?>
    <div class="pk-empty-state">
        <div class="pk-empty-icon"><?= svg_icon('box', 22) ?></div>
        <h3>Tidak Ada Perkakas</h3>
        <div class="mt-2">
            <a href="<?= BASE_URL ?>/katalog/index.php" class="btn btn-primary">Tampilkan Semua</a>
        </div>
    </div>
<?php else: ?>
    <div class="pk-masonry">
        <?php foreach ($alat_list as $alat): ?>
            <?php
            $alat['is_unavailable'] = ($alat['status_alat'] !== 'Tersedia') || ($filter_tanggal_aktif && !empty($alat['is_bentrok']));
            $detail_url = BASE_URL . '/katalog/detail_alat.php?id=' . (int)$alat['id_alat'];
            if ($filter_tanggal_aktif) {
                $detail_url .= '&tgl_mulai=' . rawurlencode($tgl_mulai) . '&tgl_selesai=' . rawurlencode($tgl_selesai);
            }
            ?>
            <?= render_tool_card($alat, $detail_url, 'h2') ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
