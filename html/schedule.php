<?php
declare(strict_types=1);
require_once __DIR__ . "/../php/auth.php";
$u = require_login(["Employee"]);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Schedule | AP Tec</title>
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
            <a href="employee_dashboard.php" class="nav-link"
              ><i class="fa-solid fa-clipboard-list"></i> My Tasks</a
            >
          </li>
          <li class="nav-item">
            <a href="invoice_generator.html" class="nav-link"
              ><i class="fa-solid fa-file-invoice-dollar"></i> Generate
              Invoice</a
            >
          </li>
          <li class="nav-item">
            <a href="schedule.php" class="nav-link active"
              ><i class="fa-solid fa-calendar-check"></i> Schedule</a
            >
          </li>
          <li class="nav-item">
            <a href="messages.php" class="nav-link">
              <i class="fa-solid fa-comments"></i> Messages
             <span id="msgBadge" class="tab-badge" style="background:var(--primary); color:black; display:none;">0</span>
              
            </a>
          </li>
          <li class="nav-item" style="margin-top: auto">
            <a
              href="../index.html"
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
            <h1>Field Schedule</h1>
            <p style="color: var(--secondary)">
              Manage your visits and availability.
            </p>
          </div>
          <div class="user-profile">
            <span><?= htmlspecialchars($u["full_name"] ?: $u["username"]) ?></span>
            <div class="user-avatar">JS</div>
          </div>
        </header>

        <div class="schedule-layout">
          <div class="calendar-widget">
            <div class="calendar-header-nav">
              <h2
                id="currentMonthDisplay"
                style="color: white; font-size: 1.5rem"
              >
                February 2026
              </h2>
              <div style="display: flex; gap: 10px">
                <button id="btnPrevMonth" class="btn-icon">
                  <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button id="btnNextMonth" class="btn-icon">
                  <i class="fa-solid fa-chevron-right"></i>
                </button>
              </div>
            </div>
            <div class="weekdays-grid">
              <div>SUN</div>
              <div>MON</div>
              <div>TUE</div>
              <div>WED</div>
              <div>THU</div>
              <div>FRI</div>
              <div>SAT</div>
            </div>
            <div id="daysGrid" class="days-grid"></div>
            <div
              style="
                margin-top: 20px;
                display: flex;
                gap: 20px;
                justify-content: center;
                font-size: 0.8rem;
                color: var(--secondary);
              "
            >
              <div style="display: flex; align-items: center; gap: 5px">
                <div class="event-dot dot-task"></div>
                Urgent
              </div>
              <div style="display: flex; align-items: center; gap: 5px">
                <div class="event-dot dot-routine"></div>
                Routine
              </div>
              <div style="display: flex; align-items: center; gap: 5px">
                <div class="event-dot dot-leave"></div>
                Leave
              </div>
            </div>
          </div>

          <div class="itinerary-panel">
            <h3 style="color: white; margin-bottom: 5px">Daily Itinerary</h3>
            <p
              id="selectedDateDisplay"
              style="
                color: var(--primary);
                font-size: 0.9rem;
                margin-bottom: 25px;
              "
            >
              February 7
            </p>

            <div id="itineraryList" style="flex: 1; overflow-y: auto"></div>

            <button
              id="btnAddReminder"
              class="btn btn-primary"
              style="margin-top: 20px; width: 100%"
            >
              <i class="fa-solid fa-plus"></i> Add Reminder
            </button>
          </div>
        </div>
      </main>
    </div>

    <div class="modal-overlay" id="reminderModal">
      <div class="modal-content">
        <i class="fa-solid fa-xmark close-modal" id="closeReminder"></i>

        <div class="modal-header">
          <h2>Set Reminder</h2>
          <p style="color: var(--secondary)">
            Create a new task or time-off request.
          </p>
        </div>

        <form id="formReminder">
          <div class="form-group">
            <label class="form-label" style="color: var(--secondary)"
              >Title</label
            >
            <input
              type="text"
              id="inpTitle"
              class="modal-input"
              placeholder="e.g. Call Client about delay"
              required
            />
          </div>

          <div style="display: flex; gap: 15px">
            <div class="form-group" style="flex: 1">
              <label class="form-label" style="color: var(--secondary)"
                >Date</label
              >
              <input
                type="date"
                id="inpReminderDate"
                class="date-input"
                style="
                  width: 100%;
                  color: white;
                  background: rgba(0, 0, 0, 0.2);
                "
              />
            </div>
            <div class="form-group" style="flex: 1">
              <label class="form-label" style="color: var(--secondary)"
                >Time</label
              >
              <input
                type="time"
                id="inpTime"
                class="modal-input"
                value="09:00"
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" style="color: var(--secondary)"
              >Type</label
            >
            <select id="inpType" class="modal-input">
              <option value="routine">Routine Task</option>
              <option value="urgent">Urgent Follow-up</option>
              <option value="leave">Time Off / Leave</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" style="color: var(--secondary)"
              >Notes</label
            >
            <textarea
              class="modal-input"
              rows="3"
              placeholder="Additional details..."
            ></textarea>
          </div>

          <div style="display: flex; gap: 15px; margin-top: 20px">
            <button
              type="button"
              id="cancelReminder"
              class="btn btn-cancel"
              style="flex: 1"
            >
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" style="flex: 1">
              Save to Schedule
            </button>
          </div>
        </form>
      </div>
    </div>

    <script src="../js/schedule.js"></script>
    <script src="../js/dashboard.js"></script>
  </body>
</html>
