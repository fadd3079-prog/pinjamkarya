<?php
require_once dirname(__DIR__) . '/config/app.php';

$sql_hemat_pinjam = "SELECT COALESCE(SUM(a.harga_beli_baru - a.biaya_perawatan), 0) AS total
                     FROM peminjaman p
                     JOIN aset_alat a ON p.id_alat = a.id_alat
                     WHERE p.status_peminjaman = 'Selesai'";
$hemat_pinjam = (float)(mysqli_fetch_assoc(mysqli_query($conn, $sql_hemat_pinjam))['total'] ?? 0);

$sql_hemat_servis = "SELECT COALESCE(SUM(k.estimasi_harga_baru - t.estimasi_biaya), 0) AS total
                     FROM tiket_perbaikan t
                     JOIN kategori_alat k ON t.id_kategori = k.id_kategori
                     WHERE t.status_tiket = 'Selesai'";
$hemat_servis = (float)(mysqli_fetch_assoc(mysqli_query($conn, $sql_hemat_servis))['total'] ?? 0);

$total_penghematan = $hemat_pinjam + $hemat_servis;

$sql_ewaste_kg = "SELECT COALESCE(SUM(k.rata_rata_berat_kg), 0) AS total
                  FROM tiket_perbaikan t
                  JOIN kategori_alat k ON t.id_kategori = k.id_kategori
                  WHERE t.status_tiket = 'Selesai'";
$total_ewaste_kg = (float)(mysqli_fetch_assoc(mysqli_query($conn, $sql_ewaste_kg))['total'] ?? 0);

$sql_unit_servis = "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE status_tiket = 'Selesai'";
$total_unit_servis = (int)(mysqli_fetch_assoc(mysqli_query($conn, $sql_unit_servis))['total'] ?? 0);

$sql_pinjam_tuntas = "SELECT COUNT(*) AS total FROM peminjaman WHERE status_peminjaman = 'Selesai'";
$total_pinjam_tuntas = (int)(mysqli_fetch_assoc(mysqli_query($conn, $sql_pinjam_tuntas))['total'] ?? 0);

$sql_utilitas = "SELECT k.id_kategori, k.nama_kategori, k.rata_rata_berat_kg,
    (SELECT COUNT(*) FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE a.id_kategori = k.id_kategori AND p.status_peminjaman = 'Selesai') AS jml_pinjam_selesai,
    (SELECT COALESCE(SUM(a.harga_beli_baru - a.biaya_perawatan), 0) FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE a.id_kategori = k.id_kategori AND p.status_peminjaman = 'Selesai') AS hemat_pinjam_kat,
    (SELECT COUNT(*) FROM tiket_perbaikan t WHERE t.id_kategori = k.id_kategori AND t.status_tiket = 'Selesai') AS jml_servis_selesai,
    (SELECT COALESCE(SUM(k.estimasi_harga_baru - t.estimasi_biaya), 0) FROM tiket_perbaikan t WHERE t.id_kategori = k.id_kategori AND t.status_tiket = 'Selesai') AS hemat_servis_kat
    FROM kategori_alat k
    ORDER BY k.id_kategori ASC";
$res_util = mysqli_query($conn, $sql_utilitas);
$kategori_metrics = [];
$max_aktivitas = 1;
while ($row = mysqli_fetch_assoc($res_util)) {
    $aktivitas = (int)$row['jml_pinjam_selesai'] + (int)$row['jml_servis_selesai'];
    if ($aktivitas > $max_aktivitas) {
        $max_aktivitas = $aktivitas;
    }
    $row['total_aktivitas'] = $aktivitas;
    $row['total_hemat_kat'] = (float)$row['hemat_pinjam_kat'] + (float)$row['hemat_servis_kat'];
    $row['total_kg_kat'] = (int)$row['jml_servis_selesai'] * (float)$row['rata_rata_berat_kg'];
    $kategori_metrics[] = $row;
}

