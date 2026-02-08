<?php
declare(strict_types=1);

require_once __DIR__ . "/../php/auth.php";
require_once __DIR__ . "/../php/db.php";

$user = require_login(["Admin"]); // ✅ your DB role enum is Admin/Owner/Employee/Customer

require_once __DIR__ . "/../php/logger.php";
log_event($pdo, "INFO", "Dashboard", "Admin opened dashboard", (int)$user["user_id"]);

// ✅ Prevent browser cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// ---------- Active Users ----------
try {
    $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='Active'")->fetchColumn();
} catch (Throwable $e) {
    $activeUsers = 0;
}

// ---------- Pending Approvals ----------
try {
    $pendingApprovals = (int)$pdo->query("SELECT COUNT(*) FROM approvals WHERE status='Pending'")->fetchColumn();
} catch (Throwable $e) {
    $pendingApprovals = 0;
}

// ---------- Security Alerts (WARNING + ERROR last 7 days) ----------
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM system_logs
        WHERE level IN ('WARNING','ERROR')
          AND created_at >= (NOW() - INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $securityAlerts = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $securityAlerts = 0;
}

// ---------- Server Uptime (fallback method: errors/total logs last 24h) ----------
try {
    $totalStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM system_logs
        WHERE created_at >= (NOW() - INTERVAL 1 DAY)
    ");
    $totalStmt->execute();
    $totalLogs24h = (int)$totalStmt->fetchColumn();

    $errStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM system_logs
        WHERE level = 'ERROR'
          AND created_at >= (NOW() - INTERVAL 1 DAY)
    ");
    $errStmt->execute();
    $errors24h = (int)$errStmt->fetchColumn();

    if ($totalLogs24h <= 0) {
        $serverUptime = "100%";
    } else {
        $uptime = (1 - ($errors24h / max(1, $totalLogs24h))) * 100;
        $uptime = max(0, min(100, $uptime));
        $serverUptime = number_format($uptime, 0) . "%";
    }
} catch (Throwable $e) {
    $serverUptime = "0%";
}

// ---------- Recent system logs (REAL schema: includes module + ip) ----------
try {
    $stmt = $pdo->query("
        SELECT 
            sl.created_at,
            COALESCE(u.username, 'System') AS username,
            sl.message,
            sl.level,
            sl.module,
            sl.ip_address
        FROM system_logs sl
        LEFT JOIN users u ON u.user_id = sl.user_id
        ORDER BY sl.created_at DESC
        LIMIT 8
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $logs = [];
}

// ✅ Your DB level enum is INFO/WARNING/ERROR (no SUCCESS)
// Map INFO -> Success badge for your UI
function badge_class(string $level): array {
    $level = strtoupper(trim($level));
    if ($level === "ERROR")   return ["status-badge status-pending", "Error"];
    if ($level === "WARNING") return ["status-badge status-pending", "Warning"];
    return ["status-badge status-active", "Success"]; // INFO
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AP Tec</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="dashboard-container">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-layer-group"></i> AP TEC.
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="admin_dashboard.php" class="nav-link active">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="user_management.php" class="nav-link">
                    <i class="fa-solid fa-users"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a href="system_logs.php" class="nav-link">
                    <i class="fa-solid fa-shield-halved"></i> System Logs
                </a>
            </li>
            <li class="nav-item">
                <a href="notifications.php" class="nav-link">
                    <i class="fa-regular fa-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a href="backup_data.html" class="nav-link">
                    <i class="fa-solid fa-database"></i> Backup / Data
                </a>
            </li>
            <li class="nav-item" style="margin-top: auto;">
                <a href="../php/logout.php" class="nav-link logout-btn" style="color: #e74c3c;">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">

        <header class="top-bar">
            <div class="page-title">
                <h1>System Overview</h1>
                <p style="color: var(--secondary); margin-top: 6px;">
                    Welcome back, <?= htmlspecialchars(($user["full_name"] ?? "") ?: "Administrator") ?>.
                </p>
            </div>

            <div class="user-profile">
                <span><?= htmlspecialchars(($user["username"] ?? "") ?: "Admin") ?></span>
                <div class="user-avatar">
                    <?php
                        $name = (($user["full_name"] ?? "") ?: ($user["username"] ?? "") ?: "Admin");
                        $parts = preg_split('/\s+/', trim($name));
                        $first = $parts[0] ?? "A";
                        $second = $parts[1] ?? $first;
                        $initials = strtoupper(substr($first, 0, 1) . substr($second, 0, 1));
                        echo htmlspecialchars($initials);
                    ?>
                </div>
            </div>
        </header>

        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= number_format($activeUsers) ?></h3>
                    <p>Active Users</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3 style="color: #2ecc71;"><?= htmlspecialchars($serverUptime) ?></h3>
                    <p>Server Uptime</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-server"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3 style="color: #f1c40f;"><?= number_format($pendingApprovals) ?></h3>
                    <p>Pending Approvals</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= number_format($securityAlerts) ?></h3>
                    <p>Security Alerts</p>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="wide-widget" style="grid-column: span 4;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <h3 style="color: white;">Recent System Activity</h3>

                    <!-- ✅ View All works now -->
                    <a href="system_logs.php" class="btn"
                       style="padding: 5px 15px; font-size: 0.8rem; border: 1px solid var(--border-glass); color: var(--primary); background: transparent; text-decoration:none;">
                        View All
                    </a>
                </div>

                <table class="table-responsive">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" style="color:#ccc;">No system activity yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $row): ?>
                                <?php
                                    [$cls, $label] = badge_class((string)$row["level"]);
                                    $time = date("M d, Y h:i A", strtotime((string)$row["created_at"]));
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($time) ?></td>
                                    <td><?= htmlspecialchars((string)$row["username"]) ?></td>
                                    <td><?= htmlspecialchars((string)$row["message"]) ?></td>
                                    <td><span class="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($label) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="../js/dashboard.js"></script>
</body>
</html>