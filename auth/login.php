<?php
require_once dirname(__DIR__) . '/config/app.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
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
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            set_flash('danger', 'Email atau kata sandi tidak sesuai.');
        }
    }
}

$page_title = 'Masuk Akun';
$minimal_layout = true;
require_once ROOT_PATH . '/includes/header.php';
?>

<section class="pk-login" aria-labelledby="login-heading">
    <header class="pk-login-head">
        <a class="pk-login-brand" href="<?= BASE_URL ?>/index.php">
            <span class="pk-login-brand-icon"><?= brand_logo('light', 16) ?></span>
            <span>PinjamKarya</span>
            <span class="pk-login-sep" aria-hidden="true">/</span>
            <span class="pk-login-scope">Masuk</span>
        </a>
        <h1 id="login-heading" class="pk-login-title">Masuk</h1>
    </header>

    <?= get_flash() ?>

    <form method="post" action="<?= BASE_URL ?>/auth/login.php">
        <div class="form-group">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="nama@pinjamkarya.id" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email" autofocus>
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Kata Sandi</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Kata sandi" required autocomplete="current-password">
        </div>

        <button type="submit" id="loginSubmitBtn" class="btn btn-primary w-100 mt-1">Masuk</button>
    </form>

    <div class="pk-auth-divider" aria-hidden="true"><span>atau</span></div>

    <a href="<?= BASE_URL ?>/auth/google_login.php?from=login" class="btn btn-outline btn-google">
        <?= google_mark_svg(15) ?>
        <span>Masuk dengan Google</span>
    </a>

    <details class="pk-demo-details" id="pkDemoDetails"<?= isset($_GET['demo']) ? ' open' : '' ?>>
        <summary>
            <span>Isi Cepat Akun Demo</span>
            <span class="pk-demo-summary-meta">
                <span>Pilih peran</span>
                <span class="pk-demo-chevron" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </span>
        </summary>
        <div class="pk-demo-box">
            <div class="pk-demo-grid">
                <button type="button" class="pk-demo-btn" data-demo-email="aflah@pinjamkarya.id" data-demo-pass="password">
                    <span class="pk-demo-identity">
                        <strong>Aflah</strong>
                        <span>aflah@pinjamkarya.id</span>
                    </span>
                    <span class="pk-demo-role">Admin</span>
                </button>
                <button type="button" class="pk-demo-btn" data-demo-email="balqis@pinjamkarya.id" data-demo-pass="password">
                    <span class="pk-demo-identity">
                        <strong>Balqis</strong>
                        <span>balqis@pinjamkarya.id</span>
                    </span>
                    <span class="pk-demo-role">Mitra &amp; Teknisi</span>
                </button>
                <button type="button" class="pk-demo-btn" data-demo-email="fadhol@pinjamkarya.id" data-demo-pass="password">
                    <span class="pk-demo-identity">
                        <strong>Fadhol</strong>
                        <span>fadhol@pinjamkarya.id</span>
                    </span>
                    <span class="pk-demo-role">Warga &bull; 110</span>
                </button>
                <button type="button" class="pk-demo-btn" data-demo-email="siti@pinjamkarya.id" data-demo-pass="password">
                    <span class="pk-demo-identity">
                        <strong>Siti</strong>
                        <span>siti@pinjamkarya.id</span>
                    </span>
                    <span class="pk-demo-role">Warga &bull; 65</span>
                </button>
                <button type="button" class="pk-demo-btn" data-demo-email="budi@pinjamkarya.id" data-demo-pass="password">
                    <span class="pk-demo-identity">
                        <strong>Budi</strong>
                        <span>budi@pinjamkarya.id</span>
                    </span>
                    <span class="pk-demo-role">Teknisi Baru</span>
                </button>
            </div>
        </div>
    </details>

    <footer class="pk-login-foot">
        <a href="<?= BASE_URL ?>/auth/register.php">Buat akun baru</a>
        <a href="<?= BASE_URL ?>/index.php">Kembali ke beranda</a>
    </footer>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
