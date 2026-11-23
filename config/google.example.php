<?php
/**
 * Salin file ini menjadi config/google.local.php lalu isi Client ID & Client Secret
 * dari Google Cloud Console (APIs & Services -> Credentials -> OAuth 2.0 Client IDs).
 *
 * Authorized JavaScript origins:
 *   http://localhost
 *
 * Authorized redirect URIs:
 *   http://localhost/pinjamkarya/modul1/google_callback.php
 */
return [
    'client_id'     => 'ISI_CLIENT_ID_GOOGLE_ANDA.apps.googleusercontent.com',
    'client_secret' => 'ISI_CLIENT_SECRET_GOOGLE_ANDA',
    // Opsional: kosongkan untuk otomatis memakai BASE_URL . '/modul1/google_callback.php'
    'redirect_uri'  => '',
];
