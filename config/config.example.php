<?php
/**
 * Templat Konfigurasi Terpusat PinjamKarya.
 *
 * Untuk konfigurasi lokal atau server hosting:
 * 1. Salin file ini menjadi config/config.local.php (sudah diabaikan .gitignore).
 * 2. Sesuaikan nilai database, base_url (opsional), dan kredensial Google OAuth.
 * 3. Alternatif server produksi: atur variabel environment (APP_BASE_URL, DB_HOST, dll).
 */
return [
    'app' => [
        // Kosongkan agar BASE_URL terdeteksi otomatis dari protokol, host, dan folder aktif.
        // Contoh override: 'https://pinjamkarya.id' atau 'http://localhost/pinjamkarya'
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

    'google' => [
        'client_id'     => '',
        'client_secret' => '',
        // Opsional: kosongkan untuk otomatis memakai BASE_URL . '/auth/google_callback.php'
        'redirect_uri'  => '',
    ],
];
