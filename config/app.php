<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/pinjamkarya');
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!defined('UPLOAD_ALAT_PATH')) {
    define('UPLOAD_ALAT_PATH', ROOT_PATH . '/assets/uploads/alat/');
}

if (!defined('UPLOAD_TIKET_PATH')) {
    define('UPLOAD_TIKET_PATH', ROOT_PATH . '/assets/uploads/tiket/');
}

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
