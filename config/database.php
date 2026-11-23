<?php
$db_cfg = pk_config('database');
$app_cfg = pk_config('app');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect(
        (string)($db_cfg['host'] ?? 'localhost'),
        (string)($db_cfg['user'] ?? 'root'),
        (string)($db_cfg['pass'] ?? ''),
        (string)($db_cfg['name'] ?? 'pinjamkarya'),
        (int)($db_cfg['port'] ?? 3306)
    );
    mysqli_set_charset($conn, (string)($db_cfg['charset'] ?: 'utf8mb4'));
} catch (mysqli_sql_exception $ex) {
    http_response_code(500);
    if (!empty($app_cfg['debug'])) {
        die('Koneksi basis data gagal: ' . $ex->getMessage());
    }
    die('Koneksi basis data gagal: Periksa pengaturan di config/config.local.php dan pastikan basis data sudah diimpor.');
}
