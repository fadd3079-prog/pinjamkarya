<?php
require_once __DIR__ . '/config/app.php';
require_login();
refresh_user_session($conn);
bersihkan_ghost_booking($conn);

$user = $_SESSION['user'];
$id_user = (int)$user['id_user'];
$role = (string)$user['role'];
$display_name = format_nama_pengguna((string)$user['nama']);

function pk_stmt_all(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function pk_stmt_one(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $rows = pk_stmt_all($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function pk_build_monthly_trend(array $rows_a, array $rows_b): array {
    $bulan_short = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $map_a = [];
    foreach ($rows_a as $r) {
        $map_a[(string)$r['ym']] = (int)$r['cnt'];
    }
    $map_b = [];
    foreach ($rows_b as $r) {
        $map_b[(string)$r['ym']] = (int)$r['cnt'];
    }

    $base = strtotime(date('Y-m-01'));
    $points = [];
    $max = 1;
    $sum_a = 0;
    $sum_b = 0;
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-{$i} months", $base);
        $ym = date('Y-m', $ts);
        $a = $map_a[$ym] ?? 0;
        $b = $map_b[$ym] ?? 0;
        $max = max($max, $a, $b);
        $sum_a += $a;
        $sum_b += $b;
        $points[] = [
            'label' => $bulan_short[(int)date('n', $ts)] ?? date('M', $ts),
            'a'     => $a,
            'b'     => $b,
        ];
    }

    return [
        'points' => $points,
        'max'    => $max,
        'sum_a'  => $sum_a,
        'sum_b'  => $sum_b,
    ];
}

function pk_render_svg_trend(array $trend, string $label_a, string $label_b): string {
    $w = 540;
    $h = 180;
    $pad_l = 28;
    $pad_r = 12;
    $pad_t = 18;
    $pad_b = 26;
    $plot_w = $w - $pad_l - $pad_r;
    $plot_h = $h - $pad_t - $pad_b;
    $base_y = $pad_t + $plot_h;
    $max = max(1, (int)$trend['max']);
    $points = $trend['points'];
    $slot_w = $plot_w / max(1, count($points));
    $bar_w = 18;
    $gap = 5;

    ob_start();
    ?>
<svg class="pk-svg-chart" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="Grafik tren 6 bulan: <?= e($label_a) ?> dan <?= e($label_b) ?>">
    <title>Tren 6 bulan: <?= e($label_a) ?> &amp; <?= e($label_b) ?></title>
    <?php foreach ([0.25, 0.5, 0.75, 1.0] as $step): ?>
        <?php
        $gy = round($base_y - ($plot_h * $step), 1);
        $gval = (int)round($max * $step);
        ?>
        <line x1="<?= $pad_l ?>" y1="<?= $gy ?>" x2="<?= $w - $pad_r ?>" y2="<?= $gy ?>" class="pk-chart-gridline"/>
        <?php if ($step === 0.5 || $step === 1.0): ?>
            <text x="<?= $pad_l - 6 ?>" y="<?= $gy + 4 ?>" text-anchor="end" class="pk-chart-axis-text"><?= $gval ?></text>
        <?php endif; ?>
    <?php endforeach; ?>
    <line x1="<?= $pad_l ?>" y1="<?= $base_y ?>" x2="<?= $w - $pad_r ?>" y2="<?= $base_y ?>" class="pk-chart-baseline"/>
    <text x="<?= $pad_l - 6 ?>" y="<?= $base_y + 4 ?>" text-anchor="end" class="pk-chart-axis-text">0</text>

    <?php foreach ($points as $idx => $pt): ?>
        <?php
        $cx = $pad_l + ($slot_w * $idx) + ($slot_w / 2);
        $xa = round($cx - $bar_w - ($gap / 2), 1);
        $xb = round($cx + ($gap / 2), 1);
        $ha = $pt['a'] > 0 ? max(4, round(($pt['a'] / $max) * $plot_h, 1)) : 2;
        $hb = $pt['b'] > 0 ? max(4, round(($pt['b'] / $max) * $plot_h, 1)) : 2;
        $ya = round($base_y - $ha, 1);
        $yb = round($base_y - $hb, 1);
        ?>
        <g>
            <rect x="<?= $xa ?>" y="<?= $ya ?>" width="<?= $bar_w ?>" height="<?= $ha ?>" rx="4" class="pk-chart-bar-a">
                <title><?= e($pt['label']) ?> - <?= e($label_a) ?>: <?= (int)$pt['a'] ?></title>
            </rect>
            <?php if ($pt['a'] > 0): ?>
                <text x="<?= round($xa + ($bar_w / 2), 1) ?>" y="<?= $ya - 4 ?>" text-anchor="middle" class="pk-chart-val-text"><?= (int)$pt['a'] ?></text>
            <?php endif; ?>

            <rect x="<?= $xb ?>" y="<?= $yb ?>" width="<?= $bar_w ?>" height="<?= $hb ?>" rx="4" class="pk-chart-bar-b">
                <title><?= e($pt['label']) ?> - <?= e($label_b) ?>: <?= (int)$pt['b'] ?></title>
            </rect>
            <?php if ($pt['b'] > 0): ?>
                <text x="<?= round($xb + ($bar_w / 2), 1) ?>" y="<?= $yb - 4 ?>" text-anchor="middle" class="pk-chart-val-text"><?= (int)$pt['b'] ?></text>
            <?php endif; ?>

            <text x="<?= round($cx, 1) ?>" y="<?= $base_y + 18 ?>" text-anchor="middle" class="pk-chart-axis-text"><?= e($pt['label']) ?></text>
        </g>
    <?php endforeach; ?>
</svg>
    <?php
    return ob_get_clean();
}

$since_month = date('Y-m-01 00:00:00', strtotime('-5 months', strtotime(date('Y-m-01'))));
$status_pill = '';
$header_actions = [];
$kpis = [];
$trend_label_a = 'Peminjaman';
$trend_label_b = 'Perbaikan';
$trend_data = [];
$dist_title = 'Status Aset';
$dist_total = 0;
$dist_items = [];
$meters = [];
$queue_link_href = '';
$queue_link_label = 'Lihat Semua';
$queue_rows = [];
$empty_queue = [];
$shortcuts = [];

if ($role === 'admin') {
    $aset_stats = pk_stmt_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(status_alat = 'Tersedia'), 0) AS tersedia,
        COALESCE(SUM(status_alat = 'Dipinjam'), 0) AS dipinjam,
        COALESCE(SUM(status_alat = 'Perawatan'), 0) AS perawatan,
        COALESCE(SUM(status_alat = 'Menunggu Verifikasi'), 0) AS menunggu_verif
        FROM aset_alat");
    $pinjam_stats = pk_stmt_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(status_peminjaman = 'Dipesan'), 0) AS dipesan,
        COALESCE(SUM(status_peminjaman = 'Dipinjam'), 0) AS dipinjam,
        COALESCE(SUM(status_peminjaman = 'Selesai'), 0) AS selesai
        FROM peminjaman");
    $tiket_stats = pk_stmt_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(status_tiket = 'Menunggu Diagnosis'), 0) AS diagnosis,
        COALESCE(SUM(status_tiket = 'Siap Diambil'), 0) AS siap_ambil,
        COALESCE(SUM(status_tiket = 'Selesai'), 0) AS selesai,
        COALESCE(SUM(status_tiket = 'Dibatalkan'), 0) AS dibatalkan
        FROM tiket_perbaikan");
    $tek_pending = (int)(pk_stmt_one($conn, "SELECT COUNT(*) AS cnt FROM users WHERE is_teknisi = 1 AND is_verified_teknisi = 0")['cnt'] ?? 0);
    $hemat_pinjam = (float)(pk_stmt_one($conn, "SELECT COALESCE(SUM(GREATEST(a.harga_beli_baru - a.biaya_perawatan, 0)), 0) AS total FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE p.status_peminjaman = 'Selesai'")['total'] ?? 0);
    $hemat_servis = (float)(pk_stmt_one($conn, "SELECT COALESCE(SUM(GREATEST(k.estimasi_harga_baru - t.estimasi_biaya, 0)), 0) AS total FROM tiket_perbaikan t JOIN kategori_alat k ON t.id_kategori = k.id_kategori WHERE t.status_tiket = 'Selesai'")['total'] ?? 0);

    $total_alat = (int)($aset_stats['total'] ?? 0);
    $alat_tersedia = (int)($aset_stats['tersedia'] ?? 0);
    $alat_dipinjam = (int)($aset_stats['dipinjam'] ?? 0);
    $alat_perawatan = (int)($aset_stats['perawatan'] ?? 0);
    $alat_verif = (int)($aset_stats['menunggu_verif'] ?? 0);

    $tx_dipesan = (int)($pinjam_stats['dipesan'] ?? 0);
    $tx_selesai = (int)($pinjam_stats['selesai'] ?? 0);

    $tk_diag = (int)($tiket_stats['diagnosis'] ?? 0);
    $tk_siap = (int)($tiket_stats['siap_ambil'] ?? 0);
    $tk_selesai = (int)($tiket_stats['selesai'] ?? 0);
    $tk_batal = (int)($tiket_stats['dibatalkan'] ?? 0);
    $tk_aktif = ((int)($tiket_stats['total'] ?? 0)) - $tk_selesai - $tk_batal;
    $urgent_count = $alat_verif + $tek_pending + $tx_dipesan;

    if ($urgent_count > 0) {
        $status_pill = '<span class="pk-badge pk-badge--danger">' . $urgent_count . ' Antrean Tindakan</span>';
    } elseif ($tk_diag > 0) {
        $status_pill = '<span class="pk-badge pk-badge--warning">' . $tk_diag . ' Perlu Diagnosis</span>';
    }

    $header_actions = [
        ['label' => 'Inventaris Alat', 'href' => BASE_URL . '/admin/kelola_aset.php', 'icon' => 'box', 'primary' => true],
        ['label' => 'Transaksi Pinjam', 'href' => BASE_URL . '/admin/kelola_transaksi.php', 'icon' => 'calendar', 'primary' => false],
    ];

    $kpis = [
        [
            'label' => 'Perlu Tindakan',
            'value' => $urgent_count . ' Item',
            'foot'  => "{$tx_dipesan} serah • " . ($alat_verif + $tek_pending) . " verif",
            'icon'  => 'alert-triangle',
            'href'  => BASE_URL . ($alat_verif > 0 ? '/admin/kelola_aset.php' : ($tek_pending > 0 ? '/admin/kelola_user.php' : '/admin/kelola_transaksi.php')),
            'tone'  => $urgent_count >= 3 ? 'danger' : ($urgent_count > 0 ? 'warn' : 'default'),
        ],
        [
            'label' => 'Aset Siap',
            'value' => $alat_tersedia . ' / ' . $total_alat,
            'foot'  => "{$alat_dipinjam} pinjam • {$alat_perawatan} rawat",
            'icon'  => 'box',
            'href'  => BASE_URL . '/admin/kelola_aset.php',
            'tone'  => 'ok',
        ],
        [
            'label' => 'Tiket Terbuka',
            'value' => $tk_aktif . ' Tiket',
            'foot'  => "{$tk_diag} diagnosis • {$tk_siap} siap ambil",
            'icon'  => 'wrench',
            'href'  => BASE_URL . '/admin/kelola_tiket.php',
            'tone'  => $tk_diag > 0 ? 'warn' : ($tk_aktif > 0 ? 'info' : 'default'),
        ],
        [
            'label' => 'Penghematan',
            'value' => rupiah($hemat_pinjam + $hemat_servis),
            'foot'  => "{$tx_selesai} pinjam • {$tk_selesai} servis",
            'icon'  => 'leaf',
            'href'  => BASE_URL . '/admin/riwayat_inspeksi.php',
            'tone'  => 'info',
        ],
    ];

    $rows_a = pk_stmt_all($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM peminjaman WHERE created_at >= ? GROUP BY ym ORDER BY ym", 's', [$since_month]);
    $rows_b = pk_stmt_all($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM tiket_perbaikan WHERE created_at >= ? GROUP BY ym ORDER BY ym", 's', [$since_month]);
    $trend_data = pk_build_monthly_trend($rows_a, $rows_b);

    $dist_title = 'Status Inventaris';
    $dist_total = $total_alat;
    $dist_items = [
        ['label' => 'Tersedia', 'count' => $alat_tersedia, 'seg' => 'ok'],
        ['label' => 'Dipinjam', 'count' => $alat_dipinjam, 'seg' => 'info'],
        ['label' => 'Perawatan', 'count' => $alat_perawatan, 'seg' => 'warn'],
        ['label' => 'Verifikasi', 'count' => $alat_verif, 'seg' => 'err'],
    ];

    $pct_ready = $total_alat > 0 ? (int)round(($alat_tersedia / $total_alat) * 100) : 0;
    $tk_valid = max(0, ((int)($tiket_stats['total'] ?? 0)) - $tk_batal);
    $pct_servis = $tk_valid > 0 ? (int)round(($tk_selesai / $tk_valid) * 100) : 0;
    $meters = [
        ['label' => 'Kesiapan Alat', 'val' => $pct_ready . '%', 'pct' => $pct_ready, 'tone' => 'ok'],
        ['label' => 'Servis Selesai', 'val' => "{$tk_selesai}/{$tk_valid} ({$pct_servis}%)", 'pct' => $pct_servis, 'tone' => 'info'],
    ];

    $queue_link_href = BASE_URL . '/admin/kelola_transaksi.php';
    $queue_link_label = 'Semua Transaksi';

    $recent_loans = pk_stmt_all($conn, "SELECT p.id_peminjaman, p.kode_booking, p.status_peminjaman, p.tgl_mulai, p.created_at, a.nama_alat, w.nama AS nama_warga FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat JOIN users w ON p.id_warga = w.id_user ORDER BY p.created_at DESC LIMIT 4");
    $recent_tickets = pk_stmt_all($conn, "SELECT t.id_tiket, t.kode_tiket, t.status_tiket, t.nama_barang, t.created_at, u.nama AS nama_pelapor FROM tiket_perbaikan t JOIN users u ON t.id_pelapor = u.id_user ORDER BY t.created_at DESC LIMIT 4");

    $merged = [];
    foreach ($recent_loans as $ln) {
        $action_url = $ln['status_peminjaman'] === 'Dipesan'
            ? BASE_URL . '/admin/inspeksi.php?id=' . (int)$ln['id_peminjaman'] . '&jenis=Awal'
            : ($ln['status_peminjaman'] === 'Dipinjam'
                ? BASE_URL . '/admin/inspeksi.php?id=' . (int)$ln['id_peminjaman'] . '&jenis=Akhir'
                : BASE_URL . '/admin/riwayat_inspeksi.php?id_peminjaman=' . (int)$ln['id_peminjaman']);
        $merged[] = [
            'sort_ts' => strtotime((string)$ln['created_at']),
            'code'    => $ln['kode_booking'],
            'kind'    => 'Peminjaman',
            'title'   => $ln['nama_alat'],
            'sub'     => format_nama_pengguna((string)$ln['nama_warga']),
            'date'    => format_tanggal($ln['tgl_mulai']),
            'badge'   => badge_status_pinjam($ln['status_peminjaman']),
            'href'    => $action_url,
            'cta'     => in_array($ln['status_peminjaman'], ['Dipesan', 'Dipinjam'], true) ? 'Inspeksi' : 'Log',
        ];
    }
    foreach ($recent_tickets as $tk) {
        $merged[] = [
            'sort_ts' => strtotime((string)$tk['created_at']),
            'code'    => $tk['kode_tiket'],
            'kind'    => 'Servis',
            'title'   => $tk['nama_barang'],
            'sub'     => format_nama_pengguna((string)$tk['nama_pelapor']),
            'date'    => format_tanggal($tk['created_at']),
            'badge'   => badge_status_tiket($tk['status_tiket']),
            'href'    => BASE_URL . '/admin/kelola_tiket.php',
            'cta'     => 'Kelola',
        ];
    }
    usort($merged, static function (array $x, array $y): int {
        return $y['sort_ts'] <=> $x['sort_ts'];
    });
    $queue_rows = array_slice($merged, 0, 6);
    $empty_queue = ['icon' => 'clipboard', 'title' => 'Belum Ada Aktivitas', 'btn' => 'Buka Inventaris', 'href' => BASE_URL . '/admin/kelola_aset.php'];

    $shortcuts = [
        ['label' => 'Verifikasi Alat', 'icon' => 'box', 'href' => BASE_URL . '/admin/kelola_aset.php', 'count' => $alat_verif . ' antrean', 'urgent' => $alat_verif > 0],
        ['label' => 'Verifikasi Teknisi', 'icon' => 'users', 'href' => BASE_URL . '/admin/kelola_user.php', 'count' => $tek_pending . ' antrean', 'urgent' => $tek_pending > 0],
        ['label' => 'Log Inspeksi', 'icon' => 'clipboard', 'href' => BASE_URL . '/admin/riwayat_inspeksi.php', 'count' => $tx_selesai . ' selesai', 'urgent' => false],
        ['label' => 'Master Kategori', 'icon' => 'tag', 'href' => BASE_URL . '/admin/kelola_kategori.php', 'count' => 'Kelola', 'urgent' => false],
    ];
} elseif ($role === 'mitra') {
    $is_kontrib = !empty($user['is_kontributor']);
    $is_teknisi = !empty($user['is_teknisi']);
    $is_verified = !empty($user['is_verified_teknisi']);

    $aset_m = pk_stmt_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(status_alat = 'Tersedia'), 0) AS tersedia,
        COALESCE(SUM(status_alat = 'Dipinjam'), 0) AS dipinjam,
        COALESCE(SUM(status_alat = 'Perawatan'), 0) AS perawatan,
        COALESCE(SUM(status_alat = 'Menunggu Verifikasi'), 0) AS pending
        FROM aset_alat WHERE id_pemilik = ?", 'i', [$id_user]);
    $pinjam_m = pk_stmt_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(p.status_peminjaman IN ('Dipesan', 'Dipinjam')), 0) AS aktif,
        COALESCE(SUM(p.status_peminjaman = 'Selesai'), 0) AS selesai
        FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE a.id_pemilik = ?", 'i', [$id_user]);
    $antrean_diag = (int)(pk_stmt_one($conn, "SELECT COUNT(*) AS cnt FROM tiket_perbaikan WHERE status_tiket = 'Menunggu Diagnosis'")['cnt'] ?? 0);
    $tiket_m = pk_stmt_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(status_tiket IN ('Menunggu Persetujuan', 'Dikerjakan', 'Siap Diambil')), 0) AS aktif,
        COALESCE(SUM(status_tiket = 'Dikerjakan'), 0) AS dikerjakan,
        COALESCE(SUM(status_tiket = 'Siap Diambil'), 0) AS siap,
        COALESCE(SUM(status_tiket = 'Selesai'), 0) AS selesai,
        COALESCE(SUM(CASE WHEN status_tiket = 'Selesai' THEN estimasi_biaya ELSE 0 END), 0) AS nilai_selesai
        FROM tiket_perbaikan WHERE id_teknisi = ?", 'i', [$id_user]);

    $m_total_alat = (int)($aset_m['total'] ?? 0);
    $m_tersedia = (int)($aset_m['tersedia'] ?? 0);
    $m_dipinjam = (int)($aset_m['dipinjam'] ?? 0);
    $m_rawat = (int)($aset_m['perawatan'] ?? 0);
    $m_pending = (int)($aset_m['pending'] ?? 0);
    $m_pinjam_aktif = (int)($pinjam_m['aktif'] ?? 0);
    $m_pinjam_selesai = (int)($pinjam_m['selesai'] ?? 0);
    $m_tk_aktif = (int)($tiket_m['aktif'] ?? 0);
    $m_tk_selesai = (int)($tiket_m['selesai'] ?? 0);
    $m_tk_total = (int)($tiket_m['total'] ?? 0);

    if ($is_teknisi && !$is_verified) {
        $status_pill = '<span class="pk-badge pk-badge--warning">Teknisi Pending</span>';
    } elseif ($is_teknisi && $antrean_diag > 0) {
        $status_pill = '<span class="pk-badge pk-badge--warning">' . $antrean_diag . ' Diagnosis Baru</span>';
    }

    if ($is_kontrib) {
        $header_actions[] = ['label' => 'Alat Titipan', 'href' => BASE_URL . '/mitra/aset_mitra.php', 'icon' => 'box', 'primary' => true];
    }
    if ($is_teknisi) {
        $header_actions[] = ['label' => 'Meja Teknisi', 'href' => BASE_URL . '/mitra/antrean_teknisi.php', 'icon' => 'wrench', 'primary' => !$is_kontrib];
    }
    if (empty($header_actions)) {
        $header_actions[] = ['label' => 'Lihat Katalog', 'href' => BASE_URL . '/katalog/index.php', 'icon' => 'grid', 'primary' => true];
    }

    if ($is_teknisi && !$is_kontrib) {
        $kpis = [
            [
                'label' => 'Antrean Servis',
                'value' => $is_verified ? ($antrean_diag . ' Tiket') : 'Pending',
                'foot'  => $is_verified ? 'Menunggu diagnosis' : 'Menunggu verif Admin',
                'icon'  => 'alert-triangle',
                'href'  => BASE_URL . '/mitra/antrean_teknisi.php',
                'tone'  => !$is_verified ? 'warn' : ($antrean_diag > 0 ? 'warn' : 'default'),
            ],
            [
                'label' => 'Tugas Aktif',
                'value' => $m_tk_aktif . ' Tiket',
                'foot'  => ((int)($tiket_m['dikerjakan'] ?? 0)) . ' kerja • ' . ((int)($tiket_m['siap'] ?? 0)) . ' siap ambil',
                'icon'  => 'wrench',
                'href'  => BASE_URL . '/mitra/antrean_teknisi.php',
                'tone'  => $m_tk_aktif > 0 ? 'info' : 'default',
            ],
            [
                'label' => 'Servis Selesai',
                'value' => $m_tk_selesai . ' Unit',
                'foot'  => "Dari {$m_tk_total} tiket",
                'icon'  => 'check-circle',
                'href'  => BASE_URL . '/mitra/antrean_teknisi.php',
                'tone'  => $m_tk_selesai > 0 ? 'ok' : 'default',
            ],
            [
                'label' => 'Nilai Jasa',
                'value' => rupiah((float)($tiket_m['nilai_selesai'] ?? 0)),
                'foot'  => 'Total servis selesai',
                'icon'  => 'bar-chart',
                'href'  => BASE_URL . '/auth/profil.php',
                'tone'  => 'info',
            ],
        ];
    } elseif ($is_kontrib && $is_teknisi) {
        $kpis = [
            [
                'label' => 'Antrean Servis',
                'value' => $is_verified ? ($antrean_diag . ' Tiket') : 'Pending',
                'foot'  => $is_verified ? "{$m_tk_aktif} aktif saya" : 'Belum terverifikasi',
                'icon'  => 'wrench',
                'href'  => BASE_URL . '/mitra/antrean_teknisi.php',
                'tone'  => !$is_verified ? 'warn' : ($antrean_diag > 0 ? 'warn' : 'default'),
            ],
            [
                'label' => 'Alat Titipan',
                'value' => $m_total_alat . ' Unit',
                'foot'  => "{$m_tersedia} siap • {$m_pending} verif",
                'icon'  => 'box',
                'href'  => BASE_URL . '/mitra/aset_mitra.php',
                'tone'  => $m_pending > 0 ? 'warn' : 'ok',
            ],
            [
                'label' => 'Sedang Dipinjam',
                'value' => $m_pinjam_aktif . ' Aktif',
                'foot'  => "{$m_pinjam_selesai} selesai",
                'icon'  => 'calendar',
                'href'  => BASE_URL . '/admin/riwayat_inspeksi.php',
                'tone'  => $m_pinjam_aktif > 0 ? 'info' : 'default',
            ],
            [
                'label' => 'Servis Selesai',
                'value' => $m_tk_selesai . ' Tiket',
                'foot'  => rupiah((float)($tiket_m['nilai_selesai'] ?? 0)),
                'icon'  => 'check-circle',
                'href'  => BASE_URL . '/mitra/antrean_teknisi.php',
                'tone'  => 'ok',
            ],
        ];
    } else {
        $kpis = [
            [
                'label' => 'Menunggu Verifikasi',
                'value' => $m_pending . ' Unit',
                'foot'  => $m_pending > 0 ? 'Tinjauan Admin' : 'Sudah ditinjau',
                'icon'  => 'alert-triangle',
                'href'  => BASE_URL . '/mitra/aset_mitra.php',
                'tone'  => $m_pending > 0 ? 'warn' : 'default',
            ],
            [
                'label' => 'Alat Titipan',
                'value' => $m_total_alat . ' Unit',
                'foot'  => "{$m_tersedia} siap • {$m_rawat} rawat",
                'icon'  => 'box',
                'href'  => BASE_URL . '/mitra/aset_mitra.php',
                'tone'  => $m_total_alat > 0 ? 'ok' : 'default',
            ],
            [
                'label' => 'Sedang Dipinjam',
                'value' => $m_pinjam_aktif . ' Aktif',
                'foot'  => ((int)($pinjam_m['total'] ?? 0)) . ' total pinjam',
                'icon'  => 'calendar',
                'href'  => BASE_URL . '/admin/riwayat_inspeksi.php',
                'tone'  => $m_pinjam_aktif > 0 ? 'info' : 'default',
            ],
            [
                'label' => 'Pinjam Selesai',
                'value' => $m_pinjam_selesai . ' Kali',
                'foot'  => 'Kembali tuntas',
                'icon'  => 'check-circle',
                'href'  => BASE_URL . '/admin/riwayat_inspeksi.php',
                'tone'  => $m_pinjam_selesai > 0 ? 'ok' : 'default',
            ],
        ];
    }

    $trend_label_a = 'Sewa Aset';
    $trend_label_b = 'Servis';
    $rows_a = $is_kontrib
        ? pk_stmt_all($conn, "SELECT DATE_FORMAT(p.created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE a.id_pemilik = ? AND p.created_at >= ? GROUP BY ym ORDER BY ym", 'is', [$id_user, $since_month])
        : [];
    $rows_b = $is_teknisi
        ? pk_stmt_all($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM tiket_perbaikan WHERE id_teknisi = ? AND created_at >= ? GROUP BY ym ORDER BY ym", 'is', [$id_user, $since_month])
        : [];
    $trend_data = pk_build_monthly_trend($rows_a, $rows_b);

    if ($is_kontrib) {
        $dist_title = 'Status Unit Titipan';
        $dist_total = $m_total_alat;
        $dist_items = [
            ['label' => 'Tersedia', 'count' => $m_tersedia, 'seg' => 'ok'],
            ['label' => 'Dipinjam', 'count' => $m_dipinjam, 'seg' => 'info'],
            ['label' => 'Perawatan', 'count' => $m_rawat, 'seg' => 'warn'],
            ['label' => 'Verifikasi', 'count' => $m_pending, 'seg' => 'err'],
        ];
        $pct_siap = $m_total_alat > 0 ? (int)round(($m_tersedia / $m_total_alat) * 100) : 0;
        $meters[] = ['label' => 'Kesiapan Unit', 'val' => "{$m_tersedia}/{$m_total_alat} ({$pct_siap}%)", 'pct' => $pct_siap, 'tone' => 'ok'];
    } else {
        $tk_wait = (int)($tiket_m['dikerjakan'] ?? 0);
        $tk_pick = (int)($tiket_m['siap'] ?? 0);
        $dist_title = 'Status Tiket Teknisi';
        $dist_total = $m_tk_total;
        $dist_items = [
            ['label' => 'Selesai', 'count' => $m_tk_selesai, 'seg' => 'ok'],
            ['label' => 'Dikerjakan', 'count' => $tk_wait, 'seg' => 'info'],
            ['label' => 'Siap Ambil', 'count' => $tk_pick, 'seg' => 'warn'],
            ['label' => 'Menunggu', 'count' => max(0, $m_tk_total - $m_tk_selesai - $tk_wait - $tk_pick), 'seg' => 'mute'],
        ];
    }
    if ($is_teknisi) {
        $pct_job = $m_tk_total > 0 ? (int)round(($m_tk_selesai / $m_tk_total) * 100) : 0;
        $meters[] = ['label' => 'Penyelesaian Servis', 'val' => "{$m_tk_selesai}/{$m_tk_total} ({$pct_job}%)", 'pct' => $pct_job, 'tone' => 'info'];
    }

    $queue_link_href = BASE_URL . ($is_teknisi ? '/mitra/antrean_teknisi.php' : '/mitra/aset_mitra.php');
    $queue_link_label = $is_teknisi ? 'Meja Teknisi' : 'Kelola Alat';

    $merged = [];
    if ($is_teknisi) {
        $jobs = pk_stmt_all($conn, "SELECT t.id_tiket, t.kode_tiket, t.nama_barang, t.status_tiket, t.created_at, u.nama AS pelapor FROM tiket_perbaikan t JOIN users u ON t.id_pelapor = u.id_user WHERE t.id_teknisi = ? OR t.status_tiket = 'Menunggu Diagnosis' ORDER BY (t.status_tiket = 'Menunggu Diagnosis') DESC, t.created_at DESC LIMIT 5", 'i', [$id_user]);
        foreach ($jobs as $jb) {
            $merged[] = [
                'sort_ts' => strtotime((string)$jb['created_at']),
                'code'    => $jb['kode_tiket'],
                'kind'    => 'Servis',
                'title'   => $jb['nama_barang'],
                'sub'     => format_nama_pengguna((string)$jb['pelapor']),
                'date'    => format_tanggal($jb['created_at']),
                'badge'   => badge_status_tiket($jb['status_tiket']),
                'href'    => BASE_URL . '/mitra/antrean_teknisi.php',
                'cta'     => $jb['status_tiket'] === 'Menunggu Diagnosis' ? 'Ambil' : 'Update',
            ];
        }
    }
    if ($is_kontrib) {
        $asset_loans = pk_stmt_all($conn, "SELECT p.id_peminjaman, p.kode_booking, p.tgl_mulai, p.status_peminjaman, p.created_at, a.nama_alat, w.nama AS warga FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat JOIN users w ON p.id_warga = w.id_user WHERE a.id_pemilik = ? ORDER BY p.created_at DESC LIMIT 4", 'i', [$id_user]);
        foreach ($asset_loans as $al) {
            $merged[] = [
                'sort_ts' => strtotime((string)$al['created_at']),
                'code'    => $al['kode_booking'],
                'kind'    => 'Sewa Aset',
                'title'   => $al['nama_alat'],
                'sub'     => format_nama_pengguna((string)$al['warga']),
                'date'    => format_tanggal($al['tgl_mulai']),
                'badge'   => badge_status_pinjam($al['status_peminjaman']),
                'href'    => BASE_URL . '/admin/riwayat_inspeksi.php?id_peminjaman=' . (int)$al['id_peminjaman'],
                'cta'     => 'Inspeksi',
            ];
        }
    }
    usort($merged, static function (array $x, array $y): int {
        return $y['sort_ts'] <=> $x['sort_ts'];
    });
    $queue_rows = array_slice($merged, 0, 6);
    $empty_queue = [
        'icon'  => $is_teknisi ? 'wrench' : 'box',
        'title' => 'Belum Ada Antrean',
        'btn'   => $is_kontrib ? 'Titipkan Alat' : 'Meja Teknisi',
        'href'  => BASE_URL . ($is_kontrib ? '/mitra/aset_mitra.php' : '/mitra/antrean_teknisi.php'),
    ];

    if ($is_kontrib) {
        $shortcuts[] = ['label' => 'Alat Titipan', 'icon' => 'box', 'href' => BASE_URL . '/mitra/aset_mitra.php', 'count' => $m_total_alat . ' unit', 'urgent' => false];
        $shortcuts[] = ['label' => 'Log Inspeksi', 'icon' => 'clipboard', 'href' => BASE_URL . '/admin/riwayat_inspeksi.php', 'count' => $m_pinjam_selesai . ' log', 'urgent' => false];
    }
    if ($is_teknisi) {
        $shortcuts[] = ['label' => 'Meja Teknisi', 'icon' => 'wrench', 'href' => BASE_URL . '/mitra/antrean_teknisi.php', 'count' => $is_verified ? ($antrean_diag . ' baru') : 'Pending', 'urgent' => !$is_verified || $antrean_diag > 0];
    }
    $shortcuts[] = ['label' => 'Profil Mitra', 'icon' => 'user', 'href' => BASE_URL . '/auth/profil.php', 'count' => 'Buka', 'urgent' => false];
} else {
    $skor = (int)($user['skor_kredibilitas'] ?? 100);
    $pinjam_w = pk_stmt_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(status_peminjaman = 'Dipesan'), 0) AS dipesan,
        COALESCE(SUM(status_peminjaman = 'Dipinjam'), 0) AS dipinjam,
        COALESCE(SUM(status_peminjaman = 'Selesai'), 0) AS selesai,
        COALESCE(SUM(status_peminjaman = 'Dibatalkan'), 0) AS batal
        FROM peminjaman WHERE id_warga = ?", 'i', [$id_user]);
    $tiket_w = pk_stmt_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(status_tiket NOT IN ('Selesai', 'Dibatalkan')), 0) AS aktif,
        COALESCE(SUM(status_tiket = 'Menunggu Persetujuan'), 0) AS setuju,
        COALESCE(SUM(status_tiket = 'Siap Diambil'), 0) AS siap,
        COALESCE(SUM(status_tiket = 'Selesai'), 0) AS selesai
        FROM tiket_perbaikan WHERE id_pelapor = ?", 'i', [$id_user]);
    $akses = pk_stmt_one($conn, "SELECT COUNT(*) AS tersedia, COALESCE(SUM(CASE WHEN min_skor <= ? THEN 1 ELSE 0 END), 0) AS eligible FROM aset_alat WHERE status_alat = 'Tersedia'", 'i', [$skor]);
    $hemat_w_pinjam = (float)(pk_stmt_one($conn, "SELECT COALESCE(SUM(GREATEST(a.harga_beli_baru - a.biaya_perawatan, 0)), 0) AS total FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE p.id_warga = ? AND p.status_peminjaman = 'Selesai'", 'i', [$id_user])['total'] ?? 0);
    $hemat_w_servis = (float)(pk_stmt_one($conn, "SELECT COALESCE(SUM(GREATEST(k.estimasi_harga_baru - t.estimasi_biaya, 0)), 0) AS total FROM tiket_perbaikan t JOIN kategori_alat k ON t.id_kategori = k.id_kategori WHERE t.id_pelapor = ? AND t.status_tiket = 'Selesai'", 'i', [$id_user])['total'] ?? 0);

    $w_dipesan = (int)($pinjam_w['dipesan'] ?? 0);
    $w_dipinjam = (int)($pinjam_w['dipinjam'] ?? 0);
    $w_pinjam_aktif = $w_dipesan + $w_dipinjam;
    $w_pinjam_selesai = (int)($pinjam_w['selesai'] ?? 0);
    $w_pinjam_batal = (int)($pinjam_w['batal'] ?? 0);

    $w_tk_aktif = (int)($tiket_w['aktif'] ?? 0);
    $w_tk_setuju = (int)($tiket_w['setuju'] ?? 0);
    $w_tk_siap = (int)($tiket_w['siap'] ?? 0);
    $w_tk_selesai = (int)($tiket_w['selesai'] ?? 0);

    $tersedia_all = (int)($akses['tersedia'] ?? 0);
    $eligible_all = (int)($akses['eligible'] ?? 0);
    $total_hemat = $hemat_w_pinjam + $hemat_w_servis;

    if ($w_tk_setuju > 0) {
        $status_pill = '<span class="pk-badge pk-badge--danger">' . $w_tk_setuju . ' Setujui Biaya</span>';
    } elseif ($skor < 70) {
        $status_pill = '<span class="pk-badge pk-badge--danger">Skor Terbatas</span>';
    }

    $header_actions = [
        ['label' => 'Pinjam Perkakas', 'href' => BASE_URL . '/katalog/index.php', 'icon' => 'grid', 'primary' => true],
        ['label' => 'Ajukan Perbaikan', 'href' => BASE_URL . '/perbaikan/buat_tiket.php', 'icon' => 'wrench', 'primary' => false],
    ];

    $kpis = [
        [
            'label' => 'Pinjaman Aktif',
            'value' => $w_pinjam_aktif . ' Alat',
            'foot'  => "{$w_dipesan} pesan • {$w_dipinjam} pakai",
            'icon'  => 'calendar',
            'href'  => BASE_URL . '/reservasi/riwayat_pinjam.php',
            'tone'  => $w_pinjam_aktif > 0 ? 'info' : 'default',
        ],
        [
            'label' => 'Tiket Servis',
            'value' => $w_tk_aktif . ' Tiket',
            'foot'  => $w_tk_setuju > 0 ? "{$w_tk_setuju} tunggu setuju" : "{$w_tk_siap} siap ambil",
            'icon'  => 'wrench',
            'href'  => BASE_URL . '/perbaikan/tiket_saya.php',
            'tone'  => $w_tk_setuju > 0 ? 'danger' : ($w_tk_aktif > 0 ? 'info' : 'default'),
        ],
        [
            'label' => 'Skor Kepercayaan',
            'value' => $skor . ' Poin',
            'foot'  => "Akses {$eligible_all}/{$tersedia_all} alat",
            'icon'  => 'heart',
            'href'  => BASE_URL . '/auth/profil.php',
            'tone'  => $skor < 70 ? 'danger' : 'ok',
        ],
        [
            'label' => 'Penghematan',
            'value' => rupiah($total_hemat),
            'foot'  => "{$w_pinjam_selesai} pinjam • {$w_tk_selesai} servis",
            'icon'  => 'leaf',
            'href'  => BASE_URL . '/katalog/index.php',
            'tone'  => $total_hemat > 0 ? 'ok' : 'default',
        ],
    ];

    $rows_a = pk_stmt_all($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM peminjaman WHERE id_warga = ? AND created_at >= ? GROUP BY ym ORDER BY ym", 'is', [$id_user, $since_month]);
    $rows_b = pk_stmt_all($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM tiket_perbaikan WHERE id_pelapor = ? AND created_at >= ? GROUP BY ym ORDER BY ym", 'is', [$id_user, $since_month]);
    $trend_data = pk_build_monthly_trend($rows_a, $rows_b);

    $total_akt = $w_pinjam_aktif + $w_pinjam_selesai + $w_pinjam_batal + $w_tk_aktif + $w_tk_selesai;
    $dist_title = 'Ringkasan Aktivitas';
    $dist_total = $total_akt;
    $dist_items = [
        ['label' => 'Tuntas', 'count' => $w_pinjam_selesai + $w_tk_selesai, 'seg' => 'ok'],
        ['label' => 'Berjalan', 'count' => $w_pinjam_aktif + max(0, $w_tk_aktif - $w_tk_setuju), 'seg' => 'info'],
        ['label' => 'Perlu Aksi', 'count' => $w_tk_setuju, 'seg' => 'warn'],
        ['label' => 'Batal', 'count' => $w_pinjam_batal, 'seg' => 'mute'],
    ];

    $pct_akses = $tersedia_all > 0 ? (int)round(($eligible_all / $tersedia_all) * 100) : 0;
    $pct_skor = min(100, max(0, (int)round(($skor / 150) * 100)));
    $meters = [
        ['label' => 'Akses Katalog', 'val' => "{$eligible_all}/{$tersedia_all} ({$pct_akses}%)", 'pct' => $pct_akses, 'tone' => $pct_akses >= 75 ? 'ok' : 'warn'],
        ['label' => 'Skor Kepercayaan', 'val' => "{$skor} / 150", 'pct' => $pct_skor, 'tone' => $skor < 70 ? 'danger' : 'info'],
    ];

    $queue_link_href = BASE_URL . '/reservasi/riwayat_pinjam.php';
    $queue_link_label = 'Riwayat Pinjam';

    $w_loans = pk_stmt_all($conn, "SELECT p.kode_booking, p.tgl_mulai, p.tgl_selesai, p.status_peminjaman, p.created_at, a.nama_alat FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE p.id_warga = ? ORDER BY p.created_at DESC LIMIT 4", 'i', [$id_user]);
    $w_tickets = pk_stmt_all($conn, "SELECT kode_tiket, nama_barang, estimasi_biaya, status_tiket, created_at FROM tiket_perbaikan WHERE id_pelapor = ? ORDER BY created_at DESC LIMIT 4", 'i', [$id_user]);

    $merged = [];
    foreach ($w_loans as $wl) {
        $merged[] = [
            'sort_ts' => strtotime((string)$wl['created_at']),
            'code'    => $wl['kode_booking'],
            'kind'    => 'Pinjam',
            'title'   => $wl['nama_alat'],
            'sub'     => format_tanggal($wl['tgl_mulai']) . ' – ' . format_tanggal($wl['tgl_selesai']),
            'date'    => format_tanggal($wl['created_at']),
            'badge'   => badge_status_pinjam($wl['status_peminjaman']),
            'href'    => BASE_URL . '/reservasi/riwayat_pinjam.php',
            'cta'     => 'Lihat',
        ];
    }
    foreach ($w_tickets as $wt) {
        $merged[] = [
            'sort_ts' => strtotime((string)$wt['created_at']),
            'code'    => $wt['kode_tiket'],
            'kind'    => 'Servis',
            'title'   => $wt['nama_barang'],
            'sub'     => (float)$wt['estimasi_biaya'] > 0 ? rupiah((float)$wt['estimasi_biaya']) : 'Proses diagnosis',
            'date'    => format_tanggal($wt['created_at']),
            'badge'   => badge_status_tiket($wt['status_tiket']),
            'href'    => BASE_URL . '/perbaikan/tiket_saya.php',
            'cta'     => $wt['status_tiket'] === 'Menunggu Persetujuan' ? 'Tinjau' : 'Detail',
        ];
    }
    usort($merged, static function (array $x, array $y): int {
        return $y['sort_ts'] <=> $x['sort_ts'];
    });
    $queue_rows = array_slice($merged, 0, 6);
    $empty_queue = ['icon' => 'grid', 'title' => 'Belum Ada Aktivitas', 'btn' => 'Jelajahi Katalog', 'href' => BASE_URL . '/katalog/index.php'];

    $need_contact = is_kontak_kosong($user['no_hp'] ?? '') || is_kontak_kosong($user['alamat'] ?? '');
    $shortcuts = [
        ['label' => 'Katalog Alat', 'icon' => 'grid', 'href' => BASE_URL . '/katalog/index.php', 'count' => $eligible_all . ' siap', 'urgent' => false],
        ['label' => 'Tiket Servis', 'icon' => 'wrench', 'href' => BASE_URL . '/perbaikan/tiket_saya.php', 'count' => $w_tk_setuju > 0 ? ($w_tk_setuju . ' setujui') : ($w_tk_aktif . ' aktif'), 'urgent' => $w_tk_setuju > 0],
        ['label' => 'Pinjaman Saya', 'icon' => 'calendar', 'href' => BASE_URL . '/reservasi/riwayat_pinjam.php', 'count' => $w_pinjam_aktif . ' aktif', 'urgent' => false],
        ['label' => 'Profil & Skor', 'icon' => 'user', 'href' => BASE_URL . '/auth/profil.php', 'count' => $need_contact ? 'Lengkapi' : ($skor . ' pt'), 'urgent' => $need_contact],
    ];
}

$page_title = 'Dashboard';
require_once ROOT_PATH . '/includes/header.php';
?>

<section class="pk-bento-hero" aria-labelledby="dash-greeting">
    <div class="pk-bento-hero-main">
        <div class="pk-bento-kicker">
            <?= render_role_badge($user) ?>
            <span>&bull;</span>
            <span><?= e(format_tanggal(date('Y-m-d'))) ?></span>
            <?php if ($status_pill !== ''): ?>
                <?= $status_pill ?>
            <?php endif; ?>
        </div>
        <h1 id="dash-greeting">Halo, <?= e($display_name) ?></h1>
    </div>

    <div class="pk-bento-actions">
        <?php foreach ($header_actions as $act): ?>
            <a href="<?= e($act['href']) ?>" class="btn <?= !empty($act['primary']) ? 'btn-primary' : 'btn-outline' ?>">
                <?= svg_icon($act['icon'], 16) ?>
                <span><?= e($act['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="pk-bento-kpis" aria-label="Ringkasan Metrik">
    <?php foreach ($kpis as $kpi): ?>
        <?php
        $tone_map = [
            'ok'     => ' pk-kpi--ok',
            'info'   => ' pk-kpi--info',
            'warn'   => ' pk-kpi--warn',
            'danger' => ' pk-kpi--danger',
        ];
        $tone_cls = $tone_map[$kpi['tone']] ?? '';
        ?>
        <a href="<?= e($kpi['href']) ?>" class="pk-kpi<?= $tone_cls ?>">
            <div class="pk-kpi-top">
                <span class="pk-kpi-label"><?= e($kpi['label']) ?></span>
                <span class="pk-kpi-icon" aria-hidden="true"><?= svg_icon($kpi['icon'], 15) ?></span>
            </div>
            <div class="pk-kpi-value"><?= e($kpi['value']) ?></div>
            <div class="pk-kpi-foot"><?= e($kpi['foot']) ?></div>
        </a>
    <?php endforeach; ?>
</section>

<section class="pk-bento-grid" aria-label="Analitik dan Antrean">
    <article class="pk-bento-card pk-bento-span-7" aria-labelledby="chart-trend-heading">
        <div class="pk-bento-head">
            <h2 id="chart-trend-heading" class="pk-bento-title">
                <?= svg_icon('bar-chart', 17) ?>
                <span>Tren 6 Bulan</span>
            </h2>
            <div class="pk-chart-legend" aria-hidden="true">
                <span class="pk-legend-item">
                    <span class="pk-legend-swatch pk-legend-swatch--primary"></span>
                    <span><?= e($trend_label_a) ?> (<?= (int)$trend_data['sum_a'] ?>)</span>
                </span>
                <span class="pk-legend-item">
                    <span class="pk-legend-swatch pk-legend-swatch--secondary"></span>
                    <span><?= e($trend_label_b) ?> (<?= (int)$trend_data['sum_b'] ?>)</span>
                </span>
            </div>
        </div>
        <div class="pk-svg-chart-wrap">
            <?= pk_render_svg_trend($trend_data, $trend_label_a, $trend_label_b) ?>
        </div>
    </article>

    <article class="pk-bento-card pk-bento-span-5" aria-labelledby="dist-heading">
        <div class="pk-bento-head">
            <h2 id="dist-heading" class="pk-bento-title">
                <?= svg_icon('box', 17) ?>
                <span><?= e($dist_title) ?></span>
            </h2>
        </div>

        <div class="pk-dist-bar" role="presentation">
            <?php if ($dist_total > 0): ?>
                <?php foreach ($dist_items as $di): ?>
                    <?php if ((int)$di['count'] > 0): ?>
                        <?php $seg_w = max(4, round(((int)$di['count'] / $dist_total) * 100, 1)); ?>
                        <span class="pk-dist-seg pk-dist-seg--<?= e($di['seg']) ?>" style="width: <?= $seg_w ?>%;" title="<?= e($di['label']) ?>: <?= (int)$di['count'] ?>"></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="pk-dist-list">
            <?php foreach ($dist_items as $di): ?>
                <?php $pct_item = $dist_total > 0 ? (int)round(((int)$di['count'] / $dist_total) * 100) : 0; ?>
                <div class="pk-dist-item">
                    <span class="pk-dist-name">
                        <span class="pk-dist-dot pk-dist-seg--<?= e($di['seg']) ?>" aria-hidden="true"></span>
                        <span><?= e($di['label']) ?></span>
                    </span>
                    <span class="pk-dist-count"><?= (int)$di['count'] ?> <span class="text-muted">(<?= $pct_item ?>%)</span></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($meters)): ?>
            <div class="pk-meters">
                <?php foreach ($meters as $mt): ?>
                    <div>
                        <div class="pk-meter-head">
                            <span class="pk-meter-label"><?= e($mt['label']) ?></span>
                            <span class="pk-meter-val"><?= e($mt['val']) ?></span>
                        </div>
                        <div class="pk-meter-track" role="progressbar" aria-valuenow="<?= (int)$mt['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($mt['label']) ?>">
                            <div class="pk-meter-fill pk-meter-fill--<?= e($mt['tone']) ?>" style="width: <?= max(0, min(100, (int)$mt['pct'])) ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="pk-bento-card pk-bento-card--table pk-bento-span-8" aria-labelledby="queue-heading">
        <div class="pk-bento-head">
            <h2 id="queue-heading" class="pk-bento-title">
                <?= svg_icon('clipboard', 17) ?>
                <span>Aktivitas Terbaru</span>
            </h2>
            <a href="<?= e($queue_link_href) ?>" class="pk-bento-link"><?= e($queue_link_label) ?></a>
        </div>

        <?php if (empty($queue_rows)): ?>
            <div class="pk-empty-state">
                <div class="pk-empty-icon"><?= svg_icon($empty_queue['icon'], 22) ?></div>
                <h3><?= e($empty_queue['title']) ?></h3>
                <div class="mt-2">
                    <a href="<?= e($empty_queue['href']) ?>" class="btn btn-primary"><?= e($empty_queue['btn']) ?></a>
                </div>
            </div>
        <?php else: ?>
            <div class="pk-table-scroll">
                <table class="pk-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Item</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queue_rows as $qr): ?>
                            <tr>
                                <td class="pk-nowrap">
                                    <span class="pk-code-tag"><?= e($qr['code']) ?></span>
                                    <div class="pk-td-sub mt-1"><?= e($qr['kind']) ?></div>
                                </td>
                                <td>
                                    <strong><?= e($qr['title']) ?></strong>
                                    <div class="pk-td-sub"><?= e($qr['sub']) ?></div>
                                </td>
                                <td class="pk-nowrap"><?= e($qr['date']) ?></td>
                                <td class="pk-nowrap"><?= $qr['badge'] ?></td>
                                <td class="pk-nowrap">
                                    <a href="<?= e($qr['href']) ?>" class="btn btn-outline"><?= e($qr['cta']) ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="pk-bento-card pk-bento-span-4" aria-labelledby="focus-heading">
        <div class="pk-bento-head">
            <h2 id="focus-heading" class="pk-bento-title">
                <?= svg_icon('shield', 17) ?>
                <span>Pintasan</span>
            </h2>
        </div>

        <div class="pk-shortcut-list">
            <?php foreach ($shortcuts as $sc): ?>
                <a href="<?= e($sc['href']) ?>" class="pk-shortcut-row">
                    <span class="pk-shortcut-left">
                        <span class="pk-shortcut-icon" aria-hidden="true"><?= svg_icon($sc['icon'], 16) ?></span>
                        <span class="pk-shortcut-label"><?= e($sc['label']) ?></span>
                    </span>
                    <span class="pk-shortcut-count<?= !empty($sc['urgent']) ? ' pk-shortcut-count--urgent' : '' ?>"><?= e($sc['count']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
