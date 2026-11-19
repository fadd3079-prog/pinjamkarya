<?php

function e($string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka): string {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function format_tanggal(?string $date_str): string {
    if (empty($date_str)) {
        return '-';
    }
    $ts = strtotime($date_str);
    if (!$ts) {
        return e($date_str);
    }
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $d = date('d', $ts);
    $m = $bulan[(int)date('n', $ts)] ?? date('M', $ts);
    $y = date('Y', $ts);
    return "$d $m $y";
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash(): string {
    if (!isset($_SESSION['flash'])) {
        return '';
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type_map = [
        'success' => 'pk-alert--success',
        'warning' => 'pk-alert--warning',
        'danger'  => 'pk-alert--danger',
        'error'   => 'pk-alert--danger',
        'info'    => 'pk-alert--info'
    ];
    $css_class = $type_map[$flash['type']] ?? 'pk-alert--info';
    $icon = svg_icon($flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' || $flash['type'] === 'error' ? 'alert-triangle' : 'info'), 20);

    return '<div class="pk-alert ' . $css_class . '" role="alert">'
        . '<div class="d-flex align-center gap-2">' . $icon . '<span>' . e($flash['message']) . '</span></div>'
        . '<button type="button" class="pk-alert-close" aria-label="Tutup pesan">' . svg_icon('x', 16) . '</button>'
        . '</div>';
}

function require_login(): void {
    if (!isset($_SESSION['user'])) {
        set_flash('warning', 'Silakan masuk terlebih dahulu.');
        header('Location: ' . BASE_URL . '/modul1/login.php');
        exit;
    }
}

function require_role(array $allowed_roles): void {
    require_login();
    $user_role = $_SESSION['user']['role'] ?? '';
    if (!in_array($user_role, $allowed_roles, true)) {
        set_flash('danger', 'Akses ditolak untuk peran akun Anda.');
        header('Location: ' . BASE_URL . '/modul4/katalog.php');
        exit;
    }
}

function refresh_user_session(mysqli $conn): void {
    if (!isset($_SESSION['user']['id_user'])) {
        return;
    }
    $id_user = (int)$_SESSION['user']['id_user'];
    $stmt = mysqli_prepare($conn, 'SELECT id_user, nama, email, no_hp, alamat, role, is_kontributor, is_teknisi, is_verified_teknisi, skor_kredibilitas, created_at FROM users WHERE id_user = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id_user);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $_SESSION['user'] = $row;
    }
    mysqli_stmt_close($stmt);
}

function upload_gambar(array $file, string $target_dir, string $prefix = 'img_'): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Parameter berkas unggahan tidak valid.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Gagal mengunggah berkas (kode galat: ' . (int)$file['error'] . ').'];
    }

    $max_size = 2 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran gambar maksimal 2 MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed_ext, true)) {
        return ['success' => false, 'message' => 'Format gambar wajib JPG, JPEG, PNG, atau WEBP.'];
    }

    $img_info = @getimagesize($file['tmp_name']);
    if ($img_info === false) {
        return ['success' => false, 'message' => 'Berkas yang diunggah bukan gambar yang sah.'];
    }

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $new_filename = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Gagal menyimpan berkas gambar.'];
    }

    return ['success' => true, 'filename' => $new_filename];
}

