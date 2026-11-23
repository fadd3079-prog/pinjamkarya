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
    header('Location: ' . BASE_URL . '/admin/kelola_user.php');
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
</div>

<div class="pk-pill-tabs pk-filter-bar" role="navigation" aria-label="Filter peran pengguna">
    <a href="<?= BASE_URL ?>/admin/kelola_user.php" class="pk-pill<?= $filter_role === '' ? ' is-active' : '' ?>">Semua</a>
    <a href="<?= BASE_URL ?>/admin/kelola_user.php?role=warga" class="pk-pill<?= $filter_role === 'warga' ? ' is-active' : '' ?>">Warga</a>
    <a href="<?= BASE_URL ?>/admin/kelola_user.php?role=mitra" class="pk-pill<?= $filter_role === 'mitra' ? ' is-active' : '' ?>">Mitra</a>
    <a href="<?= BASE_URL ?>/admin/kelola_user.php?role=admin" class="pk-pill<?= $filter_role === 'admin' ? ' is-active' : '' ?>">Admin</a>
</div>

<section class="pk-table-card" aria-label="Daftar Anggota">
    <div class="pk-table-scroll">
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
                <?php if (mysqli_num_rows($res_users) === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Tidak ada pengguna pada filter ini.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($u = mysqli_fetch_assoc($res_users)): ?>
                        <?php
                        $is_warga = ($u['role'] === 'warga');
                        $is_teknisi_mitra = ($u['role'] === 'mitra' && !empty($u['is_teknisi']));
                        ?>
                        <tr>
                            <td class="pk-nowrap pk-desktop-cell">#<?= (int)$u['id_user'] ?></td>
                            <td class="pk-td-head">
                                <div class="pk-td-head-row">
                                    <div class="pk-td-head-main">
                                        <strong><?= e(format_nama_pengguna((string)$u['nama'])) ?></strong><br>
                                        <span class="pk-td-sub"><?= e($u['email']) ?> &bull; <?= e($u['no_hp']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Alamat" class="<?= is_kontak_kosong($u['alamat']) ? 'is-empty-mobile' : '' ?>"><?= e($u['alamat']) ?></td>
                            <td data-label="Peran">
                                <div class="pk-td-val">
                                    <?= render_role_badge($u) ?>
                                </div>
                            </td>
                            <td data-label="Skor" class="pk-nowrap<?= $is_warga ? '' : ' is-empty-mobile' ?>">
                                <?php if ($is_warga): ?>
                                    <strong class="<?= (int)$u['skor_kredibilitas'] < 70 ? 'text-danger' : 'text-secondary' ?>">
                                        <?= (int)$u['skor_kredibilitas'] ?> Poin
                                    </strong>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Teknisi" class="<?= $is_teknisi_mitra ? '' : 'is-empty-mobile' ?>">
                                <?php if ($is_teknisi_mitra): ?>
                                    <?php if (!empty($u['is_verified_teknisi'])): ?>
                                        <span class="pk-badge pk-badge--success">Terverifikasi</span>
                                    <?php else: ?>
                                        <span class="pk-badge pk-badge--warning">Pending</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="pk-td-actions<?= $is_teknisi_mitra ? '' : ' is-empty' ?>">
                                <?php if ($is_teknisi_mitra): ?>
                                    <form method="post" action="<?= BASE_URL ?>/admin/kelola_user.php" style="display:inline;" data-confirm="<?= empty($u['is_verified_teknisi']) ? 'Verifikasi ' . e(format_nama_pengguna((string)$u['nama'])) . ' sebagai teknisi?' : 'Cabut status verifikasi teknisi ' . e(format_nama_pengguna((string)$u['nama'])) . '?' ?>" data-confirm-title="<?= empty($u['is_verified_teknisi']) ? 'Verifikasi Teknisi' : 'Cabut Verifikasi' ?>" data-confirm-btn="<?= empty($u['is_verified_teknisi']) ? 'Verifikasi' : 'Cabut' ?>" data-confirm-tone="<?= empty($u['is_verified_teknisi']) ? 'ok' : 'danger' ?>">
                                        <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                        <?php if (empty($u['is_verified_teknisi'])): ?>
                                            <input type="hidden" name="action" value="verify_teknisi">
                                            <button type="submit" class="btn btn-secondary btn-icon" title="Verifikasi Teknisi" aria-label="Verifikasi <?= e($u['nama']) ?>">
                                                <?= svg_icon('check-circle', 16) ?>
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="revoke_teknisi">
                                            <button type="submit" class="btn btn-danger btn-icon" title="Cabut Verifikasi" aria-label="Cabut verifikasi <?= e($u['nama']) ?>">
                                                <?= svg_icon('x', 16) ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
