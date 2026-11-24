<?php
/**
 * Proses keluar dari sesi akun.
 *
 * Tujuan : Menghapus seluruh data sesi, cookie sesi, lalu menampilkan pesan keluar.
 * Akses  : Pengguna yang sedang login.
 * Alur   : Kosongkan $_SESSION -> hapus cookie sesi -> destroy -> mulai sesi baru
 *          untuk flash -> redirect ke halaman login.
 */

require_once dirname(__DIR__) . '/config/app.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Sesi baru diperlukan agar pesan flash dapat disimpan setelah sesi lama dihancurkan.
session_start();
set_flash('info', 'Anda telah keluar dari sesi akun PinjamKarya.');
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
