<?php
declare(strict_types=1);

require_once __DIR__ . "/../php/auth.php";
$u = require_login(["Admin","Owner"]);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// ✅ FIX: unified display name (supports full_name OR name OR username)
$displayName = trim((string)(
  ($u["full_name"] ?? "") ?: ($u["name"] ?? "") ?: ($u["username"] ?? "") ?: "Admin"
));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Notifications | AP Tec</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />
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
              <i class="fa-solid fa-users"></i> User Mgmt
            </a>
          </li>

          <li class="nav-item">
            <a href="system_logs.php" class="nav-link">
              <i class="fa-solid fa-shield-halved"></i> System Logs
            </a>
          </li>

          <li class="nav-item">
            <a href="notifications.php" class="nav-link active">
              <i class="fa-solid fa-bell"></i> Notifications
            </a>
          </li>

          <li class="nav-item">
            <a href="backup_data.php" class="nav-link">
              <i class="fa-solid fa-database"></i> Backup / Data
            </a>
          </li>

          <li class="nav-item" style="margin-top: auto">
            <a href="../php/logout.php" class="nav-link logout-btn" style="color: #e74c3c">
              <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
          </li>
        </ul>
      </aside>

      <main class="main-content">
        <header class="top-bar">
          <div class="page-title">
            <h1>Notification Hub</h1>
            <p style="color: var(--secondary)">Manage your alerts and system preferences.</p>
          </div>

          <div class="user-profile">
            <!-- ✅ FIXED: now will show correct name for any logged-in user -->
            <span><?= htmlspecialchars($displayName) ?></span>
            <div class="user-avatar">
              <?php
                $parts = preg_split('/\s+/', trim($displayName));
                $initials = strtoupper(substr($parts[0] ?? 'A', 0, 1) . substr($parts[1] ?? 'D', 0, 1));
                if (strlen($initials) < 2) $initials = strtoupper(substr($displayName, 0, 2));
                echo htmlspecialchars($initials);
              ?>
            </div>
          </div>
        </header>

        <div class="tab-container">
          <button class="tab-btn active" data-target="section-inbox">
            Inbox
            <span
              id="notifCount"
              style="
                background: var(--primary);
                color: var(--bg-dark);
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 0.7rem;
                margin-left: 5px;
              "
              >0</span
            >
          </button>
        </div>

        <div id="section-inbox" class="tab-section">
          <div style="display: flex; justify-content: flex-end; margin-bottom: 15px">
            <button id="btnClear" class="btn" style="color: var(--secondary); font-size: 0.9rem">
              Mark all as read
            </button>
          </div>

          <!-- ✅ JS will render DB notifications here -->
          <div class="notification-list" id="notifList">
            <div style="color: var(--secondary); padding: 10px;">Loading notifications...</div>
          </div>
        </div>

        <div id="section-settings" class="tab-section" style="display: none">
          <div class="settings-group">
            <h3 style="color: var(--text-white); margin-bottom: 20px">System Alerts</h3>

            <div class="setting-item">
              <div>
                <h4 style="color: white; font-size: 1rem">Low Inventory Warning</h4>
                <p style="color: var(--secondary); font-size: 0.85rem">
                  Notify when stock levels drop below threshold.
                </p>
              </div>
              <label class="switch">
                <input type="checkbox" id="set_low_inventory" />
                <span class="slider"></span>
              </label>
            </div>

            <div class="setting-item">
              <div>
                <h4 style="color: white; font-size: 1rem">Server Downtime</h4>
                <p style="color: var(--secondary); font-size: 0.85rem">
                  Send immediate SMS if database connection fails.
                </p>
              </div>
              <label class="switch">
                <input type="checkbox" id="set_server_downtime" />
                <span class="slider"></span>
              </label>
            </div>
          </div>

          <div class="settings-group">
            <h3 style="color: var(--text-white); margin-bottom: 20px">Email Preferences</h3>

            <div class="setting-item">
              <div>
                <h4 style="color: white; font-size: 1rem">Daily Summary Report</h4>
                <p style="color: var(--secondary); font-size: 0.85rem">
                  Receive a PDF summary of daily transactions.
                </p>
              </div>
              <label class="switch">
                <input type="checkbox" id="set_daily_summary" />
                <span class="slider"></span>
              </label>
            </div>

            <div class="setting-item">
              <div>
                <h4 style="color: white; font-size: 1rem">New User Signups</h4>
                <p style="color: var(--secondary); font-size: 0.85rem">
                  Email admin when a new customer registers.
                </p>
              </div>
              <label class="switch">
                <input type="checkbox" id="set_new_user_signups" />
                <span class="slider"></span>
              </label>
            </div>
          </div>

          <button id="btnSaveSettings" class="btn btn-primary">Save Configuration</button>
        </div>
      </main>
    </div>

    <script src="../js/notifications.js"></script>
    <script src="../js/dashboard.js"></script>
    <script>
document.addEventListener("DOMContentLoaded", async () => {
  const nameSpan = document.querySelector(".user-profile span");
  const avatar = document.querySelector(".user-profile .user-avatar");
  if (!nameSpan || !avatar) return;

  try {
    const res = await fetch("../php/me_api.php", { credentials: "same-origin" });
    const data = await res.json();
    if (!data.ok || !data.user) return;

    const displayName = (data.user.display_name || "").trim();
    if (!displayName) return;

    nameSpan.textContent = displayName;

    const parts = displayName.split(/\s+/).filter(Boolean);
    let initials = ((parts[0] || "U")[0] || "U") + ((parts[1] || "")[0] || "");
    initials = initials.toUpperCase();
    if (initials.length < 2) initials = displayName.slice(0, 2).toUpperCase();

    avatar.textContent = initials;
  } catch (e) {}
});
</script>
  </body>
</html>