function cek_bentrok_jadwal(mysqli $conn, int $id_alat, string $tgl_mulai, string $tgl_selesai): int {
    $sql = "SELECT COUNT(*) AS bentrok FROM peminjaman
            WHERE id_alat = ?
              AND status_peminjaman IN ('Dipesan', 'Dipinjam')
              AND (tgl_mulai <= ? AND tgl_selesai >= ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iss', $id_alat, $tgl_selesai, $tgl_mulai);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return (int)($row['bentrok'] ?? 0);
}

function bersihkan_ghost_booking(mysqli $conn): int {
    $sql = "UPDATE peminjaman SET status_peminjaman = 'Dibatalkan'
            WHERE status_peminjaman = 'Dipesan' AND tgl_mulai < CURDATE()";
    mysqli_query($conn, $sql);
    return mysqli_affected_rows($conn);
}

function get_hero_banner(): ?string {
    $candidates = [
        '/assets/img/hero-image.webp',
        '/assets/img/hero-image.jpg',
        '/assets/img/hero-image.jpeg',
        '/assets/img/hero-image.png',
        '/assets/img/hero-banner.webp',
        '/assets/img/hero-banner.jpg',
        '/assets/img/hero-banner.jpeg',
        '/assets/img/hero-banner.png',
    ];
    foreach ($candidates as $relative) {
        if (file_exists(ROOT_PATH . $relative)) {
            return BASE_URL . $relative;
        }
    }
    return null;
}

function get_gambar_alat(?string $filename, string $label = 'Perkakas Komunitas'): string {
    $clean = trim((string)$filename);
    if ($clean !== '' && $clean !== 'default_alat.jpg') {
        $full_path = UPLOAD_ALAT_PATH . $clean;
        if (file_exists($full_path) && is_file($full_path)) {
            return BASE_URL . '/assets/uploads/alat/' . rawurlencode($clean);
        }
    }
    $safe_label = htmlspecialchars(mb_strimwidth($label, 0, 28, '...'), ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400">'
         . '<rect width="640" height="400" fill="#0e5a6d"/>'
         . '<circle cx="320" cy="175" r="56" fill="rgba(255,255,255,0.1)"/>'
         . '<path d="M302 152 L338 152 L344 196 L296 196 Z" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linejoin="round"/>'
         . '<path d="M310 152 L310 138 C310 133 330 133 330 138 L330 152" fill="none" stroke="#ffffff" stroke-width="3.5"/>'
         . '<text x="320" y="280" font-family="Inter, sans-serif" font-size="20" font-weight="600" fill="#ffffff" text-anchor="middle">' . $safe_label . '</text>'
         . '<text x="320" y="308" font-family="Inter, sans-serif" font-size="14" fill="#cde0e6" text-anchor="middle">PinjamKarya</text>'
         . '</svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function get_gambar_tiket(?string $filename, string $label = 'Barang Perbaikan'): string {
    $clean = trim((string)$filename);
    if ($clean !== '' && $clean !== 'default_tiket.jpg') {
        $full_path = UPLOAD_TIKET_PATH . $clean;
        if (file_exists($full_path) && is_file($full_path)) {
            return BASE_URL . '/assets/uploads/tiket/' . rawurlencode($clean);
        }
    }
    $safe_label = htmlspecialchars(mb_strimwidth($label, 0, 28, '...'), ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400">'
         . '<rect width="640" height="400" fill="#134e5e"/>'
         . '<circle cx="320" cy="175" r="56" fill="rgba(255,255,255,0.1)"/>'
         . '<path d="M302 193 L338 157 M330 150 L345 165 M295 186 L310 201" stroke="#ffffff" stroke-width="4" stroke-linecap="round"/>'
         . '<text x="320" y="280" font-family="Inter, sans-serif" font-size="20" font-weight="600" fill="#ffffff" text-anchor="middle">' . $safe_label . '</text>'
         . '<text x="320" y="308" font-family="Inter, sans-serif" font-size="14" fill="#cde0e6" text-anchor="middle">PinjamKarya</text>'
         . '</svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function generate_kode_booking(mysqli $conn): string {
    $today = date('Ymd');
    $prefix = 'PK-' . $today . '-';
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM peminjaman WHERE kode_booking LIKE CONCAT(?, '%')");
    mysqli_stmt_bind_param($stmt, 's', $prefix);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    $next = ((int)($row['total'] ?? 0)) + 1;
    do {
        $candidate = $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        $chk = mysqli_prepare($conn, 'SELECT id_peminjaman FROM peminjaman WHERE kode_booking = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 's', $candidate);
        mysqli_stmt_execute($chk);
        $chk_res = mysqli_stmt_get_result($chk);
        $exists = mysqli_fetch_assoc($chk_res);
        mysqli_stmt_close($chk);
        if ($exists) {
            $next++;
        }
    } while ($exists);

    return $candidate;
}

function generate_kode_tiket(mysqli $conn): string {
    $today = date('Ymd');
    $prefix = 'RTR-' . $today . '-';
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM tiket_perbaikan WHERE kode_tiket LIKE CONCAT(?, '%')");
    mysqli_stmt_bind_param($stmt, 's', $prefix);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    $next = ((int)($row['total'] ?? 0)) + 1;
    do {
        $candidate = $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        $chk = mysqli_prepare($conn, 'SELECT id_tiket FROM tiket_perbaikan WHERE kode_tiket = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 's', $candidate);
        mysqli_stmt_execute($chk);
        $chk_res = mysqli_stmt_get_result($chk);
        $exists = mysqli_fetch_assoc($chk_res);
        mysqli_stmt_close($chk);
        if ($exists) {
            $next++;
        }
    } while ($exists);

    return $candidate;
}

function badge_status_alat(string $status): string {
    $map = [
        'Tersedia'            => 'pk-badge--success',
        'Dipinjam'            => 'pk-badge--primary',
        'Perawatan'           => 'pk-badge--danger',
        'Menunggu Verifikasi' => 'pk-badge--warning',
    ];
    $cls = $map[$status] ?? 'pk-badge--muted';
    return '<span class="pk-badge ' . $cls . '">' . e($status) . '</span>';
}

function badge_status_pinjam(string $status): string {
    $map = [
        'Dipesan'    => 'pk-badge--warning',
        'Dipinjam'   => 'pk-badge--primary',
        'Selesai'    => 'pk-badge--success',
        'Dibatalkan' => 'pk-badge--danger',
    ];
    $cls = $map[$status] ?? 'pk-badge--muted';
    return '<span class="pk-badge ' . $cls . '">' . e($status) . '</span>';
}

function badge_status_tiket(string $status): string {
    $map = [
        'Menunggu Diagnosis'   => 'pk-badge--warning',
        'Menunggu Persetujuan' => 'pk-badge--primary',
        'Dikerjakan'           => 'pk-badge--primary',
        'Siap Diambil'         => 'pk-badge--success',
        'Selesai'              => 'pk-badge--success',
        'Dibatalkan'           => 'pk-badge--danger',
    ];
    $cls = $map[$status] ?? 'pk-badge--muted';
    return '<span class="pk-badge ' . $cls . '">' . e($status) . '</span>';
}

function svg_icon(string $name, int $size = 20): string {
    $attr = 'xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
    switch ($name) {
        case 'menu':
            return "<svg $attr><line x1=\"3\" y1=\"6\" x2=\"21\" y2=\"6\"/><line x1=\"3\" y1=\"12\" x2=\"21\" y2=\"12\"/><line x1=\"3\" y1=\"18\" x2=\"21\" y2=\"18\"/></svg>";
        case 'x':
            return "<svg $attr><line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"/><line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"/></svg>";
        case 'heart':
            return "<svg $attr><path d=\"M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z\"/></svg>";
        case 'shield':
            return "<svg $attr><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg>";
        case 'tool':
        case 'wrench':
            return "<svg $attr><path d=\"M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z\"/></svg>";
        case 'grid':
            return "<svg $attr><rect x=\"3\" y=\"3\" width=\"7\" height=\"7\"/><rect x=\"14\" y=\"3\" width=\"7\" height=\"7\"/><rect x=\"14\" y=\"14\" width=\"7\" height=\"7\"/><rect x=\"3\" y=\"14\" width=\"7\" height=\"7\"/></svg>";
        case 'calendar':
            return "<svg $attr><rect x=\"3\" y=\"4\" width=\"18\" height=\"18\" rx=\"2\" ry=\"2\"/><line x1=\"16\" y1=\"2\" x2=\"16\" y2=\"6\"/><line x1=\"8\" y1=\"2\" x2=\"8\" y2=\"6\"/><line x1=\"3\" y1=\"10\" x2=\"21\" y2=\"10\"/></svg>";
        case 'clipboard':
            return "<svg $attr><path d=\"M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2\"/><rect x=\"8\" y=\"2\" width=\"8\" height=\"4\" rx=\"1\" ry=\"1\"/></svg>";
        case 'check-circle':
            return "<svg $attr><path d=\"M22 11.08V12a10 10 0 1 1-5.93-9.14\"/><polyline points=\"22 4 12 14.01 9 11.01\"/></svg>";
        case 'alert-triangle':
            return "<svg $attr><path d=\"M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z\"/><line x1=\"12\" y1=\"9\" x2=\"12\" y2=\"13\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/></svg>";
        case 'info':
            return "<svg $attr><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"12\" y1=\"16\" x2=\"12\" y2=\"12\"/><line x1=\"12\" y1=\"8\" x2=\"12.01\" y2=\"8\"/></svg>";
        case 'user':
            return "<svg $attr><path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg>";
        case 'users':
            return "<svg $attr><path d=\"M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\"/><circle cx=\"9\" cy=\"7\" r=\"4\"/><path d=\"M23 21v-2a4 4 0 0 0-3-3.87\"/><path d=\"M16 3.13a4 4 0 0 1 0 7.75\"/></svg>";
        case 'box':
            return "<svg $attr><path d=\"M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\"/><polyline points=\"3.27 6.96 12 12.01 20.73 6.96\"/><line x1=\"12\" y1=\"22.08\" x2=\"12\" y2=\"12\"/></svg>";
        case 'bar-chart':
            return "<svg $attr><line x1=\"12\" y1=\"20\" x2=\"12\" y2=\"10\"/><line x1=\"18\" y1=\"20\" x2=\"18\" y2=\"4\"/><line x1=\"6\" y1=\"20\" x2=\"6\" y2=\"16\"/></svg>";
        case 'leaf':
            return "<svg $attr><path d=\"M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z\"/><path d=\"M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12\"/></svg>";
        case 'log-in':
            return "<svg $attr><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg>";
        case 'log-out':
            return "<svg $attr><path d=\"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4\"/><polyline points=\"16 17 21 12 16 7\"/><line x1=\"21\" y1=\"12\" x2=\"9\" y2=\"12\"/></svg>";
        case 'plus':
            return "<svg $attr><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg>";
        case 'search':
            return "<svg $attr><circle cx=\"11\" cy=\"11\" r=\"8\"/><line x1=\"21\" y1=\"21\" x2=\"16.65\" y2=\"16.65\"/></svg>";
        case 'tag':
            return "<svg $attr><path d=\"M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z\"/><line x1=\"7\" y1=\"7\" x2=\"7.01\" y2=\"7\"/></svg>";
        case 'clock':
            return "<svg $attr><circle cx=\"12\" cy=\"12\" r=\"10\"/><polyline points=\"12 6 12 12 16 14\"/></svg>";
        case 'arrow-down':
            return "<svg $attr><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><polyline points=\"19 12 12 19 5 12\"/></svg>";
        case 'home':
            return "<svg $attr><path d=\"M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\"/><polyline points=\"9 22 9 12 15 12 15 22\"/></svg>";
        default:
            return "<svg $attr><circle cx=\"12\" cy=\"12\" r=\"9\"/></svg>";
    }
}
