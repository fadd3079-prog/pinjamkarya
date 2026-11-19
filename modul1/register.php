<?php
require_once dirname(__DIR__) . '/config/app.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/modul4/katalog.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $role = ($_POST['role'] ?? 'warga') === 'mitra' ? 'mitra' : 'warga';

    $is_kontributor = ($role === 'mitra' && !empty($_POST['is_kontributor'])) ? 1 : 0;
    $is_teknisi = ($role === 'mitra' && !empty($_POST['is_teknisi'])) ? 1 : 0;
    $is_verified_teknisi = 0;
    $skor_kredibilitas = 100;

    if ($nama === '' || $email === '' || $password === '' || $no_hp === '' || $alamat === '') {
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
            mysqli_stmt_close($stmt);

            set_flash('success', 'Pendaftaran berhasil (Modal awal: 100 Poin Skor Kepercayaan). Silakan masuk.');
            header('Location: ' . BASE_URL . '/modul1/login.php');
            exit;
        }
    }
}

$page_title = 'Daftar Akun';
require_once ROOT_PATH . '/includes/header.php';
?>

<div style="max-width: 600px; margin: 12px auto;">
    <section class="pk-card" aria-labelledby="register-heading">
        <div class="pk-card-header">
            <h1 id="register-heading" class="pk-card-title">
                <?= svg_icon('user', 20) ?>
                <span>Daftar Akun</span>
            </h1>
        </div>

        <form method="post" action="<?= BASE_URL ?>/modul1/register.php">
            <div class="form-group">
                <label for="nama" class="form-label">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control" placeholder="Nama lengkap" value="<?= e($_POST['nama'] ?? '') ?>" required>
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
                <label for="alamat" class="form-label">Alamat</label>
                <textarea id="alamat" name="alamat" class="form-control" placeholder="Alamat domisili atau lokasi usaha" required><?= e($_POST['alamat'] ?? '') ?></textarea>
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

            <div class="btn-group-wrap mt-3">
                <button type="submit" class="btn btn-primary w-100">
                    <?= svg_icon('check-circle', 16) ?>
                    <span>Buat Akun</span>
                </button>
            </div>
        </form>

        <div class="mt-3 text-center">
            <span class="text-muted">Sudah punya akun?</span>
            <a href="<?= BASE_URL ?>/modul1/login.php" class="fw-bold">Masuk</a>
        </div>
    </section>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
