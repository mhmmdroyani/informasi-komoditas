<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_role_admin();

$stmt = $pdo->query(
    'SELECT username, action, description, ip_address, created_at
     FROM admin_activity_logs
     ORDER BY id DESC
     LIMIT 300'
);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas - Admin SIGMA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= @filemtime(__DIR__ . '/admin.css') ?: time() ?>">
</head>
<body>
    <?php require 'navbar.php'; ?>
    <main class="admin-main">
        <header class="page-header">
            <div>
                <span class="eyebrow"><span class="dot dot-brand"></span> Audit Sistem</span>
                <h1 class="page-title">Log Aktivitas</h1>
                <p class="page-desc">Menampilkan hingga 300 aktivitas admin terbaru.</p>
            </div>
        </header>
        <section class="card activity-log-card" aria-label="Daftar aktivitas">
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Aktivitas</th>
                            <th>Detail</th>
                            <th>Alamat IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($activities === []): ?>
                            <tr><td colspan="5" class="activity-log-empty">Belum ada aktivitas tercatat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($activity['created_at']))) ?></td>
                                    <td><?= htmlspecialchars($activity['username']) ?></td>
                                    <td><span class="badge badge-neutral"><?= htmlspecialchars($activity['action']) ?></span></td>
                                    <td><?= htmlspecialchars($activity['description']) ?></td>
                                    <td><?= htmlspecialchars($activity['ip_address'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
