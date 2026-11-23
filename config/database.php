<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'pinjamkarya';

try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $ex) {
    http_response_code(500);
    die('Koneksi basis data gagal: Pastikan layanan MySQL aktif dan basis data "pinjamkarya" sudah diimpor dari database/pinjamkarya.sql.');
}
