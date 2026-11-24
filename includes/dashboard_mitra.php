<?php
/**
 * Komponen parsial Dashboard untuk peran Mitra (role = 'mitra').
 *
 * Mendukung sub-peran Kontributor Alat, Teknisi Perbaikan, maupun keduanya.
 * Memerlukan variabel bersama dari dashboard.php:
 * $conn, $user, $id_user, $display_name, $since_month.
 */
if (!defined('ROOT_PATH')) {
    exit;
}

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

$status_pill = '';
if ($is_teknisi && !$is_verified) {
    $status_pill = '<span class="pk-badge pk-badge--warning">Teknisi Pending</span>';
} elseif ($is_teknisi && $antrean_diag > 0) {
    $status_pill = '<span class="pk-badge pk-badge--warning">' . $antrean_diag . ' Diagnosis Baru</span>';
}

$header_actions = [];
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

$rows_a = $is_kontrib
    ? pk_stmt_all($conn, "SELECT DATE_FORMAT(p.created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM peminjaman p JOIN aset_alat a ON p.id_alat = a.id_alat WHERE a.id_pemilik = ? AND p.created_at >= ? GROUP BY ym ORDER BY ym", 'is', [$id_user, $since_month])
    : [];
$rows_b = $is_teknisi
    ? pk_stmt_all($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM tiket_perbaikan WHERE id_teknisi = ? AND created_at >= ? GROUP BY ym ORDER BY ym", 'is', [$id_user, $since_month])
    : [];
$trend_data = pk_build_monthly_trend($rows_a, $rows_b);

$meters = [];
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

$shortcuts = [];
if ($is_kontrib) {
    $shortcuts[] = ['label' => 'Alat Titipan', 'icon' => 'box', 'href' => BASE_URL . '/mitra/aset_mitra.php', 'count' => $m_total_alat . ' unit', 'urgent' => false];
    $shortcuts[] = ['label' => 'Log Inspeksi', 'icon' => 'clipboard', 'href' => BASE_URL . '/admin/riwayat_inspeksi.php', 'count' => $m_pinjam_selesai . ' log', 'urgent' => false];
}
if ($is_teknisi) {
    $shortcuts[] = ['label' => 'Meja Teknisi', 'icon' => 'wrench', 'href' => BASE_URL . '/mitra/antrean_teknisi.php', 'count' => $is_verified ? ($antrean_diag . ' baru') : 'Pending', 'urgent' => !$is_verified || $antrean_diag > 0];
}
$shortcuts[] = ['label' => 'Profil Mitra', 'icon' => 'user', 'href' => BASE_URL . '/auth/profil.php', 'count' => 'Buka', 'urgent' => false];

echo pk_render_dashboard_bento($user, $display_name, [
    'status_pill'      => $status_pill,
    'header_actions'   => $header_actions,
    'kpis'             => $kpis,
    'trend_label_a'    => 'Sewa Aset',
    'trend_label_b'    => 'Servis',
    'trend_data'       => $trend_data,
    'dist_title'       => $dist_title,
    'dist_total'       => $dist_total,
    'dist_items'       => $dist_items,
    'meters'           => $meters,
    'queue_link_href'  => BASE_URL . ($is_teknisi ? '/mitra/antrean_teknisi.php' : '/mitra/aset_mitra.php'),
    'queue_link_label' => $is_teknisi ? 'Meja Teknisi' : 'Kelola Alat',
    'queue_rows'       => pk_sort_and_limit_queue($merged, 6),
    'empty_queue'      => [
        'icon'  => $is_teknisi ? 'wrench' : 'box',
        'title' => 'Belum Ada Antrean',
        'btn'   => $is_kontrib ? 'Titipkan Alat' : 'Meja Teknisi',
        'href'  => BASE_URL . ($is_kontrib ? '/mitra/aset_mitra.php' : '/mitra/antrean_teknisi.php'),
    ],
    'shortcuts'        => $shortcuts,
]);