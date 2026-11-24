<?php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

function pk_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function pk_detect_base_url(): string
{
    $proto = pk_is_https() ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = trim(explode(',', $host)[0]);
    if ($host === '') {
        $host = 'localhost';
    }

    $doc_root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $root_dir = realpath(ROOT_PATH);
    $subpath = '';

    if ($doc_root && $root_dir) {
        $doc_norm = rtrim(str_replace('\\', '/', $doc_root), '/');
        $root_norm = rtrim(str_replace('\\', '/', $root_dir), '/');
        if ($root_norm === $doc_norm) {
            $subpath = '';
        } elseif (str_starts_with($root_norm, $doc_norm . '/')) {
            $subpath = '/' . trim(substr($root_norm, strlen($doc_norm)), '/');
        }
    }

    return $proto . '://' . $host . $subpath;
}

function pk_config(?string $section = null)
{
    static $config = null;

    if ($config === null) {
        $defaults = [
            'app' => [
                'base_url' => '',
                'timezone' => 'Asia/Jakarta',
                'debug'    => false,
            ],
            'database' => [
                'host'    => 'localhost',
                'port'    => 3306,
                'user'    => 'root',
                'pass'    => '',
                'name'    => 'pinjamkarya',
                'charset' => 'utf8mb4',
            ],
        ];

        $local_file = __DIR__ . '/config.local.php';
        $local = is_file($local_file) ? (array)(include $local_file) : [];
        foreach ($defaults as $key => $section_defaults) {
            if (isset($local[$key]) && is_array($local[$key])) {
                $defaults[$key] = array_merge($section_defaults, $local[$key]);
            }
        }

        // Override opsional dari environment variables server/hosting
        $env_map = [
            'app.base_url'         => getenv('APP_BASE_URL') ?: getenv('BASE_URL'),
            'app.timezone'         => getenv('APP_TIMEZONE'),
            'app.debug'            => getenv('APP_DEBUG'),
            'database.host'        => getenv('DB_HOST'),
            'database.port'        => getenv('DB_PORT'),
            'database.user'        => getenv('DB_USER'),
            'database.pass'        => getenv('DB_PASS'),
            'database.name'        => getenv('DB_NAME'),
        ];

        foreach ($env_map as $path => $val) {
            if ($val === false || $val === '') {
                continue;
            }
            [$sec, $opt] = explode('.', $path, 2);
            if ($opt === 'debug') {
                $defaults[$sec][$opt] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            } elseif ($opt === 'port') {
                $defaults[$sec][$opt] = (int)$val;
            } else {
                $defaults[$sec][$opt] = trim((string)$val);
            }
        }

        $config = $defaults;
    }

    if ($section !== null) {
        return $config[$section] ?? [];
    }
    return $config;
}

$app_cfg = pk_config('app');
date_default_timezone_set((string)($app_cfg['timezone'] ?: 'Asia/Jakarta'));

if (empty($app_cfg['debug'])) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => pk_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    $configured_base = trim((string)($app_cfg['base_url'] ?? ''));
    define('BASE_URL', $configured_base !== '' ? rtrim($configured_base, '/') : pk_detect_base_url());
}

if (!defined('UPLOAD_ALAT_PATH')) {
    define('UPLOAD_ALAT_PATH', ROOT_PATH . '/assets/uploads/alat/');
}

if (!defined('UPLOAD_TIKET_PATH')) {
    define('UPLOAD_TIKET_PATH', ROOT_PATH . '/assets/uploads/tiket/');
}

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';