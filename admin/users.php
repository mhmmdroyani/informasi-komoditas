<?php
// admin/users.php - Kelola Pengguna (khusus role admin)
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/admin_activity.php';
require_role_admin();

$me = current_admin();
$msg = '';
$error = '';

// ==== Proses Aksi (Tambah / Edit / Hapus) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($nama)) {
            $error = 'Username dan Nama wajib diisi!';
        } else {
            if ($id > 0) {
                // Update user (password opsional, hanya diubah jika diisi)
                if (!empty($password)) {
                    $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, nama = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $nama, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, nama = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $nama, $role, $id]);
                }
                log_admin_activity($pdo, 'edit_pengguna', sprintf('Memperbarui akun pengguna %s (ID %d).', $username, $id));
                $msg = 'Data pengguna berhasil diperbarui.';
            } else {
                if (empty($password)) {
                    $error = 'Password wajib diisi untuk pengguna baru!';
                } else {
                    $stmtCheck = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? LIMIT 1");
                    $stmtCheck->execute([$username]);
                    if ($stmtCheck->fetch()) {
                        $error = 'Username sudah digunakan, silakan pilih yang lain.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, nama, role) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $nama, $role]);
                        log_admin_activity($pdo, 'tambah_pengguna', sprintf('Menambahkan akun pengguna %s.', $username));
                        $msg = 'Pengguna baru berhasil ditambahkan.';
                    }
                }
            }
        }
    } elseif ($formAction === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id !== (int)$me['id']) {
            $stmt = $pdo->prepare('SELECT username, is_active FROM admin_users WHERE id = ?');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if ($target) {
                $pdo->prepare("UPDATE admin_users SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
                log_admin_activity($pdo, 'ubah_status_pengguna', sprintf('Mengubah status akun %s.', $target['username']));
                $msg = 'Status pengguna berhasil diubah.';
            } else {
                $error = 'Pengguna tidak ditemukan.';
            }
        } else {
            $error = 'Anda tidak dapat menonaktifkan akun sendiri.';
        }
    } elseif ($formAction === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id !== (int)$me['id']) {
            $stmt = $pdo->prepare('SELECT username FROM admin_users WHERE id = ?');
            $stmt->execute([$id]);
            $targetUsername = $stmt->fetchColumn();
            if ($targetUsername !== false) {
                $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
                log_admin_activity($pdo, 'hapus_pengguna', sprintf('Menghapus akun pengguna %s.', $targetUsername));
                $msg = 'Pengguna berhasil dihapus.';
            } else {
                $error = 'Pengguna tidak ditemukan.';
            }
        } else {
            $error = 'Anda tidak dapat menghapus akun sendiri.';
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editUser = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}

$users = $pdo->query("SELECT * FROM admin_users ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Admin Karantina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
</head>
<body>
    <?php require 'navbar.php'; ?>

    <div class="admin-main">
        <div class="page-header">
            <div>
                <span class="eyebrow"><span class="dot dot-brand"></span> Khusus Admin</span>
                <h1 class="page-title">Kelola Pengguna</h1>
                <p class="page-desc">Tambah, ubah, nonaktifkan, atau hapus akun pengguna sistem. Hanya role Admin yang dapat mengakses halaman ini.</p>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success" style="margin-bottom: 16px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span><?= htmlspecialchars($msg) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 16px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="users-layout">
            <!-- Form Tambah / Edit -->
            <div class="card card-pad">
                <h2 style="font-size: 14px; font-weight: 700; color: var(--ink); margin-bottom: 14px;">
                    <?= $editUser ? 'Edit Pengguna: ' . htmlspecialchars($editUser['username']) : 'Tambah Pengguna Baru' ?>
                </h2>
                <form method="POST" action="users.php" data-confirm="Simpan data pengguna ini?" style="display: flex; flex-direction: column; gap: 14px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="save">
                    <input type="hidden" name="id" value="<?= $editUser['id'] ?? 0 ?>">

                    <div>
                        <label class="field-label">Username <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="username" required value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Nama Lengkap <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="nama" required value="<?= htmlspecialchars($editUser['nama'] ?? '') ?>" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Hak Akses (Role)</label>
                        <select name="role" class="field-input">
                            <option value="admin" <?= (($editUser['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin (akses penuh)</option>
                            <option value="user" <?= (($editUser['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>User (terbatas)</option>
                        </select>
                        <p class="field-hint">User biasa tidak dapat mengakses Kelola Pengguna.</p>
                    </div>
                    <div>
                        <label class="field-label" for="userPassword">Password baru <?= $editUser ? '(kosongkan jika tidak diubah)' : '<span style="color: var(--danger);">*</span>' ?></label>
                        <div class="password-field-wrap">
                            <input type="password" id="userPassword" name="password" class="field-input" autocomplete="new-password">
                            <button type="button" class="password-toggle-btn" id="toggleUserPassword" aria-label="Tampilkan password baru">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                        <?php if ($editUser): ?>
                            <p class="field-hint">Password tersimpan aman sebagai hash, jadi password lama tidak dapat ditampilkan. Isi hanya jika ingin menggantinya.</p>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 8px; border-top: 1px solid var(--line);">
                        <?php if ($editUser): ?>
                            <a href="users.php" style="font-size: 12px; color: var(--ink-muted); text-decoration: none;">Batal Edit</a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span><?= $editUser ? 'Simpan Perubahan' : 'Tambah Pengguna' ?></span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Daftar Pengguna -->
            <div class="card" style="overflow: hidden;">
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama</th>
                                <th style="text-align: center;">Role</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['nama']) ?></td>
                                    <td style="text-align: center;">
                                        <span class="badge <?= $u['role'] === 'admin' ? 'badge-brand' : 'badge-neutral' ?>"><?= ucfirst($u['role']) ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ((int)$u['is_active'] === 1): ?>
                                            <span class="badge badge-brand">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge badge-neutral">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <a href="users.php?edit=<?= $u['id'] ?>" style="color: var(--brand); display: inline-flex; padding: 4px;" title="Edit">
                                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                                            <form method="POST" action="users.php" style="display: inline;" data-confirm="Ubah status aktif/nonaktif pengguna ini?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="form_action" value="toggle_active">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" style="background: none; border: none; color: var(--ink-muted); display: inline-flex; padding: 4px; cursor: pointer;" title="Aktifkan/Nonaktifkan">
                                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.36 6.64a9 9 0 11-12.73 0M12 3v9"></path></svg>
                                                </button>
                                            </form>
                                            <form method="POST" action="users.php" style="display: inline;" data-confirm="Hapus pengguna ini secara permanen?" data-confirm-tone="danger">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="form_action" value="delete">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" style="background: none; border: none; color: var(--danger); display: inline-flex; padding: 4px; cursor: pointer;" title="Hapus">
                                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size: 11px; color: var(--ink-muted); margin-left: 4px;">(Anda)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('toggleUserPassword').addEventListener('click', function() {
        const input = document.getElementById('userPassword');
        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        this.setAttribute('aria-label', showPassword ? 'Sembunyikan password baru' : 'Tampilkan password baru');
    });
    </script>
</body>
</html>
