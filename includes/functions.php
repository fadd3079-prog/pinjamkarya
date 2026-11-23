<?php

function format_nama_pengguna(string $nama): string {
    $clean = trim((string)preg_replace('/\s*\([^)]*(?:Warga|Mitra|Admin|Teknisi|Skor)[^)]*\)\s*$/iu', '', $nama));
    return $clean !== '' ? $clean : trim($nama);
}

function user_role_label(array $user): string {
    $role = (string)($user['role'] ?? 'warga');
    if ($role === 'admin') {
        return 'Admin';
    }
    if ($role === 'mitra') {
        $is_k = !empty($user['is_kontributor']);
        $is_t = !empty($user['is_teknisi']);
        if ($is_k && $is_t) {
            return 'Mitra & Teknisi';
        }
        return $is_t ? 'Teknisi' : 'Mitra';
    }
    return 'Warga';
}

function render_role_badge(array $user, string $extra_class = ''): string {
    $role = (string)($user['role'] ?? 'warga');
    $mod = 'warga';
    if ($role === 'admin') {
        $mod = 'admin';
    } elseif ($role === 'mitra') {
        $mod = 'mitra';
    }
    $cls = 'pk-role-tag pk-role-tag--' . $mod . ($extra_class !== '' ? ' ' . trim($extra_class) : '');
    return '<span class="' . $cls . '">' . e(user_role_label($user)) . '</span>';
}

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

function render_alert(string $type, string $message, bool $closable = true, string $icon_override = '', string $extra_class = ''): string {
    $type_map = [
        'success' => 'pk-alert--success',
        'warning' => 'pk-alert--warning',
        'danger'  => 'pk-alert--danger',
        'error'   => 'pk-alert--danger',
        'info'    => 'pk-alert--info'
    ];
    $css_class = $type_map[$type] ?? 'pk-alert--info';
    if ($extra_class !== '') {
        $css_class .= ' ' . trim($extra_class);
    }
    $default_icon = $type === 'success'
        ? 'check-circle'
        : (($type === 'danger' || $type === 'error' || $type === 'warning') ? 'alert-triangle' : 'info');
    $icon_name = $icon_override !== '' ? $icon_override : $default_icon;
    $close_btn = $closable
        ? '<button type="button" class="pk-alert-close" aria-label="Tutup pesan">' . svg_icon('x', 15) . '</button>'
        : '';

    return '<div class="pk-alert ' . $css_class . '" role="alert">'
        . '<div class="pk-alert-body">'
        . '<span class="pk-alert-icon">' . svg_icon($icon_name, 16) . '</span>'
        . '<span class="pk-alert-text">' . e($message) . '</span>'
        . '</div>'
        . $close_btn
        . '</div>';
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
    return render_alert((string)($flash['type'] ?? 'info'), (string)($flash['message'] ?? ''));
}

function set_modal_feedback(string $type, string $message, array $old = []): void {
    $_SESSION['modal_feedback'] = [
        'type' => $type,
        'message' => $message,
        'old' => $old,
    ];
}

function get_modal_feedback(): ?array {
    if (!isset($_SESSION['modal_feedback']) || !is_array($_SESSION['modal_feedback'])) {
        return null;
    }
    $fb = $_SESSION['modal_feedback'];
    unset($_SESSION['modal_feedback']);
    return $fb;
}

