<?php
/**
 * Pengendali utama halaman Dashboard multi-peran PinjamKarya.
 *
 * Menangani autentikasi sesi, sinkronisasi profil pengguna, pembersihan ghost booking,
 * inisialisasi data bersama, serta pemuatan parsial tampilan sesuai role aktif.
 */
require_once __DIR__ . '/config/app.php';
require_login();
refresh_user_session($conn);
bersihkan_ghost_booking($conn);

$user = current_user() ?? [];
$id_user = (int)($user['id_user'] ?? 0);
$role = (string)($user['role'] ?? 'warga');
$display_name = format_nama_pengguna((string)($user['nama'] ?? ''));
$since_month = date('Y-m-01 00:00:00', strtotime('-5 months', strtotime(date('Y-m-01'))));

$page_title = 'Dashboard';
require_once ROOT_PATH . '/includes/header.php';

$dashboard_partials = [
    'admin' => ROOT_PATH . '/includes/dashboard_admin.php',
    'mitra' => ROOT_PATH . '/includes/dashboard_mitra.php',
    'warga' => ROOT_PATH . '/includes/dashboard_user.php',
];

$partial_file = $dashboard_partials[$role] ?? $dashboard_partials['warga'];
require $partial_file;

require_once ROOT_PATH . '/includes/footer.php';