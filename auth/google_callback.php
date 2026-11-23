<?php
require_once dirname(__DIR__) . '/config/app.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$from = (($_SESSION['google_oauth_from'] ?? '') === 'register') ? '/auth/register.php' : '/auth/login.php';
unset($_SESSION['google_oauth_from']);

if (!google_oauth_enabled()) {
    set_flash('warning', 'Konfigurasi Google OAuth belum lengkap.');
    header('Location: ' . BASE_URL . $from);
    exit;
}

if (isset($_GET['error'])) {
    unset($_SESSION['google_oauth_state']);
    set_flash('warning', 'Masuk dengan Google dibatalkan.');
    header('Location: ' . BASE_URL . $from);
    exit;
}

$expected_state = (string)($_SESSION['google_oauth_state'] ?? '');
$received_state = (string)($_GET['state'] ?? '');
unset($_SESSION['google_oauth_state']);

if ($expected_state === '' || $received_state === '' || !hash_equals($expected_state, $received_state)) {
    set_flash('danger', 'Sesi otorisasi Google tidak valid. Silakan coba lagi.');
    header('Location: ' . BASE_URL . $from);
    exit;
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    set_flash('danger', 'Kode otorisasi Google tidak ditemukan.');
    header('Location: ' . BASE_URL . $from);
    exit;
}

$token_data = google_exchange_code($code);
if (!$token_data) {
    set_flash('danger', 'Gagal menukar token otorisasi dengan Google. Periksa Client Secret dan Redirect URI.');
    header('Location: ' . BASE_URL . $from);
    exit;
}

$google_user = google_fetch_userinfo((string)$token_data['access_token']);
if (!$google_user || empty($google_user['email_verified'])) {
    set_flash('danger', 'Akun Google tidak memiliki email terverifikasi.');
    header('Location: ' . BASE_URL . $from);
    exit;
}

$email = strtolower(trim((string)$google_user['email']));
$nama_google = trim((string)($google_user['name'] ?? ''));
if ($nama_google === '') {
    $nama_google = strstr($email, '@', true) ?: 'Warga PinjamKarya';
}
$nama_google = mb_substr($nama_google, 0, 100);

$stmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE email = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($user) {
    unset($user['password']);
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    set_flash('success', 'Selamat datang, ' . $user['nama'] . '.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$random_hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
$no_hp_default = '-';
$alamat_default = '-';
$role_default = 'warga';
$zero = 0;
$skor_awal = 100;

$ins = mysqli_prepare(
    $conn,
    'INSERT INTO users (nama, email, password, no_hp, alamat, role, is_kontributor, is_teknisi, is_verified_teknisi, skor_kredibilitas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param(
    $ins,
    'ssssssiiii',
    $nama_google,
    $email,
    $random_hash,
    $no_hp_default,
    $alamat_default,
    $role_default,
    $zero,
    $zero,
    $zero,
    $skor_awal
);
mysqli_stmt_execute($ins);
$new_id = (int)mysqli_insert_id($conn);
mysqli_stmt_close($ins);

$stmt_new = mysqli_prepare($conn, 'SELECT id_user, nama, email, no_hp, alamat, role, is_kontributor, is_teknisi, is_verified_teknisi, skor_kredibilitas, created_at FROM users WHERE id_user = ? LIMIT 1');
mysqli_stmt_bind_param($stmt_new, 'i', $new_id);
mysqli_stmt_execute($stmt_new);
$new_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_new));
mysqli_stmt_close($stmt_new);

session_regenerate_id(true);
$_SESSION['user'] = $new_user;
set_flash('success', 'Akun Google berhasil terdaftar (Modal awal: 100 Poin).');
header('Location: ' . BASE_URL . '/dashboard.php');
exit;
