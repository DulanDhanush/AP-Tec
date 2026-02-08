<?php
declare(strict_types=1);

date_default_timezone_set("Asia/Colombo");

// ✅ protect page
require_once __DIR__ . "/../php/auth.php";
$u = require_login(["Admin"]);

// ✅ stop browser back cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// ✅ display name logic (Admin shows username, others show full_name)
$role = (string)($u["role"] ?? "");
$displayName = (string)($u["full_name"] ?? "");

if (strcasecmp($role, "Admin") === 0) {
    $displayName = (string)($u["username"] ?? $displayName);
}
if (trim($displayName) === "") {
    $displayName = (string)($u["username"] ?? "Admin");
}

$parts = preg_split('/\s+/', trim($displayName));
$first = $parts[0] ?? "A";
$second = $parts[1] ?? $first;
$initials = strtoupper(substr($first, 0, 1) . substr($second, 0, 1));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Management | AP Tec System</title>

    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
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
            <a href="user_management.php" class="nav-link active">
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
            <a href="backup_data.html" class="nav-link">
              <i class="fa-solid fa-database"></i> Backup / Data
            </a>
          </li>
          <li class="nav-item" style="margin-top: auto">
            <a
              href="../php/logout.php"
              class="nav-link logout-btn"
              style="color: #e74c3c"
            >
              <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
          </li>
        </ul>
      </aside>

      <main class="main-content">
        <header class="top-bar">
          <div class="page-title">
            <h1>User Management</h1>
            <p style="color: var(--secondary)">
              Manage access controls for Employees, Owners, and Customers.
            </p>
          </div>

          <div class="user-profile">
            <span><?= htmlspecialchars($displayName) ?></span>
            <div class="user-avatar">
              <?= htmlspecialchars($initials) ?>
            </div>
          </div>
        </header>

        <div class="filter-bar">
          <div class="search-group">
            <i
              class="fa-solid fa-magnifying-glass"
              style="color: var(--secondary)"
            ></i>
            <input
              id="searchInput"
              type="text"
              placeholder="Search by Name, ID or Email..."
            />
          </div>

          <div class="action-buttons">
            <select
              id="roleFilter"
              class="form-control"
              style="
                width: 150px;
                padding: 10px;
                background: rgba(2, 12, 27, 0.5);
                color: white;
                border: 1px solid var(--border-glass);
              "
            >
              <option value="All">All Roles</option>
              <option value="Employee">Employee</option>
              <option value="Customer">Customer</option>
              <option value="Owner">Owner</option>
              <option value="Admin">Admin</option>
            </select>

            <button
              id="btnAddUser"
              class="btn btn-primary"
              style="
                padding: 10px 20px;
                display: flex;
                align-items: center;
                gap: 8px;
              "
            >
              <i class="fa-solid fa-plus"></i> Add New User
            </button>
          </div>
        </div>

        <div class="wide-widget" style="height: auto; min-height: 500px">
          <table class="table-responsive">
            <thead>
              <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Email / Contact</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="usersTbody">
              <tr>
                <td colspan="6" style="color: #ccc">Loading users...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </main>
    </div>

    <div class="modal-overlay" id="userModal">
      <div class="modal-content">
        <i class="fa-solid fa-xmark close-modal"></i>

        <div class="modal-header">
          <h2 id="modalTitle">Add New User</h2>
          <p id="modalSub">Create a new account for system access.</p>
        </div>

        <form id="userForm">
          <input type="hidden" id="inpID" />

          <div class="form-group">
            <label class="form-label" style="color: var(--secondary)"
              >Full Name</label
            >
            <input
              type="text"
              id="inpName"
              class="modal-input"
              placeholder="e.g. Dulan Dhanush"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label" style="color: var(--secondary)"
              >Email Address</label
            >
            <input
              type="email"
              id="inpEmail"
              class="modal-input"
              placeholder="e.g. Dulan@tec.com"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label" style="color: var(--secondary)"
              >System Role</label
            >
            <select id="inpRole" class="modal-input" style="cursor: pointer">
              <option value="Employee">Employee (Technician)</option>
              <option value="Customer">Customer (Client)</option>
              <option value="Owner">Owner (Manager)</option>
              <option value="Admin">Admin</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" style="color: var(--secondary)"
              >Default Password</label
            >
            <input
              type="password"
              id="inpPassword"
              class="modal-input"
              value="Welcome123"
              placeholder="••••••••"
            />
            <small
              style="
                color: var(--secondary);
                font-size: 0.8rem;
                margin-top: 5px;
                display: block;
              "
            >
              * Default password for new users
            </small>
          </div>

          <div style="display: flex; gap: 15px; margin-top: 30px">
            <button
              type="button"
              class="btn btn-cancel"
              style="
                width: 100px;
                background: transparent;
                color: var(--secondary);
                border: 1px solid var(--border-glass);
              "
            >
              Cancel
            </button>
            <button
              type="submit"
              id="btnSave"
              class="btn btn-primary"
              style="flex: 1"
            >
              Create Account
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ✅ cache-bust so browser loads latest JS -->
    <script src="../js/user_mgr.js?v=2"></script>
    <script src="../js/dashboard.js"></script>
  </body>
</html>