function require_login(): void {
    if (!isset($_SESSION['user'])) {
        set_flash('warning', 'Silakan masuk terlebih dahulu.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_role(array $allowed_roles): void {
    require_login();
    $user_role = $_SESSION['user']['role'] ?? '';
    if (!in_array($user_role, $allowed_roles, true)) {
        set_flash('danger', 'Akses ditolak untuk peran akun Anda.');
        header('Location: ' . BASE_URL . '/dashboard.php');
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

function is_kontak_kosong(?string $val): bool {
    $trimmed = trim((string)$val);
    return $trimmed === '' || $trimmed === '-';
}

function lengkapi_kontak_user(mysqli $conn, array &$user, array $post): array {
    $need_hp = is_kontak_kosong($user['no_hp'] ?? '');
    $need_alamat = is_kontak_kosong($user['alamat'] ?? '');
    if (!$need_hp && !$need_alamat) {
        return ['ok' => true];
    }

    $no_hp = $need_hp ? trim((string)($post['no_hp'] ?? '')) : (string)$user['no_hp'];
    $alamat = $need_alamat ? trim((string)($post['alamat'] ?? '')) : (string)$user['alamat'];

    if (($need_hp && $no_hp === '') || ($need_alamat && $alamat === '')) {
        return ['ok' => false, 'message' => 'Mohon lengkapi nomor WhatsApp/telepon dan alamat Anda terlebih dahulu.'];
    }

    $id_user = (int)$user['id_user'];
    $stmt = mysqli_prepare($conn, 'UPDATE users SET no_hp = ?, alamat = ? WHERE id_user = ?');
    mysqli_stmt_bind_param($stmt, 'ssi', $no_hp, $alamat, $id_user);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $user['no_hp'] = $no_hp;
    $user['alamat'] = $alamat;
    $_SESSION['user'] = $user;

    return ['ok' => true];
}

function render_kontak_fields_if_needed(array $user, array $post = [], bool $disabled = false): string {
    $need_hp = is_kontak_kosong($user['no_hp'] ?? '');
    $need_alamat = is_kontak_kosong($user['alamat'] ?? '');
    if (!$need_hp && !$need_alamat) {
        return '';
    }

    $dis_attr = $disabled ? ' disabled' : '';
    $html = '';

    if ($need_hp) {
        $val_hp = e($post['no_hp'] ?? '');
        $html .= '<div class="form-group">'
               . '<label for="no_hp" class="form-label">No. Telepon / WhatsApp</label>'
               . '<input type="text" id="no_hp" name="no_hp" class="form-control" placeholder="0812xxxxxxxx" value="' . $val_hp . '" required' . $dis_attr . '>'
               . '</div>';
    }

    if ($need_alamat) {
        $val_alamat = e($post['alamat'] ?? '');
        $html .= '<div class="form-group">'
               . '<label for="alamat" class="form-label">Alamat Lengkap</label>'
               . '<textarea id="alamat" name="alamat" class="form-control" placeholder="Alamat domisili untuk verifikasi peminjaman atau layanan" required' . $dis_attr . '>' . $val_alamat . '</textarea>'
               . '<span class="form-help">Cukup diisi sekali dan otomatis tersimpan ke profil Anda.</span>'
               . '</div>';
    }

    return $html;
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

function build_placeholder_svg(string $label, string $bg_color, string $ring_stroke, string $accent_color): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400">'
         . '<rect width="640" height="400" fill="' . $bg_color . '"/>'
         . '<circle cx="320" cy="200" r="56" fill="#fbfaf8" stroke="' . $ring_stroke . '" stroke-width="1.5"/>'
         . '<g transform="translate(300.4 167.4) scale(0.42)">' . brand_logo_paths('#1e1e1c', $accent_color) . '</g>'
         . '</svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function get_gambar_alat_info(?string $filename, string $label = 'Perkakas Komunitas'): array {
    $clean = trim((string)$filename);
    if ($clean !== '' && $clean !== 'default_alat.jpg') {
        $full_path = UPLOAD_ALAT_PATH . $clean;
        if (file_exists($full_path) && is_file($full_path)) {
            $info = @getimagesize($full_path);
            $w = is_array($info) && !empty($info[0]) ? (int)$info[0] : 640;
            $h = is_array($info) && !empty($info[1]) ? (int)$info[1] : 400;
            $ratio = $w > 0 ? round($h / $w, 4) : 0.625;
            return [
                'url'    => BASE_URL . '/assets/uploads/alat/' . rawurlencode($clean),
                'width'  => $w,
                'height' => $h,
                'ratio'  => $ratio > 0 ? $ratio : 0.625,
            ];
        }
    }
    return [
        'url'    => build_placeholder_svg($label, '#ebe9e4', '#dbd9d3', '#686660'),
        'width'  => 640,
        'height' => 400,
        'ratio'  => 0.625,
    ];
}

function get_gambar_alat(?string $filename, string $label = 'Perkakas Komunitas'): string {
    return get_gambar_alat_info($filename, $label)['url'];
}

function get_gambar_tiket(?string $filename, string $label = 'Barang Perbaikan'): string {
    $clean = trim((string)$filename);
    if ($clean !== '' && $clean !== 'default_tiket.jpg') {
        $full_path = UPLOAD_TIKET_PATH . $clean;
        if (file_exists($full_path) && is_file($full_path)) {
            return BASE_URL . '/assets/uploads/tiket/' . rawurlencode($clean);
        }
    }
    return build_placeholder_svg($label, '#ebe9e4', '#c3c0b9', '#5c5a55');
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
        'Perawatan'           => 'pk-badge--warning',
        'Menunggu Verifikasi' => 'pk-badge--danger',
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

function badge_kondisi_fisik(string $kondisi): string {
    $map = [
        'Baik'         => 'pk-badge--success',
        'Rusak Ringan' => 'pk-badge--warning',
        'Rusak Berat'  => 'pk-badge--danger',
    ];
    $cls = $map[$kondisi] ?? 'pk-badge--muted';
    return '<span class="pk-badge ' . $cls . '">' . e($kondisi) . '</span>';
}

function brand_logo_paths(string $body_color, string $accent_color = ''): string {
    return '<path fill="' . $body_color . '" d="M0.131134 57.1743V10.6218C0.131134 4.82795 4.82798 0.131104 10.6218 0.131104H55.7943C59.5423 0.131104 63.0055 2.13062 64.8795 5.37645L91.7566 51.9289C95.7945 58.9227 90.7471 67.6649 82.6714 67.6649H10.6218C4.82798 67.6649 0.131134 62.9681 0.131134 57.1743Z"/>'
         . '<path fill="' . $body_color . '" d="M0.131134 144.64L0.131141 88.1218C0.131142 82.3279 4.82799 77.6311 10.6218 77.6311H51.3604C57.1543 77.6311 61.8511 82.3279 61.8511 88.1218V144.64C61.8511 150.434 57.1543 155.131 51.3604 155.131H10.6218C4.82798 155.131 0.131134 150.434 0.131134 144.64Z"/>';
}

function brand_logo(string $variant = 'dark', int $size = 22): string {
    $palettes = [
        'dark'  => ['body' => '#f3f2ee', 'accent' => '#b0aea7'],
        'light' => ['body' => '#1e1e1c', 'accent' => '#686660'],
        'soft'  => ['body' => '#1e1e1c', 'accent' => '#5c5a55'],
    ];
    $colors = $palettes[$variant] ?? $palettes['light'];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="-31 0 156 156" aria-hidden="true" focusable="false">'
         . brand_logo_paths($colors['body'], $colors['accent'])
         . '</svg>';
}

function render_public_nav(string $active = 'home', bool $overlay = false): string {
    $is_logged_in = isset($_SESSION['user']);
    $landing_url = BASE_URL . '/index.php';
    $katalog_url = BASE_URL . '/katalog/index.php';

    ob_start();
    ?>
<header id="site-header" class="site-nav<?= $overlay ? ' site-nav--overlay' : '' ?>">
    <div class="site-nav_shell">
        <a class="site-nav_brand" href="<?= e($landing_url) ?>" aria-label="PinjamKarya, beranda">
            <span class="site-nav_logo-wrap"><?= brand_logo('light', 18) ?></span>
            <span class="site-nav_wordmark">PinjamKarya</span>
        </a>

        <button type="button" class="site-nav_toggle" aria-expanded="false" aria-controls="site-menu">
            <span class="site-nav_toggle-label">Menu</span>
            <span class="site-nav_toggle-icon" aria-hidden="true"><span></span></span>
        </button>

        <div class="site-nav_drawer">
            <nav id="site-menu" class="site-nav_menu" aria-label="Navigasi utama">
                <a class="site-nav_link" href="<?= e($landing_url) ?>"<?= $active === 'home' ? ' aria-current="page"' : '' ?>>Home</a>
                <a class="site-nav_link" href="<?= e($katalog_url) ?>"<?= $active === 'katalog' ? ' aria-current="page"' : '' ?>>Katalog</a>
                <?php if ($is_logged_in): ?>
                    <a class="site-nav_cta" href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a class="site-nav_link" href="<?= BASE_URL ?>/auth/register.php">Daftar</a>
                    <a class="site-nav_cta" href="<?= BASE_URL ?>/auth/login.php">Masuk</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</header>
    <?php
    return ob_get_clean();
}

function render_public_footer(): string {
    $is_logged_in = isset($_SESSION['user']);
    $landing_url = BASE_URL . '/index.php';

    ob_start();
    ?>
<footer class="pk-footer">
    <div class="pk-footer-inner">
        <div class="pk-footer-top">
            <a href="<?= e($landing_url) ?>" class="pk-footer-brand">
                <span class="pk-brand-icon"><?= brand_logo('light', 18) ?></span>
                <span>PinjamKarya.</span>
            </a>
            <nav aria-label="Navigasi footer">
                <ul class="pk-footer-links">
                    <li><a href="<?= e($landing_url) ?>">Home</a></li>
                    <li><a href="<?= BASE_URL ?>/katalog/index.php">Katalog</a></li>
                    <?php if ($is_logged_in): ?>
                        <li><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                        <li><a href="<?= BASE_URL ?>/auth/logout.php">Keluar</a></li>
                    <?php else: ?>
                        <li><a href="<?= BASE_URL ?>/auth/login.php">Masuk</a></li>
                        <li><a href="<?= BASE_URL ?>/auth/register.php">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <div class="pk-footer-bottom">
            <span>&copy; 2026 PinjamKarya &bull; Informatika Universitas Jenderal Soedirman</span>
            <a href="#main-content">Kembali ke atas</a>
        </div>
    </div>
</footer>
    <?php
    return ob_get_clean();
}

function render_tool_card(array $alat, string $detail_url, string $heading_tag = 'h2'): string {
    $tag = in_array($heading_tag, ['h2', 'h3'], true) ? $heading_tag : 'h2';
    $is_free = (float)($alat['biaya_perawatan'] ?? 0) <= 0;
    $biaya = $is_free ? 'Gratis' : rupiah((float)$alat['biaya_perawatan']);
    $status_alat = (string)($alat['status_alat'] ?? 'Tersedia');
    $is_unavailable = !empty($alat['is_unavailable']) || ($status_alat !== 'Tersedia');
    $img_info = get_gambar_alat_info($alat['foto_alat'] ?? '', $alat['nama_alat'] ?? '');

    ob_start();
    ?>
<article class="pk-tool-card<?= $is_unavailable ? ' is-unavailable' : '' ?>">
    <div class="pk-tool-media">
        <img src="<?= e($img_info['url']) ?>"
             alt="<?= e($alat['nama_alat'] ?? '') ?>"
             width="<?= (int)$img_info['width'] ?>"
             height="<?= (int)$img_info['height'] ?>"
             loading="lazy">
        <div class="pk-tool-status">
            <?php if ($is_unavailable): ?>
                <span class="pk-badge pk-badge--unavailable">Tidak Tersedia</span>
                <?php if ($status_alat !== 'Tersedia'): ?>
                    <?= badge_status_alat($status_alat) ?>
                <?php endif; ?>
            <?php else: ?>
                <?= badge_status_alat($status_alat) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="pk-tool-body">
        <span class="pk-tool-meta-label"><?= e($alat['nama_kategori'] ?? '') ?></span>
        <<?= $tag ?> class="pk-tool-title"><?= e($alat['nama_alat'] ?? '') ?></<?= $tag ?>>
        <div class="pk-tool-foot">
            <span class="pk-tool-fee<?= $is_free ? ' pk-tool-fee--free' : '' ?>"><?= e($biaya) ?></span>
            <span class="pk-tool-specs"><?= (int)($alat['max_hari_pinjam'] ?? 0) ?> hr &middot; Skor <?= (int)($alat['min_skor'] ?? 0) ?></span>
        </div>
    </div>

    <a href="<?= e($detail_url) ?>" class="pk-tool-hit" aria-label="<?= e(($alat['nama_alat'] ?? '') . ' - ' . ($is_unavailable ? 'Tidak Tersedia (' . $status_alat . ')' : $status_alat)) ?>"></a>
</article>
    <?php
    return ob_get_clean();
}

function google_mark_svg(int $size = 16): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">'
         . '<path d="M21.8 12.23c0-.72-.06-1.42-.18-2.09H12v3.96h5.5a4.7 4.7 0 0 1-2.04 3.08v2.56h3.3c1.93-1.78 3.04-4.4 3.04-7.51z"/>'
         . '<path d="M12 22c2.75 0 5.06-.91 6.75-2.46l-3.3-2.56c-.91.61-2.08.98-3.45.98-2.65 0-4.9-1.79-5.7-4.2H2.89v2.64A10 10 0 0 0 12 22z"/>'
         . '<path d="M6.3 13.76A6 6 0 0 1 6 12c0-.61.11-1.2.3-1.76V7.6H2.89A10 10 0 0 0 2 12c0 1.61.39 3.14 1.07 4.4l3.23-2.64z"/>'
         . '<path d="M12 6.04c1.5 0 2.84.51 3.9 1.52l2.92-2.92C17.05 2.99 14.75 2 12 2A10 10 0 0 0 2.89 7.6l3.41 2.64c.8-2.41 3.05-4.2 5.7-4.2z"/>'
         . '</svg>';
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
        case 'arrow-right':
            return "<svg $attr><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/><polyline points=\"12 5 19 12 12 19\"/></svg>";
        case 'home':
            return "<svg $attr><path d=\"M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\"/><polyline points=\"9 22 9 12 15 12 15 22\"/></svg>";
        case 'edit':
            return "<svg $attr><path d=\"M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7\"/><path d=\"M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z\"/></svg>";
        case 'trash':
            return "<svg $attr><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/><line x1=\"10\" y1=\"11\" x2=\"10\" y2=\"17\"/><line x1=\"14\" y1=\"11\" x2=\"14\" y2=\"17\"/></svg>";
        case 'eye':
            return "<svg $attr><path d=\"M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\"/><circle cx=\"12\" cy=\"12\" r=\"3\"/></svg>";
        case 'globe':
            return "<svg $attr><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z\"/></svg>";
        default:
            return "<svg $attr><circle cx=\"12\" cy=\"12\" r=\"9\"/></svg>";
    }
}
