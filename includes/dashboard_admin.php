<?php
/**
 * Komponen parsial Dashboard untuk peran Admin Hub (role = 'admin').
 *
 * Memerlukan variabel bersama dari dashboard.php:
 * $conn, $user, $display_name, $since_month.
 */
if (!defined('ROOT_PATH')) {
    exit;
}

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

$status_pill = '';
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

$shortcuts = [
    ['label' => 'Verifikasi Alat', 'icon' => 'box', 'href' => BASE_URL . '/admin/kelola_aset.php', 'count' => $alat_verif . ' antrean', 'urgent' => $alat_verif > 0],
    ['label' => 'Verifikasi Teknisi', 'icon' => 'users', 'href' => BASE_URL . '/admin/kelola_user.php', 'count' => $tek_pending . ' antrean', 'urgent' => $tek_pending > 0],
    ['label' => 'Log Inspeksi', 'icon' => 'clipboard', 'href' => BASE_URL . '/admin/riwayat_inspeksi.php', 'count' => $tx_selesai . ' selesai', 'urgent' => false],
    ['label' => 'Master Kategori', 'icon' => 'tag', 'href' => BASE_URL . '/admin/kelola_kategori.php', 'count' => 'Kelola', 'urgent' => false],
];

echo pk_render_dashboard_bento($user, $display_name, [
    'status_pill'      => $status_pill,
    'header_actions'   => $header_actions,
    'kpis'             => $kpis,
    'trend_label_a'    => 'Peminjaman',
    'trend_label_b'    => 'Perbaikan',
    'trend_data'       => $trend_data,
    'dist_title'       => 'Status Inventaris',
    'dist_total'       => $total_alat,
    'dist_items'       => $dist_items,
    'meters'           => $meters,
    'queue_link_href'  => BASE_URL . '/admin/kelola_transaksi.php',
    'queue_link_label' => 'Semua Transaksi',
    'queue_rows'       => pk_sort_and_limit_queue($merged, 6),
    'empty_queue'      => ['icon' => 'clipboard', 'title' => 'Belum Ada Aktivitas', 'btn' => 'Buka Inventaris', 'href' => BASE_URL . '/admin/kelola_aset.php'],
    'shortcuts'        => $shortcuts,
]);