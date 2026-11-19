<?php
require_once dirname(__DIR__) . '/config/app.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/modul4/katalog.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        set_flash('warning', 'Mohon isi alamat email dan kata sandi Anda.');
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;
            set_flash('success', 'Selamat datang, ' . $user['nama'] . '.');
            if ($user['role'] === 'admin') {
                header('Location: ' . BASE_URL . '/modul9/dashboard.php');
            } else {
                header('Location: ' . BASE_URL . '/modul4/katalog.php');
            }
            exit;
        } else {
            set_flash('danger', 'Email atau kata sandi tidak sesuai.');
        }
    }
}

$page_title = 'Masuk Akun';
require_once ROOT_PATH . '/includes/header.php';
?>

<div style="max-width: 520px; margin: 12px auto;">
    <section class="pk-card" aria-labelledby="login-heading">
        <div class="pk-card-header">
            <h1 id="login-heading" class="pk-card-title">
                <?= svg_icon('log-in', 20) ?>
                <span>Masuk Akun</span>
            </h1>
        </div>

        <form method="post" action="<?= BASE_URL ?>/modul1/login.php">
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="nama@pinjamkarya.id" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Kata Sandi</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Kata sandi" required autocomplete="current-password">
            </div>

            <div class="btn-group-wrap mt-3">
                <button type="submit" class="btn btn-primary w-100">
                    <?= svg_icon('log-in', 16) ?>
                    <span>Masuk</span>
                </button>
            </div>
        </form>

        <div class="mt-3 text-center">
            <span class="text-muted">Belum punya akun?</span>
            <a href="<?= BASE_URL ?>/modul1/register.php" class="fw-bold">Daftar Akun</a>
        </div>

        <div class="pk-demo-box">
            <div class="d-flex align-center justify-between">
                <strong style="font-size:0.85rem;">Akun Demo</strong>
                <span class="text-muted" style="font-size:0.8rem;">Sandi: <code>password</code></span>
            </div>

            <div class="pk-demo-grid">
                <button type="button" class="pk-demo-btn" data-demo-email="aflah@pinjamkarya.id" data-demo-pass="password">
                    <strong>Aflah (Admin)</strong>
                    <span class="text-muted">aflah@pinjamkarya.id</span>
                </button>
                <button type="button" class="pk-demo-btn" data-demo-email="balqis@pinjamkarya.id" data-demo-pass="password">
                    <strong>Balqis (Mitra &amp; Teknisi)</strong>
                    <span class="text-muted">balqis@pinjamkarya.id</span>
                </button>
                <button type="button" class="pk-demo-btn" data-demo-email="fadhol@pinjamkarya.id" data-demo-pass="password">
                    <strong>Fadhol (Warga &bull; 110)</strong>
                    <span class="text-muted">fadhol@pinjamkarya.id</span>
                </button>
                <button type="button" class="pk-demo-btn" data-demo-email="siti@pinjamkarya.id" data-demo-pass="password">
                    <strong>Siti (Warga &bull; 65)</strong>
                    <span class="text-muted">siti@pinjamkarya.id</span>
                </button>
                <button type="button" class="pk-demo-btn" data-demo-email="budi@pinjamkarya.id" data-demo-pass="password">
                    <strong>Budi (Teknisi Pending)</strong>
                    <span class="text-muted">budi@pinjamkarya.id</span>
                </button>
            </div>
        </div>
    </section>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
