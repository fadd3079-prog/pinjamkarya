<?php
/**
 * Konfigurasi Google OAuth kini terpusat di config/config.example.php -> config/config.local.php
 * (bagian 'google'). File ini tetap didukung jika Anda masih memakai config/google.local.php.
 *
 * Authorized redirect URIs di Google Cloud Console:
 *   https://domain-anda.com/auth/google_callback.php
 *   http://localhost/pinjamkarya/auth/google_callback.php
 */
return [
    'client_id'     => 'ISI_CLIENT_ID_GOOGLE_ANDA.apps.googleusercontent.com',
    'client_secret' => 'ISI_CLIENT_SECRET_GOOGLE_ANDA',
    // Opsional: kosongkan untuk otomatis memakai BASE_URL . '/auth/google_callback.php'
    'redirect_uri'  => '',
];
