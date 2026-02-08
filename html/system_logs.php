<?php
declare(strict_types=1);

require_once __DIR__ . "/../php/auth.php";
$u = require_login(["Admin","Owner"]);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Safe display name
$displayName = trim((string)(
    ($u["full_name"] ?? "") ?: ($u["name"] ?? "") ?: ($u["username"] ?? "") ?: "User"
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs | AP Tec Security</title>

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
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="user_management.php" class="nav-link">
                    <i class="fa-solid fa-users"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a href="system_logs.php" class="nav-link active">
                    <i class="fa-solid fa-shield-halved"></i> System Logs
                </a>
            </li>
            <li class="nav-item">
                <a href="notifications.php" class="nav-link">
                    <i class="fa-solid fa-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a href="backup_data.php" class="nav-link">
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
                <h1>System Logs</h1>
                <p style="color: var(--secondary);">Monitor security events, errors, and user activities in real-time.</p>
            </div>

            <div class="user-profile">
                <span><?= htmlspecialchars($displayName) ?></span>
                <div class="user-avatar">
                    <?php
                        $parts = preg_split('/\s+/', trim($displayName));
                        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));
                        if (strlen($initials) < 2) $initials = strtoupper(substr($displayName, 0, 2));
                        echo htmlspecialchars($initials);
                    ?>
                </div>
            </div>
        </header>

        <div class="filter-bar">
            <div style="display: flex; gap: 15px; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px; color: var(--secondary); font-size: 0.9rem;">
                    <i class="fa-regular fa-calendar"></i>
                    <input type="date" class="date-input" id="dateFrom">
                    <span>to</span>
                    <input type="date" class="date-input" id="dateTo">
                </div>

                <select id="levelFilter" class="form-control" style="width: 140px; padding: 10px; background: rgba(2, 12, 27, 0.5); color: white; border: 1px solid var(--border-glass);">
                    <option value="ALL">All Levels</option>
                    <option value="INFO">INFO</option>
                    <option value="WARNING">WARNING</option>
                    <option value="ERROR">CRITICAL</option>
                </select>
            </div>

            <div class="action-buttons">
                <div class="search-group">
                    <i class="fa-solid fa-magnifying-glass" style="color: var(--secondary);"></i>
                    <input type="text" id="logSearch" placeholder="Search Log ID or User...">
                </div>

                <button id="btnPDF" class="btn btn-primary" style="padding: 10px 15px;" onclick="window.open('../php/generate_pdf.php', '_blank')">
                    <i class="fa-solid fa-file-pdf"></i> PDF Report
                </button>
            </div>
        </div>

        <div class="wide-widget" style="height: auto; min-height: 600px;">
            <table class="table-responsive">
                <thead>
                    <tr>
                        <th width="15%">Timestamp</th>
                        <th width="10%">Level</th>
                        <th width="15%">Module</th>
                        <th width="35%">Activity / Message</th>
                        <th width="15%">User / IP</th>
                        <th width="10%">Action</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <tr><td colspan="6" style="color:#ccc;">Loading logs...</td></tr>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script src="../js/system_logs.js?v=2"></script>
<script src="../js/dashboard.js"></script>
</body>
</html>