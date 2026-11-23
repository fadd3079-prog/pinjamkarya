<?php
require_once dirname(__DIR__) . '/config/app.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = '-';
    $role = ($_POST['role'] ?? 'warga') === 'mitra' ? 'mitra' : 'warga';

    $is_kontributor = ($role === 'mitra' && !empty($_POST['is_kontributor'])) ? 1 : 0;
    $is_teknisi = ($role === 'mitra' && !empty($_POST['is_teknisi'])) ? 1 : 0;
    $is_verified_teknisi = 0;
    $skor_kredibilitas = 100;

    if ($nama === '' || $email === '' || $password === '' || $no_hp === '') {
        set_flash('warning', 'Seluruh kolom formulir pendaftaran wajib diisi.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('warning', 'Format alamat email tidak sah.');
    } elseif (strlen($password) < 6) {
        set_flash('warning', 'Kata sandi minimal terdiri dari 6 karakter.');
    } elseif ($role === 'mitra' && $is_kontributor === 0 && $is_teknisi === 0) {
        set_flash('warning', 'Akun Mitra wajib memilih minimal satu sub-peran.');
    } else {
        $chk = mysqli_prepare($conn, 'SELECT id_user FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($chk, 's', $email);
        mysqli_stmt_execute($chk);
        $chk_res = mysqli_stmt_get_result($chk);
        $existing = mysqli_fetch_assoc($chk_res);
        mysqli_stmt_close($chk);

        if ($existing) {
            set_flash('danger', 'Alamat email sudah terdaftar.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'INSERT INTO users (nama, email, password, no_hp, alamat, role, is_kontributor, is_teknisi, is_verified_teknisi, skor_kredibilitas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssssssiiii', $nama, $email, $hash, $no_hp, $alamat, $role, $is_kontributor, $is_teknisi, $is_verified_teknisi, $skor_kredibilitas);
            mysqli_stmt_execute($stmt);
            $new_user_id = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $q_user = mysqli_prepare($conn, 'SELECT id_user, nama, email, no_hp, alamat, role, is_kontributor, is_teknisi, is_verified_teknisi, skor_kredibilitas, created_at FROM users WHERE id_user = ? LIMIT 1');
            mysqli_stmt_bind_param($q_user, 'i', $new_user_id);
            mysqli_stmt_execute($q_user);
            $new_user = mysqli_fetch_assoc(mysqli_stmt_get_result($q_user));
            mysqli_stmt_close($q_user);

            session_regenerate_id(true);
            $_SESSION['user'] = $new_user ?: [
                'id_user'             => $new_user_id,
                'nama'                => $nama,
                'email'               => $email,
                'no_hp'               => $no_hp,
                'alamat'              => $alamat,
                'role'                => $role,
                'is_kontributor'      => $is_kontributor,
                'is_teknisi'          => $is_teknisi,
                'is_verified_teknisi' => $is_verified_teknisi,
                'skor_kredibilitas'   => $skor_kredibilitas,
            ];

            $welcome_msg = $role === 'mitra'
                ? 'Pendaftaran akun Mitra berhasil. Selamat datang di dashboard.'
                : 'Pendaftaran berhasil (Modal awal: 100 Poin Skor Kepercayaan). Selamat datang di dashboard.';
            set_flash('success', $welcome_msg);
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
    }
}

$page_title = 'Daftar Akun';
$minimal_layout = true;
require_once ROOT_PATH . '/includes/header.php';
?>

<section class="pk-login pk-login--wide" aria-labelledby="register-heading">
    <header class="pk-login-head">
        <a class="pk-login-brand" href="<?= BASE_URL ?>/index.php">
            <span class="pk-login-brand-icon"><?= brand_logo('light', 16) ?></span>
            <span>PinjamKarya</span>
            <span class="pk-login-sep" aria-hidden="true">/</span>
            <span class="pk-login-scope">Daftar</span>
        </a>
        <h1 id="register-heading" class="pk-login-title">Daftar Akun</h1>
    </header>

    <?= get_flash() ?>

    <form method="post" action="<?= BASE_URL ?>/auth/register.php">
        <div class="form-group">
            <label for="nama" class="form-label">Nama Lengkap</label>
            <input type="text" id="nama" name="nama" class="form-control" placeholder="Nama lengkap" value="<?= e($_POST['nama'] ?? '') ?>" required autofocus>
        </div>

        <div class="pk-grid pk-grid--2">
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="no_hp" class="form-label">No. Telepon / WhatsApp</label>
                <input type="text" id="no_hp" name="no_hp" class="form-control" placeholder="0812xxxxxxxx" value="<?= e($_POST['no_hp'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Kata Sandi</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
        </div>

        <div class="form-group">
            <label for="roleSelect" class="form-label">Peran Keanggotaan</label>
            <select id="roleSelect" name="role" class="form-select">
                <option value="warga" <?= (($_POST['role'] ?? 'warga') === 'warga') ? 'selected' : '' ?>>Warga / UMKM (Peminjam Alat &amp; Pengguna Servis)</option>
                <option value="mitra" <?= (($_POST['role'] ?? '') === 'mitra') ? 'selected' : '' ?>>Mitra Komunitas (Kontributor Alat / Teknisi)</option>
            </select>
        </div>

        <div id="mitraSubroleBox" class="form-group" style="display: none;">
            <div class="form-check">
                <input type="checkbox" id="is_kontributor" name="is_kontributor" value="1" class="form-check-input" <?= !empty($_POST['is_kontributor']) ? 'checked' : '' ?>>
                <label for="is_kontributor" class="form-check-label">Mitra Kontributor Alat</label>
            </div>
            <div class="form-check mb-0">
                <input type="checkbox" id="is_teknisi" name="is_teknisi" value="1" class="form-check-input" <?= !empty($_POST['is_teknisi']) ? 'checked' : '' ?>>
                <label for="is_teknisi" class="form-check-label">Mitra Teknisi Perbaikan</label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mt-1">Buat Akun</button>
    </form>

    <div class="pk-auth-divider" aria-hidden="true"><span>atau</span></div>

    <a href="<?= BASE_URL ?>/auth/google_login.php?from=register" class="btn btn-outline btn-google">
        <?= google_mark_svg(15) ?>
        <span>Daftar dengan Google</span>
    </a>

    <footer class="pk-login-foot">
        <a href="<?= BASE_URL ?>/auth/login.php">Sudah punya akun? Masuk</a>
        <a href="<?= BASE_URL ?>/index.php">Kembali ke beranda</a>
    </footer>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
