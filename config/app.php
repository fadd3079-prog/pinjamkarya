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
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    $cf_visitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
    return $cf_visitor !== '' && stripos($cf_visitor, '"https"') !== false;
}

function pk_detect_base_url(): string
{
    $proto = pk_is_https() ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = trim(explode(',', $host)[0]);
    if ($host === '') {
        $host = 'localhost';
    }

    $doc_raw = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $doc_real = $doc_raw !== '' ? @realpath($doc_raw) : false;
    $doc_use = $doc_real !== false ? $doc_real : $doc_raw;
    $root_real = @realpath(ROOT_PATH);
    $root_use = $root_real !== false ? $root_real : ROOT_PATH;
    $subpath = '';
    $resolved = false;

    if ($doc_use !== '' && $root_use !== '') {
        $doc_norm = rtrim(str_replace('\\', '/', $doc_use), '/');
        $root_norm = rtrim(str_replace('\\', '/', $root_use), '/');
        if ($root_norm === $doc_norm) {
            $subpath = '';
            $resolved = true;
        } elseif (str_starts_with($root_norm, $doc_norm . '/')) {
            $subpath = '/' . trim(substr($root_norm, strlen($doc_norm)), '/');
            $resolved = true;
        }
    }

    if (!$resolved && !empty($_SERVER['SCRIPT_NAME'])) {
        $script_dir = str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME']));
        $script_dir = '/' . trim($script_dir, '/');
        if ($script_dir === '/') {
            $subpath = '';
        } else {
            $feature_dirs = ['/admin', '/auth', '/katalog', '/mitra', '/perbaikan', '/reservasi'];
            foreach ($feature_dirs as $fd) {
                if ($script_dir === $fd) {
                    $subpath = '';
                    $resolved = true;
                    break;
                }
                if (str_ends_with($script_dir, $fd)) {
                    $subpath = rtrim(substr($script_dir, 0, -strlen($fd)), '/');
                    $resolved = true;
                    break;
                }
            }
            if (!$resolved) {
                $subpath = $script_dir;
            }
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