$res_last_pinjam = mysqli_query($conn, "SELECT p.kode_booking, p.tgl_mulai, p.tgl_selesai, a.nama_alat,
    (a.harga_beli_baru - a.biaya_perawatan) AS nilai_hemat, u.nama AS nama_warga
    FROM peminjaman p
    JOIN aset_alat a ON p.id_alat = a.id_alat
    JOIN users u ON p.id_warga = u.id_user
    WHERE p.status_peminjaman = 'Selesai'
    ORDER BY p.id_peminjaman DESC LIMIT 5");

$res_last_servis = mysqli_query($conn, "SELECT t.kode_tiket, t.nama_barang, t.estimasi_biaya,
    (k.estimasi_harga_baru - t.estimasi_biaya) AS nilai_hemat, k.rata_rata_berat_kg,
    u.nama AS nama_pelapor, tek.nama AS nama_teknisi
    FROM tiket_perbaikan t
    JOIN kategori_alat k ON t.id_kategori = k.id_kategori
    JOIN users u ON t.id_pelapor = u.id_user
    LEFT JOIN users tek ON t.id_teknisi = tek.id_user
    WHERE t.status_tiket = 'Selesai'
    ORDER BY t.id_tiket DESC LIMIT 5");

$page_title = 'Dashboard Dampak';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Dashboard Dampak Sirkularitas</h1>
    <div class="btn-group-wrap">
        <a href="<?= BASE_URL ?>/modul4/katalog.php" class="btn btn-primary">Pinjam Alat</a>
        <a href="<?= BASE_URL ?>/modul7/buat_tiket.php" class="btn btn-warm">Perbaiki Barang</a>
    </div>
</div>

<section class="pk-grid pk-grid--4 mb-4" aria-label="Indikator Utama">
    <article class="pk-card mb-0">
        <div class="d-flex align-center justify-between mb-1">
            <span class="text-muted">Total Penghematan Warga</span>
            <?= svg_icon('tag', 18) ?>
        </div>
        <div class="fs-xl fw-bold text-primary"><?= rupiah($total_penghematan) ?></div>
        <div class="mt-2 text-muted" style="font-size:0.82rem;">
            <div>Pinjam: <strong><?= rupiah($hemat_pinjam) ?></strong></div>
            <div>Servis: <strong><?= rupiah($hemat_servis) ?></strong></div>
        </div>
    </article>

    <article class="pk-card mb-0">
        <div class="d-flex align-center justify-between mb-1">
            <span class="text-muted">E-Waste Dicegah (SDG 12)</span>
            <?= svg_icon('leaf', 18) ?>
        </div>
        <div class="fs-xl fw-bold text-secondary"><?= number_format($total_ewaste_kg, 2, ',', '.') ?> Kg</div>
    </article>

    <article class="pk-card mb-0">
        <div class="d-flex align-center justify-between mb-1">
            <span class="text-muted">Barang Diperbaiki</span>
            <?= svg_icon('wrench', 18) ?>
        </div>
        <div class="fs-xl fw-bold text-warm"><?= $total_unit_servis ?> Unit</div>
    </article>

    <article class="pk-card mb-0">
        <div class="d-flex align-center justify-between mb-1">
            <span class="text-muted">Peminjaman Tuntas (SDG 10)</span>
            <?= svg_icon('box', 18) ?>
        </div>
        <div class="fs-xl fw-bold text-primary"><?= $total_pinjam_tuntas ?> Transaksi</div>
    </article>
</section>

<section class="pk-card mb-4" aria-labelledby="chart-utilitas-heading">
    <div class="pk-card-header">
        <h2 id="chart-utilitas-heading" class="pk-card-title">
            <?= svg_icon('bar-chart', 18) ?>
            <span>Utilitas &amp; Penghematan per Kategori</span>
        </h2>
    </div>

    <div class="pk-grid pk-grid--2">
        <div>
            <?php foreach ($kategori_metrics as $km): ?>
                <?php
                $pct = (int)round(($km['total_aktivitas'] / $max_aktivitas) * 100);
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-between align-center flex-wrap gap-1 mb-1">
                        <strong><?= e($km['nama_kategori']) ?></strong>
                        <span class="text-muted" style="font-size:0.84rem;">
                            <strong><?= (int)$km['jml_pinjam_selesai'] ?></strong> Pinjam &bull;
                            <strong><?= (int)$km['jml_servis_selesai'] ?></strong> Servis &bull;
                            <strong><?= rupiah($km['total_hemat_kat']) ?></strong>
                        </span>
                    </div>
                    <div class="pk-progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Utilitas <?= e($km['nama_kategori']) ?>">
                        <div class="pk-progress-bar" style="width: <?= max(6, $pct) ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pk-card mb-0">
            <?php
            $max_hemat_kat = 1.0;
            foreach ($kategori_metrics as $km) {
                if ($km['total_hemat_kat'] > $max_hemat_kat) {
                    $max_hemat_kat = $km['total_hemat_kat'];
                }
            }
            $svg_height = count($kategori_metrics) * 54 + 16;
            ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 <?= $svg_height ?>" width="100%" height="<?= $svg_height ?>" role="img" aria-label="Perbandingan penghematan per kategori">
                <?php foreach ($kategori_metrics as $idx => $km): ?>
                    <?php
                    $y = 12 + ($idx * 52);
                    $bar_w = (int)round(($km['total_hemat_kat'] / $max_hemat_kat) * 280);
                    $bar_w = max(8, $bar_w);
                    ?>
                    <text x="4" y="<?= $y + 14 ?>" font-family="Inter, sans-serif" font-size="13" font-weight="500" fill="#112026">
                        <?= e($km['nama_kategori']) ?>
                    </text>
                    <rect x="4" y="<?= $y + 22 ?>" width="280" height="12" rx="6" fill="#eef2f4" />
                    <rect x="4" y="<?= $y + 22 ?>" width="<?= $bar_w ?>" height="12" rx="6" fill="#0e5a6d" />
                    <text x="296" y="<?= $y + 33 ?>" font-family="Inter, sans-serif" font-size="13" font-weight="600" fill="#0e5a6d">
                        <?= e(rupiah($km['total_hemat_kat'])) ?>
                    </text>
                <?php endforeach; ?>
            </svg>
        </div>
    </div>
</section>

<div class="pk-grid pk-grid--2">
    <section class="pk-card" aria-labelledby="jejak-pinjam-heading">
        <div class="pk-card-header">
            <h2 id="jejak-pinjam-heading" class="pk-card-title">
                <?= svg_icon('calendar', 18) ?>
                <span>Peminjaman Tuntas Terbaru</span>
            </h2>
        </div>

        <?php if (mysqli_num_rows($res_last_pinjam) === 0): ?>
            <div class="text-muted">Belum ada transaksi selesai.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="pk-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Alat &amp; Peminjam</th>
                            <th>Penghematan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($lp = mysqli_fetch_assoc($res_last_pinjam)): ?>
                            <tr>
                                <td><strong><?= e($lp['kode_booking']) ?></strong></td>
                                <td>
                                    <strong><?= e($lp['nama_alat']) ?></strong><br>
                                    <span class="text-muted" style="font-size:0.82rem;"><?= e($lp['nama_warga']) ?></span>
                                </td>
                                <td class="text-secondary fw-bold">+<?= rupiah($lp['nilai_hemat']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="pk-card" aria-labelledby="jejak-servis-heading">
        <div class="pk-card-header">
            <h2 id="jejak-servis-heading" class="pk-card-title">
                <?= svg_icon('wrench', 18) ?>
                <span>Perbaikan Tuntas Terbaru</span>
            </h2>
        </div>

        <?php if (mysqli_num_rows($res_last_servis) === 0): ?>
            <div class="text-muted">Belum ada tiket selesai.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="pk-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Barang &amp; Teknisi</th>
                            <th>Dampak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ls = mysqli_fetch_assoc($res_last_servis)): ?>
                            <tr>
                                <td><strong><?= e($ls['kode_tiket']) ?></strong></td>
                                <td>
                                    <strong><?= e($ls['nama_barang']) ?></strong><br>
                                    <span class="text-muted" style="font-size:0.82rem;"><?= e($ls['nama_teknisi'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <div class="text-secondary fw-bold">+<?= rupiah($ls['nilai_hemat']) ?></div>
                                    <div class="text-muted" style="font-size:0.82rem;"><?= number_format((float)$ls['rata_rata_berat_kg'], 2, ',', '.') ?> Kg</div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
