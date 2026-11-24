<?php
/**
 * Halaman pembuatan reservasi peminjaman alat.
 *
 * Tujuan : Memvalidasi kelayakan skor, durasi, dan bentrok jadwal sebelum membuat
 *          booking baru bagi Warga.
 * Akses  : Warga (role 'warga').
 * Alur   : Muat alat -> cek skor minimal -> POST (CSRF, tanggal, kontak, panduan)
 *          -> hitung durasi -> kunci baris alat -> cek bentrok -> simpan booking
 *          dalam transaksi -> redirect ke riwayat pinjam.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_role(['warga']);
refresh_user_session($conn);
bersihkan_ghost_booking($conn);

$warga      = $_SESSION['user'];
$id_warga   = (int)$warga['id_user'];
$skor_warga = (int)$warga['skor_kredibilitas'];

$id_alat = (int)($_GET['id_alat'] ?? ($_POST['id_alat'] ?? 0));
if ($id_alat <= 0) {
    set_flash('warning', 'Pilih perkakas dari katalog.');
    header('Location: ' . BASE_URL . '/katalog/index.php');
    exit;
}

$alat = pk_stmt_one(
    $conn,
    "SELECT a.*, k.nama_kategori, k.panduan_keselamatan
     FROM aset_alat a
     JOIN kategori_alat k ON a.id_kategori = k.id_kategori
     WHERE a.id_alat = ? LIMIT 1",
    'i',
    [$id_alat]
);

if (empty($alat) || in_array($alat['status_alat'], ['Menunggu Verifikasi', 'Perawatan'], true)) {
    set_flash('danger', 'Unit perkakas sedang tidak tersedia untuk dipesan.');
    header('Location: ' . BASE_URL . '/katalog/index.php');
    exit;
}

$min_skor     = (int)$alat['min_skor'];
$max_hari     = (int)$alat['max_hari_pinjam'];
$skor_cukup   = ($skor_warga >= $min_skor);
$selisih_skor = max(0, $min_skor - $skor_warga);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token()) {
    set_flash('danger', 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$skor_cukup) {
        set_flash('danger', 'Skor Kepercayaan Anda (' . $skor_warga . ' poin) belum memenuhi syarat minimal (' . $min_skor . ' poin).');
        header('Location: ' . BASE_URL . '/reservasi/reservasi.php?id_alat=' . $id_alat);
        exit;
    }

    $tgl_mulai      = trim($_POST['tgl_mulai'] ?? '');
    $tgl_selesai    = trim($_POST['tgl_selesai'] ?? '');
    $setuju_panduan = !empty($_POST['setuju_panduan']) ? 1 : 0;
    $today          = date('Y-m-d');

    if ($tgl_mulai === '' || $tgl_selesai === '') {
        set_flash('warning', 'Tanggal mulai dan tanggal selesai wajib diisi.');
    } elseif ($setuju_panduan !== 1) {
        set_flash('warning', 'Anda wajib menyetujui Panduan Keselamatan Kerja.');
    } elseif ($tgl_mulai < $today) {
        set_flash('warning', 'Tanggal mulai tidak boleh di masa lalu.');
    } elseif ($tgl_selesai < $tgl_mulai) {
        set_flash('warning', 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
    } else {
        $kontak_res = lengkapi_kontak_user($conn, $warga, $_POST);
        if (!$kontak_res['ok']) {
            set_flash('warning', $kontak_res['message']);
        } else {
            // Durasi inklusif: tanggal mulai dan selesai dihitung sebagai hari pinjam.
            $durasi_hari = (int)round((strtotime($tgl_selesai) - strtotime($tgl_mulai)) / 86400) + 1;
            if ($durasi_hari > $max_hari) {
                set_flash('danger', 'Durasi peminjaman (' . $durasi_hari . ' hari) melebihi batas maksimal (' . $max_hari . ' hari).');
            } else {
                mysqli_begin_transaction($conn);
                try {
                    // Kunci baris aset agar dua reservasi tidak memesan unit yang sama bersamaan.
                    $row_lock = pk_stmt_one($conn, 'SELECT status_alat FROM aset_alat WHERE id_alat = ? FOR UPDATE', 'i', [$id_alat]);

                    if (empty($row_lock) || in_array($row_lock['status_alat'], ['Perawatan', 'Menunggu Verifikasi'], true)) {
                        mysqli_rollback($conn);
                        set_flash('danger', 'Unit sedang dalam perawatan dan tidak dapat dipesan.');
                    } else {
                        $bentrok = cek_bentrok_jadwal($conn, $id_alat, $tgl_mulai, $tgl_selesai);
                        if ($bentrok > 0) {
                            // Tawarkan tanggal kosong terdekat setelah booking aktif terakhir.
                            $row_max = pk_stmt_one(
                                $conn,
                                "SELECT DATE_ADD(MAX(tgl_selesai), INTERVAL 1 DAY) AS tgl_kosong
                                 FROM peminjaman
                                 WHERE id_alat = ? AND status_peminjaman IN ('Dipesan', 'Dipinjam') AND tgl_selesai >= CURDATE()",
                                'i',
                                [$id_alat]
                            );

                            mysqli_rollback($conn);
                            $saran_tgl = !empty($row_max['tgl_kosong']) ? format_tanggal($row_max['tgl_kosong']) : format_tanggal($today);
                            set_flash('danger', 'Jadwal bentrok. Tanggal kosong terdekat: ' . $saran_tgl . '.');
                        } else {
                            $kode_booking = generate_kode_booking($conn);
                            $status_awal  = 'Dipesan';
                            $stmt_ins = mysqli_prepare($conn, 'INSERT INTO peminjaman (kode_booking, id_alat, id_warga, tgl_mulai, tgl_selesai, setuju_panduan, status_peminjaman) VALUES (?, ?, ?, ?, ?, ?, ?)');
                            mysqli_stmt_bind_param($stmt_ins, 'siissis', $kode_booking, $id_alat, $id_warga, $tgl_mulai, $tgl_selesai, $setuju_panduan, $status_awal);
                            mysqli_stmt_execute($stmt_ins);
                            mysqli_stmt_close($stmt_ins);

                            mysqli_commit($conn);
                            set_flash('success', 'Reservasi berhasil dibuat. Kode Booking: ' . $kode_booking . '.');
                            header('Location: ' . BASE_URL . '/reservasi/riwayat_pinjam.php');
                            exit;
                        }
                    }
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    set_flash('danger', 'Gagal memproses reservasi.');
                }
            }
        }
    }
}

$jadwal_terisi = pk_stmt_all(
    $conn,
    "SELECT kode_booking, tgl_mulai, tgl_selesai, status_peminjaman
     FROM peminjaman
     WHERE id_alat = ? AND status_peminjaman IN ('Dipesan', 'Dipinjam') AND tgl_selesai >= CURDATE()
     ORDER BY tgl_mulai ASC",
    'i',
    [$id_alat]
);

$default_mulai   = $_POST['tgl_mulai'] ?? ($_GET['tgl_mulai'] ?? date('Y-m-d'));
$default_selesai = $_POST['tgl_selesai'] ?? ($_GET['tgl_selesai'] ?? date('Y-m-d', strtotime('+1 day')));

$page_title = 'Reservasi Alat';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Reservasi Perkakas</h1>
    <a href="<?= BASE_URL ?>/katalog/detail_alat.php?id=<?= (int)$alat['id_alat'] ?>" class="btn btn-muted">Kembali</a>
</div>

<?php if (!$skor_cukup): ?>
    <?= render_alert('danger', 'Skor Kepercayaan Anda ' . $skor_warga . ' poin (syarat minimal ' . $alat['nama_alat'] . ': ' . $min_skor . ' poin, kurang ' . $selisih_skor . ' poin).', false) ?>
<?php endif; ?>

<div class="pk-grid pk-grid--2">
    <section class="pk-card" aria-labelledby="form-res-heading">
        <div class="pk-card-header">
            <h2 id="form-res-heading" class="pk-card-title">
                <?= svg_icon('calendar', 18) ?>
                <span>Jadwal &amp; Persetujuan</span>
            </h2>
        </div>

        <form method="post" action="<?= BASE_URL ?>/reservasi/reservasi.php?id_alat=<?= (int)$alat['id_alat'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id_alat" value="<?= (int)$alat['id_alat'] ?>">

            <div class="pk-grid pk-grid--2">
                <div class="form-group">
                    <label for="tgl_mulai" class="form-label">Tanggal Mulai</label>
                    <input type="date" id="tgl_mulai" name="tgl_mulai" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($default_mulai) ?>" required <?= !$skor_cukup ? 'disabled' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="tgl_selesai" class="form-label">Tanggal Selesai</label>
                    <input type="date" id="tgl_selesai" name="tgl_selesai" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($default_selesai) ?>" required <?= !$skor_cukup ? 'disabled' : '' ?>>
                </div>
            </div>

            <div class="mb-3">
                <span id="durasiInfoBox" class="form-help fw-bold" data-max-hari="<?= (int)$max_hari ?>">
                    Maksimal <?= (int)$max_hari ?> hari.
                </span>
            </div>

            <?= render_kontak_fields_if_needed($warga, $_POST, !$skor_cukup) ?>

            <div class="form-group">
                <label class="form-label">Panduan Keselamatan (<?= e($alat['nama_kategori']) ?>)</label>
                <div class="pk-guideline-box mb-3"><?= e($alat['panduan_keselamatan']) ?></div>

                <div class="form-check">
                    <input type="checkbox" id="setuju_panduan" name="setuju_panduan" value="1" class="form-check-input" required <?= !$skor_cukup ? 'disabled' : '' ?>>
                    <label for="setuju_panduan" class="form-check-label">
                        Saya menyetujui panduan keselamatan kerja dan bertanggung jawab menjaga kondisi alat.
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" <?= !$skor_cukup ? 'disabled aria-disabled="true"' : '' ?>>
                <?= svg_icon('check-circle', 16) ?>
                <span><?= $skor_cukup ? 'Buat Reservasi' : 'Skor Tidak Cukup' ?></span>
            </button>
        </form>
    </section>

    <div>
        <section class="pk-card" aria-labelledby="ringkasan-alat-heading">
            <div class="pk-card-header">
                <h2 id="ringkasan-alat-heading" class="pk-card-title">
                    <?= svg_icon('box', 18) ?>
                    <span><?= e($alat['nama_alat']) ?></span>
                </h2>
            </div>
            <ul class="pk-tool-meta mb-0">
                <li>
                    <span>Skor Anda</span>
                    <strong class="<?= $skor_cukup ? 'text-secondary' : 'text-danger' ?>"><?= $skor_warga ?> Poin</strong>
                </li>
                <li>
                    <span>Skor Minimal</span>
                    <strong><?= $min_skor ?> Poin</strong>
                </li>
                <li>
                    <span>Maks. Pinjam</span>
                    <strong><?= $max_hari ?> Hari</strong>
                </li>
                <li>
                    <span>Biaya Rawat</span>
                    <strong><?= (float)$alat['biaya_perawatan'] <= 0 ? 'Gratis' : rupiah($alat['biaya_perawatan']) ?></strong>
                </li>
            </ul>
        </section>

        <section class="pk-table-card" aria-labelledby="kalender-terisi-heading">
            <div class="pk-table-card-head">
                <h2 id="kalender-terisi-heading" class="pk-card-title">
                    <?= svg_icon('calendar', 18) ?>
                    <span>Jadwal Terisi</span>
                </h2>
            </div>

            <?php if (count($jadwal_terisi) === 0): ?>
                <div class="text-secondary fw-bold" style="padding:18px 20px;">Semua tanggal mulai hari ini tersedia.</div>
            <?php else: ?>
                <div class="pk-table-scroll">
                    <table class="pk-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Mulai</th>
                                <th>Selesai</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal_terisi as $j): ?>
                                <tr>
                                    <td class="pk-td-head">
                                        <div class="pk-td-head-row">
                                            <strong class="pk-nowrap"><?= e($j['kode_booking']) ?></strong>
                                            <span class="pk-mobile-badge"><?= badge_status_pinjam($j['status_peminjaman']) ?></span>
                                        </div>
                                    </td>
                                    <td class="pk-nowrap" data-label="Mulai"><?= e(format_tanggal($j['tgl_mulai'])) ?></td>
                                    <td class="pk-nowrap" data-label="Selesai"><?= e(format_tanggal($j['tgl_selesai'])) ?></td>
                                    <td class="pk-desktop-cell" data-label="Status"><?= badge_status_pinjam($j['status_peminjaman']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
