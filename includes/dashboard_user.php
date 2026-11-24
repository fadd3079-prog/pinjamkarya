<?php
/**
 * Komponen parsial Dashboard untuk peran Warga / UMKM (role = 'warga').
 *
 * Memerlukan variabel bersama dari dashboard.php:
 * $conn, $user, $id_user, $display_name, $since_month.
 */
if (!defined('ROOT_PATH')) {
    exit;
}

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

$status_pill = '';
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

$need_contact = is_kontak_kosong($user['no_hp'] ?? '') || is_kontak_kosong($user['alamat'] ?? '');
$shortcuts = [
    ['label' => 'Katalog Alat', 'icon' => 'grid', 'href' => BASE_URL . '/katalog/index.php', 'count' => $eligible_all . ' siap', 'urgent' => false],
    ['label' => 'Tiket Servis', 'icon' => 'wrench', 'href' => BASE_URL . '/perbaikan/tiket_saya.php', 'count' => $w_tk_setuju > 0 ? ($w_tk_setuju . ' setujui') : ($w_tk_aktif . ' aktif'), 'urgent' => $w_tk_setuju > 0],
    ['label' => 'Pinjaman Saya', 'icon' => 'calendar', 'href' => BASE_URL . '/reservasi/riwayat_pinjam.php', 'count' => $w_pinjam_aktif . ' aktif', 'urgent' => false],
    ['label' => 'Profil & Skor', 'icon' => 'user', 'href' => BASE_URL . '/auth/profil.php', 'count' => $need_contact ? 'Lengkapi' : ($skor . ' pt'), 'urgent' => $need_contact],
];

echo pk_render_dashboard_bento($user, $display_name, [
    'status_pill'      => $status_pill,
    'header_actions'   => $header_actions,
    'kpis'             => $kpis,
    'trend_label_a'    => 'Peminjaman',
    'trend_label_b'    => 'Perbaikan',
    'trend_data'       => $trend_data,
    'dist_title'       => 'Ringkasan Aktivitas',
    'dist_total'       => $total_akt,
    'dist_items'       => $dist_items,
    'meters'           => $meters,
    'queue_link_href'  => BASE_URL . '/reservasi/riwayat_pinjam.php',
    'queue_link_label' => 'Riwayat Pinjam',
    'queue_rows'       => pk_sort_and_limit_queue($merged, 6),
    'empty_queue'      => ['icon' => 'grid', 'title' => 'Belum Ada Aktivitas', 'btn' => 'Jelajahi Katalog', 'href' => BASE_URL . '/katalog/index.php'],
    'shortcuts'        => $shortcuts,
]);