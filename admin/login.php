<?php
// admin/login.php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
require_once __DIR__ . '/../includes/admin_activity.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && (int)($user['is_active'] ?? 1) === 1 && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_nama'] = $user['nama'];
            $_SESSION['admin_role'] = $user['role'] ?? 'admin';
            log_admin_activity($pdo, 'login', 'Berhasil masuk ke panel admin.');
            
            header('Location: index.php');
            exit;
        } else {
            log_admin_activity($pdo, 'login_gagal', 'Percobaan login gagal.');
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Karantina Kalimantan Selatan</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
    <style>
        body.login-body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: var(--bg); }
        .login-wrap { max-width: 420px; width: 100%; }

        .login-card { background: var(--surface); border-radius: var(--radius-lg); box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18); overflow: hidden; }

        .login-hero { background: var(--brand-dark); padding: 32px 28px; text-align: center; }
        .login-logo-badge { display: inline-flex; align-items: center; justify-content: center; padding: 12px; border-radius: 16px; background: rgba(255, 255, 255, 0.1); margin-bottom: 16px; }
        .login-logo-badge img { height: 52px; width: auto; }
        .login-hero h1 { font-size: 19px; font-weight: 800; color: #ffffff; margin: 0; }
        .login-hero p { font-size: 12px; color: rgba(255, 255, 255, 0.72); margin: 6px 0 0; }
        .login-hero .brand-line { color: #eab308; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.6px; margin: 6px 0 0; }

        .login-form-section { padding: 28px; }
        .login-field { margin-bottom: 20px; }
        .login-field .field-label { display: block; margin-bottom: 7px; }
        .login-field .field-input { padding: 11px 14px; font-size: 14px; }
        .password-field-wrap { position: relative; }
        .password-field-wrap input { padding-right: 44px; }
        .password-field-wrap input::-ms-reveal,
        .password-field-wrap input::-ms-clear { display: none; }
        .password-toggle-btn { position: absolute; top: 50%; right: 4px; transform: translateY(-50%); background: none; border: none; padding: 8px; display: flex; align-items: center; justify-content: center; color: var(--ink-muted); cursor: pointer; }
        .password-toggle-btn:hover { color: var(--ink); }
        .login-back-link { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 20px; font-size: 12px; color: var(--ink-muted); text-decoration: none; }
        .login-back-link:hover { color: var(--ink); }
    </style>
</head>
<body class="login-body">
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-hero">
                <div class="login-logo-badge">
                    <img src="../embed/logo-barantin.png" alt="Logo Barantin">
                </div>
                <h1>Badan Karantina Indonesia</h1>
                <p class="brand-line">Balai Karantina Kalimantan Selatan</p>
                <p>Sistem Pengelolaan &amp; Publikasi Infografis</p>
            </div>

            <div class="login-form-section">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" style="margin-bottom: 18px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="login-field">
                        <label class="field-label">Username</label>
                        <input type="text" name="username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="field-input">
                    </div>

                    <div class="login-field">
                        <label class="field-label">Password</label>
                        <div class="password-field-wrap">
                            <input type="password" id="passwordInput" name="password" required class="field-input">
                            <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Tampilkan password">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" id="eyeIcon"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 11px;">
                        <span>Masuk ke Panel Admin</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>
            </div>
        </div>

        <a href="../index.php" class="login-back-link">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <span>Kembali ke Halaman Publik</span>
        </a>
    </div>

    <script>
    document.getElementById('togglePasswordBtn').addEventListener('click', function () {
        const input = document.getElementById('passwordInput');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        this.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
    });
    </script>
</body>
</html>
