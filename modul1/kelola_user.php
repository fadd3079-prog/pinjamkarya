<?php
require_once dirname(__DIR__) . '/config/app.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id_user'])) {
    $target_id = (int)$_POST['id_user'];
    $action = $_POST['action'];

    if ($action === 'verify_teknisi') {
        $stmt = mysqli_prepare($conn, "UPDATE users SET is_verified_teknisi = 1 WHERE id_user = ? AND role = 'mitra' AND is_teknisi = 1");
        mysqli_stmt_bind_param($stmt, 'i', $target_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'Status teknisi mitra berhasil diverifikasi.');
    } elseif ($action === 'revoke_teknisi') {
        $stmt = mysqli_prepare($conn, "UPDATE users SET is_verified_teknisi = 0 WHERE id_user = ? AND role = 'mitra' AND is_teknisi = 1");
        mysqli_stmt_bind_param($stmt, 'i', $target_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('warning', 'Verifikasi teknisi mitra telah dicabut.');
    }
    header('Location: ' . BASE_URL . '/modul1/kelola_user.php');
    exit;
}

$filter_role = $_GET['role'] ?? '';
if (in_array($filter_role, ['admin', 'mitra', 'warga'], true)) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE role = ? ORDER BY id_user ASC');
    mysqli_stmt_bind_param($stmt, 's', $filter_role);
    mysqli_stmt_execute($stmt);
    $res_users = mysqli_stmt_get_result($stmt);
} else {
    $res_users = mysqli_query($conn, 'SELECT * FROM users ORDER BY id_user ASC');
}

$page_title = 'Kelola Pengguna & Teknisi';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="pk-page-header">
    <h1>Kelola Pengguna &amp; Teknisi</h1>
    <div class="btn-group-wrap">
        <a href="<?= BASE_URL ?>/modul1/kelola_user.php" class="btn <?= $filter_role === '' ? 'btn-primary' : 'btn-muted' ?>">Semua</a>
        <a href="<?= BASE_URL ?>/modul1/kelola_user.php?role=warga" class="btn <?= $filter_role === 'warga' ? 'btn-primary' : 'btn-muted' ?>">Warga</a>
        <a href="<?= BASE_URL ?>/modul1/kelola_user.php?role=mitra" class="btn <?= $filter_role === 'mitra' ? 'btn-primary' : 'btn-muted' ?>">Mitra</a>
        <a href="<?= BASE_URL ?>/modul1/kelola_user.php?role=admin" class="btn <?= $filter_role === 'admin' ? 'btn-primary' : 'btn-muted' ?>">Admin</a>
    </div>
</div>

<section class="pk-card" aria-label="Daftar Anggota">
    <div class="table-responsive">
        <table class="pk-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama &amp; Kontak</th>
                    <th>Alamat</th>
                    <th>Peran</th>
                    <th>Skor</th>
                    <th>Status Teknisi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($u = mysqli_fetch_assoc($res_users)): ?>
                    <tr>
                        <td>#<?= (int)$u['id_user'] ?></td>
                        <td>
                            <strong><?= e($u['nama']) ?></strong><br>
                            <span class="text-muted" style="font-size:0.82rem;"><?= e($u['email']) ?> &bull; <?= e($u['no_hp']) ?></span>
                        </td>
                        <td><?= e($u['alamat']) ?></td>
                        <td>
                            <span class="pk-badge pk-badge--primary"><?= e(strtoupper($u['role'])) ?></span>
                            <?php if ($u['role'] === 'mitra'): ?>
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    <?php if (!empty($u['is_kontributor'])): ?>
                                        <span class="pk-badge pk-badge--muted">Kontributor</span>
                                    <?php endif; ?>
                                    <?php if (!empty($u['is_teknisi'])): ?>
                                        <span class="pk-badge pk-badge--muted">Teknisi</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'warga'): ?>
                                <strong class="<?= (int)$u['skor_kredibilitas'] < 70 ? 'text-danger' : 'text-secondary' ?>">
                                    <?= (int)$u['skor_kredibilitas'] ?> Poin
                                </strong>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'mitra' && !empty($u['is_teknisi'])): ?>
                                <?php if (!empty($u['is_verified_teknisi'])): ?>
                                    <span class="pk-badge pk-badge--success">Terverifikasi</span>
                                <?php else: ?>
                                    <span class="pk-badge pk-badge--warning">Pending</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'mitra' && !empty($u['is_teknisi'])): ?>
                                <form method="post" action="<?= BASE_URL ?>/modul1/kelola_user.php" style="display:inline;">
                                    <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                    <?php if (empty($u['is_verified_teknisi'])): ?>
                                        <input type="hidden" name="action" value="verify_teknisi">
                                        <button type="submit" class="btn btn-secondary">Verifikasi</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="revoke_teknisi">
                                        <button type="submit" class="btn btn-danger">Cabut</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
