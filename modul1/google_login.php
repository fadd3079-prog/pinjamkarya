<?php
require_once dirname(__DIR__) . '/config/app.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/modul4/katalog.php');
    exit;
}

if (!google_oauth_enabled()) {
    set_flash('warning', 'Login Google belum dikonfigurasi. Isi client_id dan client_secret di config/google.local.php.');
    $back = (($_GET['from'] ?? '') === 'register') ? '/modul1/register.php' : '/modul1/login.php';
    header('Location: ' . BASE_URL . $back);
    exit;
}

$_SESSION['google_oauth_state'] = bin2hex(random_bytes(24));
$_SESSION['google_oauth_from'] = (($_GET['from'] ?? '') === 'register') ? 'register' : 'login';

header('Location: ' . google_oauth_auth_url($_SESSION['google_oauth_state']));
exit;
