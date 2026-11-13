<?php

function log_admin_activity(
    PDO $pdo,
    string $action,
    string $description,
    ?int $adminId = null,
    ?string $username = null
): void {
    if ($adminId === null && isset($_SESSION['admin_id'])) {
        $adminId = (int)$_SESSION['admin_id'];
    }
    if ($username === null) {
        $username = (string)($_SESSION['admin_username'] ?? 'Tamu');
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    if (!is_string($ipAddress) || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
        $ipAddress = null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admin_activity_logs (admin_id, username, action, description, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$adminId, $username, $action, $description, $ipAddress]);
}
