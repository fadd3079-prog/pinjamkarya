<?php

function google_oauth_config(): array {
    $cfg = pk_config('google');

    $client_id = trim((string)($cfg['client_id'] ?? ''));
    $client_secret = trim((string)($cfg['client_secret'] ?? ''));
    $redirect_uri = trim((string)($cfg['redirect_uri'] ?? ''));

    if ($redirect_uri === '') {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'http://localhost/pinjamkarya';
        $redirect_uri = $base . '/auth/google_callback.php';
    }

    return [
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $redirect_uri,
    ];
}

function google_oauth_enabled(): bool {
    $cfg = google_oauth_config();
    if ($cfg['client_id'] === '' || $cfg['client_secret'] === '') {
        return false;
    }
    if (str_starts_with($cfg['client_id'], 'ISI_') || str_starts_with($cfg['client_secret'], 'ISI_')) {
        return false;
    }
    return function_exists('curl_init');
}

function google_oauth_auth_url(string $state): string {
    $cfg = google_oauth_config();
    $params = [
        'client_id'     => $cfg['client_id'],
        'redirect_uri'  => $cfg['redirect_uri'],
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function google_http_request(string $url, ?array $post_fields = null, array $headers = []): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'Ekstensi PHP cURL tidak aktif.'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    if ($post_fields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields, '', '&', PHP_QUERY_RFC3986));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => $status, 'body' => null, 'error' => $err !== '' ? $err : 'Gagal menghubungi server Google.'];
    }

    $decoded = json_decode($raw, true);
    return [
        'ok'     => ($status >= 200 && $status < 300 && is_array($decoded)),
        'status' => $status,
        'body'   => is_array($decoded) ? $decoded : null,
        'error'  => is_array($decoded) && isset($decoded['error_description']) ? (string)$decoded['error_description'] : null,
    ];
}

function google_exchange_code(string $code): ?array {
    $cfg = google_oauth_config();
    $res = google_http_request('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'redirect_uri'  => $cfg['redirect_uri'],
        'grant_type'    => 'authorization_code',
    ]);
    if (!$res['ok'] || empty($res['body']['access_token'])) {
        return null;
    }
    return $res['body'];
}

function google_fetch_userinfo(string $access_token): ?array {
    $res = google_http_request(
        'https://openidconnect.googleapis.com/v1/userinfo',
        null,
        ['Authorization: Bearer ' . $access_token]
    );
    if (!$res['ok'] || empty($res['body']['email'])) {
        return null;
    }
    return $res['body'];
}
