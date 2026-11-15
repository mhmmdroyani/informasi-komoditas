<?php
// config/auth.php
const ADMIN_IDLE_TIMEOUT = 1800;
const ADMIN_MAX_SESSION_DURATION = 28800;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }

    $now = time();
    if (!isset($_SESSION['admin_authenticated_at'])) {
        $_SESSION['admin_authenticated_at'] = $now;
    }
    if (!isset($_SESSION['admin_last_activity'])) {
        $_SESSION['admin_last_activity'] = $now;
    }

    if (
        $now - (int)$_SESSION['admin_last_activity'] >= ADMIN_IDLE_TIMEOUT
        || $now - (int)$_SESSION['admin_authenticated_at'] >= ADMIN_MAX_SESSION_DURATION
    ) {
        unset(
            $_SESSION['admin_logged_in'],
            $_SESSION['admin_id'],
            $_SESSION['admin_username'],
            $_SESSION['admin_nama'],
            $_SESSION['admin_role'],
            $_SESSION['admin_authenticated_at'],
            $_SESSION['admin_last_activity']
        );
        return false;
    }

    $_SESSION['admin_last_activity'] = $now;
    return true;
}

function require_admin() {
    if (!is_logged_in()) {
        header('Location: login.php?expired=1');
        exit;
    }
}

function current_admin() {
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? 'Admin',
        'nama' => $_SESSION['admin_nama'] ?? 'Administrator',
        'role' => $_SESSION['admin_role'] ?? 'admin',
    ];
}

function is_admin_role() {
    return ($_SESSION['admin_role'] ?? 'admin') === 'admin';
}

function require_role_admin() {
    require_admin();
    if (!is_admin_role()) {
        header('Location: index.php?error=' . urlencode('Anda tidak memiliki hak akses ke halaman ini.'));
        exit;
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function require_csrf() {
    $submitted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!$stored || !$submitted || !hash_equals($stored, $submitted)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}
