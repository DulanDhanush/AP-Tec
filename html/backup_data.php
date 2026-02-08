<?php
declare(strict_types=1);

require_once __DIR__ . "/../php/auth.php";

// ✅ Only admin can access (use lowercase, array)
$u = require_login(["admin"]);

// ✅ prefer full_name for display, fallback to username
$displayName = (string)($u["full_name"] ?? "");
if ($displayName === "") {
  $displayName = (string)($u["username"] ?? "User");
}

$role = (string)($u["role"] ?? "admin");
$roleLabel = ucfirst(strtolower($role));
$avatarLetter = strtoupper(mb_substr(trim($displayName), 0, 1, "UTF-8"));
if ($avatarLetter === "") $avatarLetter = "U";
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Data Backup & Restore | AP Tec</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />

    <style>
      .upload-zone {
        border: 2px dashed var(--border-glass);
        border-radius: 12px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: 0.3s;
        background: rgba(2, 12, 27, 0.3);
      }
      .upload-zone:hover {
        border-color: var(--primary);
        background: rgba(100, 255, 218, 0.05);
      }
      .progress-bar-container {
        width: 100%;
        height: 6px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
        margin-top: 15px;
        overflow: hidden;
      }
      .progress-bar {
        height: 100%;
        width: 75%;
        background: var(--primary);
        box-shadow: 0 0 10px var(--primary);
      }
    </style>
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
            <a href="system_logs.php" class="nav-link">
              <i class="fa-solid fa-shield-halved"></i> System Logs
            </a>
          </li>
          <li class="nav-item">
            <a href="notifications.php" class="nav-link">
              <i class="fa-solid fa-bell"></i> Notifications
            </a>
          </li>
          <li class="nav-item">
            <a href="backup_data.php" class="nav-link active">
              <i class="fa-solid fa-database"></i> Backup / Data
            </a>
          </li>
          <li class="nav-item" style="margin-top: auto">
            <a href="../index.html" class="nav-link logout-btn" style="color: #e74c3c">
              <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
          </li>
        </ul>
      </aside>

      <main class="main-content">
        <header class="top-bar">
          <div class="page-title">
            <h1>Data Management</h1>
            <p style="color: var(--secondary)">
              Secure database backups and disaster recovery.
            </p>
          </div>

          <!-- ✅ Dynamic logged-in user -->
          <div class="user-profile">
            <div>
              <span id="uiUserName"><?= htmlspecialchars($displayName) ?></span><br />
              <small id="uiUserRole" style="color: var(--secondary)">
                <?= htmlspecialchars($roleLabel) ?>
              </small>
            </div>
            <div class="user-avatar" id="uiUserAvatar">
              <?= htmlspecialchars($avatarLetter) ?>
            </div>
          </div>
        </header>

        <div class="dashboard-grid">
          <div class="wide-widget" style="height: auto; min-height: 250px">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
              <div>
                <h3 style="color:white; font-size:1.2rem">System Backup</h3>
                <p style="color:var(--secondary); font-size:0.9rem">
                  Create a full SQL dump of the current database.
                </p>
              </div>
              <span id="backupStatus" class="status-badge status-active">Secure</span>
            </div>

            <div style="margin-bottom:30px">
              <p style="font-size:0.85rem; color:var(--secondary); margin-bottom:5px;">
                Last Successful Backup
              </p>
              <h2 id="lastBackupText" style="color:var(--primary); font-family:'Courier New', monospace;">
                —
              </h2>
            </div>

            <button id="btnCreateBackup" class="btn btn-primary" style="width: 100%">
              <i class="fa-solid fa-cloud-arrow-down"></i> Create New Backup
            </button>

            <div style="margin-top:20px; font-size:0.8rem; color:var(--secondary);">
              <i class="fa-solid fa-hard-drive"></i> Storage Usage: 450MB / 5GB
              <div class="progress-bar-container">
                <div class="progress-bar" style="width: 15%"></div>
              </div>
            </div>
          </div>

          <div class="wide-widget" style="height: auto; min-height: 250px">
            <h3 style="color:white; font-size:1.2rem; margin-bottom:5px">Restore Data</h3>
            <p style="color:var(--secondary); font-size:0.9rem; margin-bottom:20px;">
              Import a .sql file to restore system state.
            </p>

            <input type="file" id="fileRestore" style="display:none" accept=".sql,.zip" />

            <div class="upload-zone">
              <i class="fa-solid fa-cloud-arrow-up" style="font-size:2rem; color:var(--secondary); margin-bottom:10px;"></i>
              <h3 style="color:white; font-size:1rem">Click to Upload</h3>
              <p style="color:var(--secondary); font-size:0.8rem">
                or drag and drop .sql files here
              </p>
            </div>

            <div style="margin-top: 20px; text-align: right">
              <button id="btnRestore" class="btn btn-danger" style="padding: 10px 20px">
                <i class="fa-solid fa-rotate-left"></i> Restore System
              </button>
            </div>
          </div>
        </div>

        <div class="settings-group">
          <h3 style="color: var(--text-white); margin-bottom: 20px">
            Automation Settings
          </h3>

          <div class="setting-item">
            <div>
              <h4 style="color: white; font-size: 1rem">Automated Daily Backups</h4>
              <p style="color: var(--secondary); font-size: 0.85rem">
                System will auto-backup at 00:00 server time.
              </p>
            </div>
            <label class="switch">
              <input type="checkbox" checked />
              <span class="slider"></span>
            </label>
          </div>

          <div class="setting-item">
            <div>
              <h4 style="color: white; font-size: 1rem">Upload to Cloud</h4>
              <p style="color: var(--secondary); font-size: 0.85rem">
                Sync backup files to external cloud storage.
              </p>
            </div>
            <label class="switch">
              <input type="checkbox" />
              <span class="slider"></span>
            </label>
          </div>
        </div>

        <div class="wide-widget" style="height:auto; min-height:400px; grid-column: span 4">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="color:white">Backup History</h3>
            <button class="btn-glass"><i class="fa-solid fa-filter"></i> Filter</button>
          </div>

          <table class="table-responsive">
            <thead>
              <tr>
                <th>Backup ID</th>
                <th>Date & Time</th>
                <th>Size</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </main>
    </div>

    <script src="../js/backup.js"></script>
    <script src="../js/dashboard.js"></script>

    <!-- ✅ Force correct user info AFTER dashboard.js -->
    <script>
      (function () {
        const name = <?= json_encode($displayName) ?>;
        const role = <?= json_encode($roleLabel) ?>;

        const n = document.getElementById("uiUserName");
        const r = document.getElementById("uiUserRole");
        const a = document.getElementById("uiUserAvatar");

        if (n) n.textContent = name;
        if (r) r.textContent = role;
        if (a) a.textContent = name ? name.trim().charAt(0).toUpperCase() : "U";
      })();
    </script>
  </body>
</html>