<?php
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metode tidak diizinkan.']);
    exit;
}

require_csrf();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesi telah berakhir.']);
    exit;
}

$now = time();
echo json_encode([
    'idle_remaining_seconds' => max(
        0,
        ADMIN_IDLE_TIMEOUT - ($now - (int)$_SESSION['admin_last_activity'])
    ),
    'absolute_remaining_seconds' => max(
        0,
        ADMIN_MAX_SESSION_DURATION - ($now - (int)$_SESSION['admin_authenticated_at'])
    ),
]);
