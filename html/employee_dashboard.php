<?php
declare(strict_types=1);

require_once __DIR__ . "/../php/auth.php";
$u = require_login(["Employee"]); // IMPORTANT: DB role is "Employee"
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Technician Workspace | AP Tec</title>

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
        <a href="employee_dashboard.php" class="nav-link active">
          <i class="fa-solid fa-clipboard-list"></i> My Tasks
        </a>
      </li>
      <li class="nav-item">
        <a href="invoice_generator.html" class="nav-link">
          <i class="fa-solid fa-file-invoice"></i> Generate Invoice
        </a>
      </li>
      <li class="nav-item">
        <a href="schedule.php" class="nav-link">
          <i class="fa-solid fa-calendar-check"></i> Schedule
        </a>
      </li>
      <li class="nav-item">
        <a href="messages.php" class="nav-link">
          <i class="fa-solid fa-comments"></i>
          Messages
          <span id="msgBadge" class="tab-badge" style="background:var(--primary); color:black; display:none;">0</span>
        </a>
      </li>

      <li class="nav-item" style="margin-top:auto;">
        <a href="../index.html" class="nav-link logout-btn" style="color:#e74c3c;">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
      </li>
    </ul>
  </aside>

  <main class="main-content">

    <header class="top-bar">
      <div class="page-title">
        <h1>Technician Workspace</h1>
        <p style="color: var(--secondary);">Manage your assignments and update field status.</p>
      </div>

      <div style="display:flex; gap:20px; align-items:center;">
        <button id="availBtn" class="availability-toggle" style="cursor:pointer;">
          <div class="status-dot"></div>
          <span id="statusText" style="font-size:0.85rem; color:#2ecc71; font-weight:600;">Available</span>
        </button>

        <span id="userName"><?= htmlspecialchars($u["full_name"] ?: $u["username"]) ?></span>
      </div>
    </header>

    <div class="dashboard-grid">

      <div style="grid-column: span 3;">

        <div class="tab-container">
          <button class="tab-btn active" data-filter="all">All Tasks</button>
          <button class="tab-btn" data-filter="pending">Pending</button>
          <button class="tab-btn" data-filter="progress">In Progress</button>
          <button class="tab-btn" data-filter="waiting_parts">Waiting for Parts</button>
          <button class="tab-btn" data-filter="completed">Completed</button>
        </div>

        <div id="reqError" style="color:#e74c3c; margin-top:12px; display:none;"></div>

        <!-- REAL TASKS WILL LOAD HERE -->
        <div id="taskGrid" class="task-grid"></div>
      </div>

      <div style="grid-column: span 1;">
        <div class="wide-widget" style="height:auto; margin-bottom:20px;">
          <h3 style="color:white; margin-bottom:20px; font-size:1.1rem;">Today's Route</h3>

          <!-- REAL ROUTE LOADS HERE -->
          <div id="routeTimeline" class="timeline"></div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="taskModal">
  <div class="modal-content">
    <i class="fa-solid fa-xmark close-modal"></i>

    <div class="modal-header">
      <h2 id="modalTaskTitle">Task</h2>
      <p style="color: var(--secondary);">
        Updating Task ID: <span id="modalTaskId" style="color: var(--primary);">#</span>
      </p>
    </div>

    <form id="taskUpdateForm">
      <input type="hidden" id="modalTaskDbId" value="0">

      <div class="form-group">
        <label class="form-label" style="color: var(--secondary);">Status</label>
        <select id="modalStatus" class="modal-input">
          <option value="Pending">Pending</option>
          <option value="In Progress">In Progress</option>
          <option value="Waiting for Parts">Waiting for Parts</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" style="color: var(--secondary);">Technician Notes</label>
        <textarea id="modalNotes" class="modal-input" rows="4" placeholder="Describe work done or issues found..."></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" style="color: var(--secondary);">Proof of Work</label>

        <div style="border:2px dashed var(--border-glass); padding:15px; text-align:center; border-radius:6px; cursor:pointer; background:rgba(0,0,0,0.2);"
             onclick="document.getElementById('proofUpload').click()">
          <i class="fa-solid fa-camera" style="color: var(--secondary); font-size:1.5rem; margin-bottom:5px;"></i>
          <p style="font-size:0.8rem; color: var(--secondary);">Click to upload photo</p>
          <div id="proofPreview" style="margin-top:5px; font-size:0.8rem;"></div>
        </div>

        <input type="file" id="proofUpload" style="display:none;" accept="image/*">
      </div>

      <div id="modalError" style="color:#e74c3c; margin-top:10px; display:none;"></div>

      <div style="display:flex; gap:15px; margin-top:20px;">
        <button type="button" id="modalCancelBtn" class="btn btn-cancel" style="flex:1;">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1;">Update Task</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/employee.js"></script>
<script src="../js/dashboard.js"></script>
</body>
</html>