<?php
/**
 * Kumpulan fungsi utilitas terpusat aplikasi PinjamKarya.
 *
 * Mencakup helper autentikasi & sesi, token CSRF, notifikasi flash/alert,
 * pemformatan teks/mata uang/tanggal, unggah & validasi gambar, query
 * prepared statement, generator kode unik, komponen UI, serta analitik dashboard.
 */

/**
 * Mengembalikan peta nama bulan singkat berbahasa Indonesia (1-12).
 *
 * @return array<int, string> Daftar nama bulan singkat terindeks 1 hingga 12.
 */
function pk_nama_bulan_singkat(): array {
    return [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
}

/**
 * Menghapus sufiks label peran bawaan pada nama pengguna untuk tampilan UI.
 *
 * @param string $nama Nama lengkap pengguna dari database.
 * @return string Nama pengguna yang sudah dirapikan.
 */
function format_nama_pengguna(string $nama): string {
    $clean = trim((string)preg_replace('/\s*\([^)]*(?:Warga|Mitra|Admin|Teknisi|Skor)[^)]*\)\s*$/iu', '', $nama));
    return $clean !== '' ? $clean : trim($nama);
}

/**
 * Menghasilkan label teks peran pengguna berdasarkan role dan sub-peran Mitra.
 *
 * @param array $user Data pengguna yang memuat key role, is_kontributor, dan is_teknisi.
 * @return string Label peran siap tampil ('Admin', 'Mitra & Teknisi', 'Teknisi', 'Mitra', atau 'Warga').
 */
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

/**
 * Merender elemen HTML badge peran pengguna.
 *
 * @param array  $user        Data pengguna aktif atau target.
 * @param string $extra_class Kelas CSS tambahan opsional.
 * @return string Markup HTML <span> badge peran.
 */
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

/**
 * Melakukan escape HTML (anti-XSS) untuk string keluaran ke antarmuka.
 *
 * @param mixed $string Nilai teks yang akan diamankan untuk output HTML.
 * @return string String hasil htmlspecialchars dengan UTF-8.
 */
function e($string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Memformat angka nominal menjadi format mata uang Rupiah (contoh: Rp 25.000).
 *
 * @param int|float|string $angka Nilai nominal numerik.
 * @return string String nominal terformat Rupiah.
 */
function rupiah($angka): string {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Memformat string tanggal menjadi format lokal singkat (contoh: 24 Nov 2026).
 *
 * @param string|null $date_str String tanggal/waktu dari database atau input.
 * @return string Tanggal terformat lokal, atau '-' jika kosong.
 */
function format_tanggal(?string $date_str): string {
    if (empty($date_str)) {
        return '-';
    }
    $ts = strtotime($date_str);
    if (!$ts) {
        return e($date_str);
    }
    $bulan = pk_nama_bulan_singkat();
    $d = date('d', $ts);
    $m = $bulan[(int)date('n', $ts)] ?? date('M', $ts);
    $y = date('Y', $ts);
    return "$d $m $y";
}

/**
 * Merender komponen notifikasi alert bergaya PinjamKarya.
 *
 * @param string $type          Jenis alert ('success', 'warning', 'danger', 'error', 'info').
 * @param string $message       Isi pesan yang ditampilkan.
 * @param bool   $closable      Apakah menampilkan tombol tutup alert.
 * @param string $icon_override Nama ikon SVG opsional untuk menimpa ikon bawaan.
 * @param string $extra_class   Kelas CSS tambahan opsional.
 * @return string Markup HTML blok alert.
 */
function render_alert(string $type, string $message, bool $closable = true, string $icon_override = '', string $extra_class = ''): string {
    $type_map = [
        'success' => 'pk-alert--success',
        'warning' => 'pk-alert--warning',
        'danger'  => 'pk-alert--danger',
        'error'   => 'pk-alert--danger',
        'info'    => 'pk-alert--info',
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

/**
 * Menyimpan pesan notifikasi flash ke dalam sesi untuk ditampilkan sekali.
 *
 * @param string $type    Jenis pesan ('success', 'warning', 'danger', 'info').
 * @param string $message Isi pesan notifikasi.
 * @return void
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Mengambil, menghapus, dan merender pesan flash dari sesi sebagai HTML alert.
 *
 * @return string Markup HTML alert jika ada pesan flash, atau string kosong.
 */
function get_flash(): string {
    if (!isset($_SESSION['flash'])) {
        return '';
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return render_alert((string)($flash['type'] ?? 'info'), (string)($flash['message'] ?? ''));
}

/**
 * Menyimpan umpan balik validasi modal beserta input lama pengguna ke sesi.
 *
 * @param string $type    Jenis umpan balik ('warning', 'danger', 'success', 'info').
 * @param string $message Pesan umpan balik untuk modal.
 * @param array  $old     Nilai input form sebelumnya agar tidak hilang.
 * @return void
 */
function set_modal_feedback(string $type, string $message, array $old = []): void {
    $_SESSION['modal_feedback'] = [
        'type'    => $type,
        'message' => $message,
        'old'     => $old,
    ];
}

/**
 * Mengambil dan menghapus umpan balik modal dari sesi.
 *
 * @return array|null Data umpan balik modal atau null jika tidak tersedia.
 */
function get_modal_feedback(): ?array {
    if (!isset($_SESSION['modal_feedback']) || !is_array($_SESSION['modal_feedback'])) {
        return null;
    }
    $fb = $_SESSION['modal_feedback'];
    unset($_SESSION['modal_feedback']);
    return $fb;
}

/**
 * Memeriksa apakah pengguna saat ini sudah memiliki sesi login aktif.
 *
 * @return bool True jika pengguna sudah login, false jika belum.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

/**
 * Mengambil data array pengguna yang sedang login dari sesi aktif.
 *
 * @return array|null Data pengguna dari $_SESSION['user'] atau null jika belum login.
 */
function current_user(): ?array {
    return is_logged_in() ? $_SESSION['user'] : null;
}

/**
 * Memeriksa apakah peran pengguna termasuk dalam daftar peran yang diizinkan.
 *
 * @param array      $allowed_roles Daftar role yang diperbolehkan (mis. ['admin', 'mitra']).
 * @param array|null $user          Data pengguna opsional; default mengambil pengguna sesi aktif.
 * @return bool True jika peran pengguna cocok dengan salah satu peran yang diizinkan.
 */
function has_role(array $allowed_roles, ?array $user = null): bool {
    $target = $user ?? current_user();
    if ($target === null) {
        return false;
    }
    $user_role = (string)($target['role'] ?? '');
    return in_array($user_role, $allowed_roles, true);
}

/**
 * Memastikan pengguna telah login; mengalihkan ke halaman login jika belum.
 *
 * @return void
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan masuk terlebih dahulu.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Memastikan pengguna telah login dan memiliki salah satu peran yang diizinkan.
 *
 * @param array $allowed_roles Daftar peran yang diizinkan mengakses halaman.
 * @return void
 */
function require_role(array $allowed_roles): void {
    require_login();
    if (!has_role($allowed_roles)) {
        set_flash('danger', 'Akses ditolak untuk peran akun Anda.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Menghasilkan atau mengembalikan token CSRF aktif pada sesi pengguna.
 *
 * @return string Token CSRF heksadesimal sepanjang 64 karakter.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Merender elemen input tersembunyi HTML yang berisi token CSRF sesi aktif.
 *
 * @param string $field_name Nama atribut name pada input hidden (default 'csrf_token').
 * @return string Markup HTML <input type="hidden"> token CSRF.
 */
function csrf_field(string $field_name = 'csrf_token'): string {
    return '<input type="hidden" name="' . e($field_name) . '" value="' . e(csrf_token()) . '">';
}

/**
 * Memvalidasi token CSRF kiriman terhadap token yang tersimpan di sesi secara aman.
 *
 * @param string|null $token      Token yang dikirim; jika null, otomatis membaca $_POST[$field_name].
 * @param string      $field_name Nama key pada $_POST bila $token tidak diberikan.
 * @return bool True jika token valid dan cocok, false jika tidak.
 */
function verify_csrf_token(?string $token = null, string $field_name = 'csrf_token'): bool {
    $session_token = (string)($_SESSION['csrf_token'] ?? '');
    $submitted = $token !== null ? $token : (string)($_POST[$field_name] ?? '');
    if ($session_token === '' || $submitted === '') {
        return false;
    }
    return hash_equals($session_token, $submitted);
}

/**
 * Menyinkronkan data $_SESSION['user'] dengan catatan terbaru dari tabel users.
 *
 * @param mysqli $conn Koneksi database aktif.
 * @return void
 */
function refresh_user_session(mysqli $conn): void {
    if (!isset($_SESSION['user']['id_user'])) {
        return;
    }
    $id_user = (int)$_SESSION['user']['id_user'];
    $row = pk_stmt_one(
        $conn,
        'SELECT id_user, nama, email, no_hp, alamat, role, is_kontributor, is_teknisi, is_verified_teknisi, skor_kredibilitas, created_at FROM users WHERE id_user = ? LIMIT 1',
        'i',
        [$id_user]
    );
    if (!empty($row)) {
        $_SESSION['user'] = $row;
    }
}

/**
 * Memeriksa apakah nilai kontak (nomor HP atau alamat) masih kosong atau placeholder '-'.
 *
 * @param string|null $val Nilai kolom kontak pengguna.
 * @return bool True jika kontak dianggap belum terisi.
 */
function is_kontak_kosong(?string $val): bool {
    $trimmed = trim((string)$val);
    return $trimmed === '' || $trimmed === '-';
}

/**
 * Melengkapi dan menyimpan nomor HP serta alamat pengguna jika masih kosong.
 *
 * @param mysqli $conn Koneksi database aktif.
 * @param array  $user Referensi array data pengguna aktif yang akan diperbarui.
 * @param array  $post Data kiriman formulir ($_POST).
 * @return array{ok: bool, message?: string} Status hasil pembaruan kontak.
 */
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

/**
 * Merender field formulir nomor telepon dan/atau alamat apabila data kontak pengguna belum lengkap.
 *
 * @param array $user     Data pengguna aktif.
 * @param array $post     Data input lama ($_POST) untuk mempertahankan isian form.
 * @param bool  $disabled Apakah field dirender dalam keadaan nonaktif (disabled).
 * @return string Markup HTML field kontak yang diperlukan, atau string kosong jika sudah lengkap.
 */
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

/**
 * Memvalidasi dan mengunggah berkas gambar (JPG, JPEG, PNG, WEBP maksimal 2 MB) ke direktori tujuan.
 *
 * @param array  $file       Elemen array dari $_FILES.
 * @param string $target_dir Jalur direktori penyimpanan berkas tujuan.
 * @param string $prefix     Prefiks nama berkas unik yang dihasilkan.
 * @return array{success: bool, filename?: string, message?: string} Hasil proses unggah gambar.
 */
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

/**
 * Mengeksekusi kueri SELECT dengan prepared statement dan mengembalikan seluruh baris hasil.
 *
 * @param mysqli $conn   Koneksi database aktif.
 * @param string $sql    String kueri SQL dengan placeholder '?'.
 * @param string $types  String tipe parameter bind_param (mis. 'is').
 * @param array  $params Daftar nilai parameter yang diikat ke statement.
 * @return array<int, array<string, mixed>> Daftar baris hasil asosiatif.
 */
function pk_stmt_all(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Mengeksekusi kueri SELECT dengan prepared statement dan mengembalikan baris pertama saja.
 *
 * @param mysqli $conn   Koneksi database aktif.
 * @param string $sql    String kueri SQL dengan placeholder '?'.
 * @param string $types  String tipe parameter bind_param (mis. 'i').
 * @param array  $params Daftar nilai parameter yang diikat ke statement.
 * @return array<string, mixed> Baris hasil pertama atau array kosong jika tidak ditemukan.
 */
function pk_stmt_one(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $rows = pk_stmt_all($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

/**
 * Menghitung jumlah peminjaman aktif yang bentrok dengan rentang tanggal tertentu untuk suatu alat.
 *
 * @param mysqli $conn        Koneksi database aktif.
 * @param int    $id_alat     ID aset alat yang diperiksa.
 * @param string $tgl_mulai   Tanggal mulai pinjam (YYYY-MM-DD).
 * @param string $tgl_selesai Tanggal selesai pinjam (YYYY-MM-DD).
 * @return int Jumlah jadwal yang beririsan.
 */
function cek_bentrok_jadwal(mysqli $conn, int $id_alat, string $tgl_mulai, string $tgl_selesai): int {
    $sql = "SELECT COUNT(*) AS bentrok FROM peminjaman
            WHERE id_alat = ?
              AND status_peminjaman IN ('Dipesan', 'Dipinjam')
              AND (tgl_mulai <= ? AND tgl_selesai >= ?)";
    $row = pk_stmt_one($conn, $sql, 'iss', [$id_alat, $tgl_selesai, $tgl_mulai]);
    return (int)($row['bentrok'] ?? 0);
}

/**
 * Membatalkan secara otomatis reservasi berstatus 'Dipesan' yang tanggal mulainya sudah terlewat.
 *
 * @param mysqli $conn Koneksi database aktif.
 * @return int Jumlah baris peminjaman yang dibatalkan.
 */
function bersihkan_ghost_booking(mysqli $conn): int {
    $sql = "UPDATE peminjaman SET status_peminjaman = 'Dibatalkan'
            WHERE status_peminjaman = 'Dipesan' AND tgl_mulai < CURDATE()";
    mysqli_query($conn, $sql);
    return mysqli_affected_rows($conn);
}

/**
 * Mencari berkas gambar hero banner lokal yang tersedia pada direktori assets/img/.
 *
 * @return string|null URL penuh banner hero jika ditemukan, atau null.
 */
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

/**
 * Membuat data URI SVG placeholder bawaan untuk foto alat atau barang perbaikan.
 *
 * @param string $label        Label deskriptif placeholder.
 * @param string $bg_color     Kode warna latar belakang SVG.
 * @param string $ring_stroke  Kode warna garis lingkaran tengah.
 * @param string $accent_color Kode warna aksen logo.
 * @return string Data URI SVG siap dipakai pada atribut src gambar.
 */
function build_placeholder_svg(string $label, string $bg_color, string $ring_stroke, string $accent_color): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400">'
         . '<rect width="640" height="400" fill="' . $bg_color . '"/>'
         . '<circle cx="320" cy="200" r="56" fill="#fbfaf8" stroke="' . $ring_stroke . '" stroke-width="1.5"/>'
         . '<g transform="translate(300.4 167.4) scale(0.42)">' . brand_logo_paths('#1e1e1c', $accent_color) . '</g>'
         . '</svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

/**
 * Mengambil informasi URL, dimensi, dan rasio aspek gambar aset alat (atau placeholder SVG).
 *
 * @param string|null $filename Nama berkas foto alat dari database.
 * @param string      $label    Label fallback untuk placeholder SVG.
 * @return array{url: string, width: int, height: int, ratio: float} Metadata gambar alat.
 */
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

/**
 * Mengambil URL gambar aset alat atau data URI placeholder jika tidak tersedia.
 *
 * @param string|null $filename Nama berkas foto alat.
 * @param string      $label    Label fallback untuk placeholder.
 * @return string URL gambar alat siap pakai.
 */
function get_gambar_alat(?string $filename, string $label = 'Perkakas Komunitas'): string {
    return get_gambar_alat_info($filename, $label)['url'];
}

/**
 * Mengambil URL gambar barang tiket perbaikan atau data URI placeholder jika tidak tersedia.
 *
 * @param string|null $filename Nama berkas foto barang tiket.
 * @param string      $label    Label fallback untuk placeholder.
 * @return string URL gambar tiket siap pakai.
 */
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

/**
 * Menghasilkan kode transaksi unik harian berurutan (format: TAG-YYYYMMDD-NNN) secara terpusat.
 *
 * @param mysqli $conn       Koneksi database aktif.
 * @param string $table      Nama tabel target ('peminjaman' atau 'tiket_perbaikan').
 * @param string $column     Nama kolom kode unik ('kode_booking' atau 'kode_tiket').
 * @param string $id_column  Nama kolom primary key ('id_peminjaman' atau 'id_tiket').
 * @param string $prefix_tag Prefiks kode transaksi (mis. 'PK' atau 'RTR').
 * @return string Kode unik baru yang dijamin belum digunakan di tabel target.
 */
function generate_kode_unik(mysqli $conn, string $table, string $column, string $id_column, string $prefix_tag): string {
    $allowed = [
        'peminjaman'      => ['col' => 'kode_booking', 'id' => 'id_peminjaman'],
        'tiket_perbaikan' => ['col' => 'kode_tiket', 'id' => 'id_tiket'],
    ];
    if (!isset($allowed[$table]) || $allowed[$table]['col'] !== $column || $allowed[$table]['id'] !== $id_column) {
        throw new InvalidArgumentException('Target tabel atau kolom kode unik tidak valid.');
    }

    $today = date('Ymd');
    $prefix = $prefix_tag . '-' . $today . '-';
    $row = pk_stmt_one($conn, "SELECT COUNT(*) AS total FROM {$table} WHERE {$column} LIKE CONCAT(?, '%')", 's', [$prefix]);
    $next = ((int)($row['total'] ?? 0)) + 1;

    do {
        $candidate = $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        $exists = pk_stmt_one($conn, "SELECT {$id_column} FROM {$table} WHERE {$column} = ? LIMIT 1", 's', [$candidate]);
        if (!empty($exists)) {
            $next++;
        }
    } while (!empty($exists));

    return $candidate;
}

/**
 * Menghasilkan kode booking peminjaman unik harian (format: PK-YYYYMMDD-NNN).
 *
 * @param mysqli $conn Koneksi database aktif.
 * @return string Kode booking unik baru.
 */
function generate_kode_booking(mysqli $conn): string {
    return generate_kode_unik($conn, 'peminjaman', 'kode_booking', 'id_peminjaman', 'PK');
}

/**
 * Menghasilkan kode tiket perbaikan unik harian (format: RTR-YYYYMMDD-NNN).
 *
 * @param mysqli $conn Koneksi database aktif.
 * @return string Kode tiket perbaikan unik baru.
 */
function generate_kode_tiket(mysqli $conn): string {
    return generate_kode_unik($conn, 'tiket_perbaikan', 'kode_tiket', 'id_tiket', 'RTR');
}

/**
 * Merender elemen badge status generik berdasarkan peta label ke kelas CSS.
 *
 * @param string                $label         Teks label status yang akan ditampilkan.
 * @param array<string, string> $style_map     Peta nilai status ke kelas modifier CSS.
 * @param string                $default_class Kelas fallback jika status tidak ada di peta.
 * @return string Markup HTML <span> badge status.
 */
function render_badge(string $label, array $style_map, string $default_class = 'pk-badge--muted'): string {
    $cls = $style_map[$label] ?? $default_class;
    return '<span class="pk-badge ' . $cls . '">' . e($label) . '</span>';
}

/**
 * Merender badge status ketersediaan aset alat.
 *
 * @param string $status Status alat ('Tersedia', 'Dipinjam', 'Perawatan', 'Menunggu Verifikasi').
 * @return string Markup HTML badge status alat.
 */
function badge_status_alat(string $status): string {
    return render_badge($status, [
        'Tersedia'            => 'pk-badge--success',
        'Dipinjam'            => 'pk-badge--primary',
        'Perawatan'           => 'pk-badge--warning',
        'Menunggu Verifikasi' => 'pk-badge--danger',
    ]);
}

/**
 * Merender badge status transaksi peminjaman.
 *
 * @param string $status Status peminjaman ('Dipesan', 'Dipinjam', 'Selesai', 'Dibatalkan').
 * @return string Markup HTML badge status peminjaman.
 */
function badge_status_pinjam(string $status): string {
    return render_badge($status, [
        'Dipesan'    => 'pk-badge--warning',
        'Dipinjam'   => 'pk-badge--primary',
        'Selesai'    => 'pk-badge--success',
        'Dibatalkan' => 'pk-badge--danger',
    ]);
}

/**
 * Merender badge status tiket perbaikan Right-to-Repair.
 *
 * @param string $status Status tiket perbaikan.
 * @return string Markup HTML badge status tiket.
 */
function badge_status_tiket(string $status): string {
    return render_badge($status, [
        'Menunggu Diagnosis'   => 'pk-badge--warning',
        'Menunggu Persetujuan' => 'pk-badge--primary',
        'Dikerjakan'           => 'pk-badge--primary',
        'Siap Diambil'         => 'pk-badge--success',
        'Selesai'              => 'pk-badge--success',
        'Dibatalkan'           => 'pk-badge--danger',
    ]);
}

/**
 * Merender badge kondisi fisik hasil inspeksi alat.
 *
 * @param string $kondisi Kondisi fisik ('Baik', 'Rusak Ringan', 'Rusak Berat').
 * @return string Markup HTML badge kondisi fisik.
 */
function badge_kondisi_fisik(string $kondisi): string {
    return render_badge($kondisi, [
        'Baik'         => 'pk-badge--success',
        'Rusak Ringan' => 'pk-badge--warning',
        'Rusak Berat'  => 'pk-badge--danger',
    ]);
}

/**
 * Mengembalikan elemen <path> SVG pembentuk logo merek PinjamKarya.
 *
 * @param string $body_color   Warna isi utama path logo.
 * @param string $accent_color Warna aksen opsional (disimpan untuk kompatibilitas).
 * @return string Fragmen HTML/SVG <path> logo.
 */
function brand_logo_paths(string $body_color, string $accent_color = ''): string {
    return '<path fill="' . $body_color . '" d="M0.131134 57.1743V10.6218C0.131134 4.82795 4.82798 0.131104 10.6218 0.131104H55.7943C59.5423 0.131104 63.0055 2.13062 64.8795 5.37645L91.7566 51.9289C95.7945 58.9227 90.7471 67.6649 82.6714 67.6649H10.6218C4.82798 67.6649 0.131134 62.9681 0.131134 57.1743Z"/>'
         . '<path fill="' . $body_color . '" d="M0.131134 144.64L0.131141 88.1218C0.131142 82.3279 4.82799 77.6311 10.6218 77.6311H51.3604C57.1543 77.6311 61.8511 82.3279 61.8511 88.1218V144.64C61.8511 150.434 57.1543 155.131 51.3604 155.131H10.6218C4.82798 155.131 0.131134 150.434 0.131134 144.64Z"/>';
}

/**
 * Merender ikon SVG logo merek PinjamKarya sesuai varian warna dan ukuran.
 *
 * @param string $variant Varian palet warna ('dark', 'light', 'soft').
 * @param int    $size    Ukuran lebar dan tinggi SVG dalam piksel.
 * @return string Markup HTML <svg> logo merek.
 */
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

/**
 * Merender bilah navigasi publik untuk halaman beranda dan katalog.
 *
 * @param string $active  Halaman aktif saat ini ('home' atau 'katalog').
 * @param bool   $overlay Apakah navbar menggunakan mode overlay transparan.
 * @return string Markup HTML <header> navigasi publik.
 */
function render_public_nav(string $active = 'home', bool $overlay = false): string {
    $is_logged_in = is_logged_in();
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

/**
 * Merender bagian footer publik aplikasi PinjamKarya.
 *
 * @return string Markup HTML <footer> publik.
 */
function render_public_footer(): string {
    $is_logged_in = is_logged_in();
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

/**
 * Merender kartu katalog perkakas untuk halaman beranda maupun daftar katalog.
 *
 * @param array  $alat        Baris data aset alat beserta nama kategori.
 * @param string $detail_url  URL halaman detail alat tujuan.
 * @param string $heading_tag Tag heading HTML untuk judul alat ('h2' atau 'h3').
 * @return string Markup HTML <article> kartu alat.
 */
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

/**
 * Menyusun data seri tren bulanan 6 bulan terakhir untuk dua metrik dashboard.
 *
 * @param array<int, array<string, mixed>> $rows_a Hasil agregasi bulanan seri pertama (key 'ym', 'cnt').
 * @param array<int, array<string, mixed>> $rows_b Hasil agregasi bulanan seri kedua (key 'ym', 'cnt').
 * @return array{points: array<int, array{label: string, a: int, b: int}>, max: int, sum_a: int, sum_b: int} Struktur data grafik tren.
 */
function pk_build_monthly_trend(array $rows_a, array $rows_b): array {
    $bulan_short = pk_nama_bulan_singkat();
    $map_a = [];
    foreach ($rows_a as $r) {
        $map_a[(string)$r['ym']] = (int)$r['cnt'];
    }
    $map_b = [];
    foreach ($rows_b as $r) {
        $map_b[(string)$r['ym']] = (int)$r['cnt'];
    }

    $base = strtotime(date('Y-m-01'));
    $points = [];
    $max = 1;
    $sum_a = 0;
    $sum_b = 0;
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-{$i} months", $base);
        $ym = date('Y-m', $ts);
        $a = $map_a[$ym] ?? 0;
        $b = $map_b[$ym] ?? 0;
        $max = max($max, $a, $b);
        $sum_a += $a;
        $sum_b += $b;
        $points[] = [
            'label' => $bulan_short[(int)date('n', $ts)] ?? date('M', $ts),
            'a'     => $a,
            'b'     => $b,
        ];
    }

    return [
        'points' => $points,
        'max'    => $max,
        'sum_a'  => $sum_a,
        'sum_b'  => $sum_b,
    ];
}

/**
 * Merender grafik batang SVG lokal 6 bulan terakhir untuk dashboard.
 *
 * @param array  $trend   Struktur data tren keluaran pk_build_monthly_trend().
 * @param string $label_a Label seri batang pertama.
 * @param string $label_b Label seri batang kedua.
 * @return string Markup HTML <svg> grafik tren.
 */
function pk_render_svg_trend(array $trend, string $label_a, string $label_b): string {
    $w = 540;
    $h = 180;
    $pad_l = 28;
    $pad_r = 12;
    $pad_t = 18;
    $pad_b = 26;
    $plot_w = $w - $pad_l - $pad_r;
    $plot_h = $h - $pad_t - $pad_b;
    $base_y = $pad_t + $plot_h;
    $max = max(1, (int)$trend['max']);
    $points = $trend['points'];
    $slot_w = $plot_w / max(1, count($points));
    $bar_w = 18;
    $gap = 5;

    ob_start();
    ?>
<svg class="pk-svg-chart" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="Grafik tren 6 bulan: <?= e($label_a) ?> dan <?= e($label_b) ?>">
    <title>Tren 6 bulan: <?= e($label_a) ?> &amp; <?= e($label_b) ?></title>
    <?php foreach ([0.25, 0.5, 0.75, 1.0] as $step): ?>
        <?php
        $gy = round($base_y - ($plot_h * $step), 1);
        $gval = (int)round($max * $step);
        ?>
        <line x1="<?= $pad_l ?>" y1="<?= $gy ?>" x2="<?= $w - $pad_r ?>" y2="<?= $gy ?>" class="pk-chart-gridline"/>
        <?php if ($step === 0.5 || $step === 1.0): ?>
            <text x="<?= $pad_l - 6 ?>" y="<?= $gy + 4 ?>" text-anchor="end" class="pk-chart-axis-text"><?= $gval ?></text>
        <?php endif; ?>
    <?php endforeach; ?>
    <line x1="<?= $pad_l ?>" y1="<?= $base_y ?>" x2="<?= $w - $pad_r ?>" y2="<?= $base_y ?>" class="pk-chart-baseline"/>
    <text x="<?= $pad_l - 6 ?>" y="<?= $base_y + 4 ?>" text-anchor="end" class="pk-chart-axis-text">0</text>

    <?php foreach ($points as $idx => $pt): ?>
        <?php
        $cx = $pad_l + ($slot_w * $idx) + ($slot_w / 2);
        $xa = round($cx - $bar_w - ($gap / 2), 1);
        $xb = round($cx + ($gap / 2), 1);
        $ha = $pt['a'] > 0 ? max(4, round(($pt['a'] / $max) * $plot_h, 1)) : 2;
        $hb = $pt['b'] > 0 ? max(4, round(($pt['b'] / $max) * $plot_h, 1)) : 2;
        $ya = round($base_y - $ha, 1);
        $yb = round($base_y - $hb, 1);
        ?>
        <g>
            <rect x="<?= $xa ?>" y="<?= $ya ?>" width="<?= $bar_w ?>" height="<?= $ha ?>" rx="4" class="pk-chart-bar-a">
                <title><?= e($pt['label']) ?> - <?= e($label_a) ?>: <?= (int)$pt['a'] ?></title>
            </rect>
            <?php if ($pt['a'] > 0): ?>
                <text x="<?= round($xa + ($bar_w / 2), 1) ?>" y="<?= $ya - 4 ?>" text-anchor="middle" class="pk-chart-val-text"><?= (int)$pt['a'] ?></text>
            <?php endif; ?>

            <rect x="<?= $xb ?>" y="<?= $yb ?>" width="<?= $bar_w ?>" height="<?= $hb ?>" rx="4" class="pk-chart-bar-b">
                <title><?= e($pt['label']) ?> - <?= e($label_b) ?>: <?= (int)$pt['b'] ?></title>
            </rect>
            <?php if ($pt['b'] > 0): ?>
                <text x="<?= round($xb + ($bar_w / 2), 1) ?>" y="<?= $yb - 4 ?>" text-anchor="middle" class="pk-chart-val-text"><?= (int)$pt['b'] ?></text>
            <?php endif; ?>

            <text x="<?= round($cx, 1) ?>" y="<?= $base_y + 18 ?>" text-anchor="middle" class="pk-chart-axis-text"><?= e($pt['label']) ?></text>
        </g>
    <?php endforeach; ?>
</svg>
    <?php
    return ob_get_clean();
}

/**
 * Mengurutkan gabungan item aktivitas dashboard dari terbaru ke terlama lalu membatasi jumlahnya.
 *
 * @param array<int, array<string, mixed>> $items Daftar item aktivitas yang memiliki key 'sort_ts'.
 * @param int                              $limit Batas maksimal baris yang dikembalikan.
 * @return array<int, array<string, mixed>> Daftar aktivitas terurut dan terpotong.
 */
function pk_sort_and_limit_queue(array $items, int $limit = 6): array {
    usort($items, static function (array $x, array $y): int {
        return ($y['sort_ts'] ?? 0) <=> ($x['sort_ts'] ?? 0);
    });
    return array_slice($items, 0, $limit);
}

/**
 * Merender tata letak Bento Grid dashboard (hero, KPI, tren 6 bulan, distribusi, antrean, pintasan).
 *
 * @param array  $user         Data pengguna yang sedang login.
 * @param string $display_name Nama tampilan pengguna yang sudah diformat.
 * @param array  $view         Paket data view-model dashboard yang disiapkan oleh parsial peran.
 * @return string Markup HTML lengkap konten dashboard.
 */
function pk_render_dashboard_bento(array $user, string $display_name, array $view): string {
    $status_pill      = (string)($view['status_pill'] ?? '');
    $header_actions   = (array)($view['header_actions'] ?? []);
    $kpis             = (array)($view['kpis'] ?? []);
    $trend_label_a    = (string)($view['trend_label_a'] ?? 'Peminjaman');
    $trend_label_b    = (string)($view['trend_label_b'] ?? 'Perbaikan');
    $trend_data       = (array)($view['trend_data'] ?? ['points' => [], 'max' => 1, 'sum_a' => 0, 'sum_b' => 0]);
    $dist_title       = (string)($view['dist_title'] ?? 'Status Aset');
    $dist_total       = (int)($view['dist_total'] ?? 0);
    $dist_items       = (array)($view['dist_items'] ?? []);
    $meters           = (array)($view['meters'] ?? []);
    $queue_link_href  = (string)($view['queue_link_href'] ?? '');
    $queue_link_label = (string)($view['queue_link_label'] ?? 'Lihat Semua');
    $queue_rows       = (array)($view['queue_rows'] ?? []);
    $empty_queue      = (array)($view['empty_queue'] ?? []);
    $shortcuts        = (array)($view['shortcuts'] ?? []);

    $tone_map = [
        'ok'     => ' pk-kpi--ok',
        'info'   => ' pk-kpi--info',
        'warn'   => ' pk-kpi--warn',
        'danger' => ' pk-kpi--danger',
    ];

    ob_start();
    ?>

<section class="pk-bento-hero" aria-labelledby="dash-greeting">
    <div class="pk-bento-hero-main">
        <div class="pk-bento-kicker">
            <?= render_role_badge($user) ?>
            <span>&bull;</span>
            <span><?= e(format_tanggal(date('Y-m-d'))) ?></span>
            <?php if ($status_pill !== ''): ?>
                <?= $status_pill ?>
            <?php endif; ?>
        </div>
        <h1 id="dash-greeting">Halo, <?= e($display_name) ?></h1>
    </div>

    <div class="pk-bento-actions">
        <?php foreach ($header_actions as $act): ?>
            <a href="<?= e($act['href']) ?>" class="btn <?= !empty($act['primary']) ? 'btn-primary' : 'btn-outline' ?>">
                <?= svg_icon($act['icon'], 16) ?>
                <span><?= e($act['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="pk-bento-kpis" aria-label="Ringkasan Metrik">
    <?php foreach ($kpis as $kpi): ?>
        <?php $tone_cls = $tone_map[$kpi['tone']] ?? ''; ?>
        <a href="<?= e($kpi['href']) ?>" class="pk-kpi<?= $tone_cls ?>">
            <div class="pk-kpi-top">
                <span class="pk-kpi-label"><?= e($kpi['label']) ?></span>
                <span class="pk-kpi-icon" aria-hidden="true"><?= svg_icon($kpi['icon'], 15) ?></span>
            </div>
            <div class="pk-kpi-value"><?= e($kpi['value']) ?></div>
            <div class="pk-kpi-foot"><?= e($kpi['foot']) ?></div>
        </a>
    <?php endforeach; ?>
</section>

<section class="pk-bento-grid" aria-label="Analitik dan Antrean">
    <article class="pk-bento-card pk-bento-span-7" aria-labelledby="chart-trend-heading">
        <div class="pk-bento-head">
            <h2 id="chart-trend-heading" class="pk-bento-title">
                <?= svg_icon('bar-chart', 17) ?>
                <span>Tren 6 Bulan</span>
            </h2>
            <div class="pk-chart-legend" aria-hidden="true">
                <span class="pk-legend-item">
                    <span class="pk-legend-swatch pk-legend-swatch--primary"></span>
                    <span><?= e($trend_label_a) ?> (<?= (int)$trend_data['sum_a'] ?>)</span>
                </span>
                <span class="pk-legend-item">
                    <span class="pk-legend-swatch pk-legend-swatch--secondary"></span>
                    <span><?= e($trend_label_b) ?> (<?= (int)$trend_data['sum_b'] ?>)</span>
                </span>
            </div>
        </div>
        <div class="pk-svg-chart-wrap">
            <?= pk_render_svg_trend($trend_data, $trend_label_a, $trend_label_b) ?>
        </div>
    </article>

    <article class="pk-bento-card pk-bento-span-5" aria-labelledby="dist-heading">
        <div class="pk-bento-head">
            <h2 id="dist-heading" class="pk-bento-title">
                <?= svg_icon('box', 17) ?>
                <span><?= e($dist_title) ?></span>
            </h2>
        </div>

        <div class="pk-dist-bar" role="presentation">
            <?php if ($dist_total > 0): ?>
                <?php foreach ($dist_items as $di): ?>
                    <?php if ((int)$di['count'] > 0): ?>
                        <?php $seg_w = max(4, round(((int)$di['count'] / $dist_total) * 100, 1)); ?>
                        <span class="pk-dist-seg pk-dist-seg--<?= e($di['seg']) ?>" style="width: <?= $seg_w ?>%;" title="<?= e($di['label']) ?>: <?= (int)$di['count'] ?>"></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="pk-dist-list">
            <?php foreach ($dist_items as $di): ?>
                <?php $pct_item = $dist_total > 0 ? (int)round(((int)$di['count'] / $dist_total) * 100) : 0; ?>
                <div class="pk-dist-item">
                    <span class="pk-dist-name">
                        <span class="pk-dist-dot pk-dist-seg--<?= e($di['seg']) ?>" aria-hidden="true"></span>
                        <span><?= e($di['label']) ?></span>
                    </span>
                    <span class="pk-dist-count"><?= (int)$di['count'] ?> <span class="text-muted">(<?= $pct_item ?>%)</span></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($meters)): ?>
            <div class="pk-meters">
                <?php foreach ($meters as $mt): ?>
                    <div>
                        <div class="pk-meter-head">
                            <span class="pk-meter-label"><?= e($mt['label']) ?></span>
                            <span class="pk-meter-val"><?= e($mt['val']) ?></span>
                        </div>
                        <div class="pk-meter-track" role="progressbar" aria-valuenow="<?= (int)$mt['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($mt['label']) ?>">
                            <div class="pk-meter-fill pk-meter-fill--<?= e($mt['tone']) ?>" style="width: <?= max(0, min(100, (int)$mt['pct'])) ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="pk-bento-card pk-bento-card--table pk-bento-span-8" aria-labelledby="queue-heading">
        <div class="pk-bento-head">
            <h2 id="queue-heading" class="pk-bento-title">
                <?= svg_icon('clipboard', 17) ?>
                <span>Aktivitas Terbaru</span>
            </h2>
            <a href="<?= e($queue_link_href) ?>" class="pk-bento-link"><?= e($queue_link_label) ?></a>
        </div>

        <?php if (empty($queue_rows)): ?>
            <div class="pk-empty-state">
                <div class="pk-empty-icon"><?= svg_icon($empty_queue['icon'], 22) ?></div>
                <h3><?= e($empty_queue['title']) ?></h3>
                <div class="mt-2">
                    <a href="<?= e($empty_queue['href']) ?>" class="btn btn-primary"><?= e($empty_queue['btn']) ?></a>
                </div>
            </div>
        <?php else: ?>
            <div class="pk-table-scroll">
                <table class="pk-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Item</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queue_rows as $qr): ?>
                            <tr>
                                <td class="pk-nowrap">
                                    <span class="pk-code-tag"><?= e($qr['code']) ?></span>
                                    <div class="pk-td-sub mt-1"><?= e($qr['kind']) ?></div>
                                </td>
                                <td>
                                    <strong><?= e($qr['title']) ?></strong>
                                    <div class="pk-td-sub"><?= e($qr['sub']) ?></div>
                                </td>
                                <td class="pk-nowrap"><?= e($qr['date']) ?></td>
                                <td class="pk-nowrap"><?= $qr['badge'] ?></td>
                                <td class="pk-nowrap">
                                    <a href="<?= e($qr['href']) ?>" class="btn btn-outline"><?= e($qr['cta']) ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="pk-bento-card pk-bento-span-4" aria-labelledby="focus-heading">
        <div class="pk-bento-head">
            <h2 id="focus-heading" class="pk-bento-title">
                <?= svg_icon('shield', 17) ?>
                <span>Pintasan</span>
            </h2>
        </div>

        <div class="pk-shortcut-list">
            <?php foreach ($shortcuts as $sc): ?>
                <a href="<?= e($sc['href']) ?>" class="pk-shortcut-row">
                    <span class="pk-shortcut-left">
                        <span class="pk-shortcut-icon" aria-hidden="true"><?= svg_icon($sc['icon'], 16) ?></span>
                        <span class="pk-shortcut-label"><?= e($sc['label']) ?></span>
                    </span>
                    <span class="pk-shortcut-count<?= !empty($sc['urgent']) ? ' pk-shortcut-count--urgent' : '' ?>"><?= e($sc['count']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>
</section>
    <?php
    return ob_get_clean();
}

/**
 * Merender ikon SVG merek Google untuk tombol visual pada halaman autentikasi.
 *
 * @param int $size Ukuran lebar dan tinggi ikon dalam piksel.
 * @return string Markup HTML <svg> ikon Google.
 */
function google_mark_svg(int $size = 16): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">'
         . '<path d="M21.8 12.23c0-.72-.06-1.42-.18-2.09H12v3.96h5.5a4.7 4.7 0 0 1-2.04 3.08v2.56h3.3c1.93-1.78 3.04-4.4 3.04-7.51z"/>'
         . '<path d="M12 22c2.75 0 5.06-.91 6.75-2.46l-3.3-2.56c-.91.61-2.08.98-3.45.98-2.65 0-4.9-1.79-5.7-4.2H2.89v2.64A10 10 0 0 0 12 22z"/>'
         . '<path d="M6.3 13.76A6 6 0 0 1 6 12c0-.61.11-1.2.3-1.76V7.6H2.89A10 10 0 0 0 2 12c0 1.61.39 3.14 1.07 4.4l3.23-2.64z"/>'
         . '<path d="M12 6.04c1.5 0 2.84.51 3.9 1.52l2.92-2.92C17.05 2.99 14.75 2 12 2A10 10 0 0 0 2.89 7.6l3.41 2.64c.8-2.41 3.05-4.2 5.7-4.2z"/>'
         . '</svg>';
}

/**
 * Merender ikon vektor SVG inline lokal berdasarkan nama ikon.
 *
 * @param string $name Nama ikon yang diminta (mis. 'box', 'calendar', 'wrench', 'heart').
 * @param int    $size Ukuran lebar dan tinggi ikon dalam piksel.
 * @return string Markup HTML <svg> ikon.
 */
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