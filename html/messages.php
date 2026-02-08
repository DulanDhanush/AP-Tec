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
    <title>Messages | AP Tec</title>

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
            <a href="employee_dashboard.php" class="nav-link">
              <i class="fa-solid fa-clipboard-list"></i> My Tasks
            </a>
          </li>
          <li class="nav-item">
            <a href="invoice_generator.html" class="nav-link">
              <i class="fa-solid fa-file-invoice-dollar"></i> Generate Invoice
            </a>
          </li>
          <li class="nav-item">
            <a href="schedule.php" class="nav-link">
              <i class="fa-solid fa-calendar-check"></i> Schedule
            </a>
          </li>
          <li class="nav-item">
            <a href="messages.php" class="nav-link active">
              <i class="fa-solid fa-comments"></i> Messages
              <span
                class="tab-badge"
                style="background: var(--primary); color: black"
                >2</span
              >
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
            <h1>Communication Center</h1>
            <p style="color: var(--secondary)">
              Chat with customers and team members.
            </p>
          </div>
          <div class="user-profile">
            <span><?= htmlspecialchars($u["full_name"] ?: $u["username"]) ?></span>
            <div class="user-avatar">JS</div>
          </div>
        </header>

        <div class="chat-layout">
          <div class="chat-sidebar">
            <div class="chat-search">
              <div class="search-group" style="width: 100%">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Search people..." />
              </div>
            </div>

            <div class="contact-list">
              <div
                class="contact-item active"
                data-role="Customer (Alpha Corp)"
              >
                <div style="position: relative">
                  <div class="user-avatar" style="background: #8e44ad">AC</div>
                  <div class="online-dot"></div>
                </div>
                <div class="contact-info">
                  <div class="contact-name">Alpha Corp HQ</div>
                  <div class="contact-preview" style="color: var(--text-white)">
                    Are you available tomorrow?
                  </div>
                </div>
                <div class="contact-meta">
                  <div>10:45 AM</div>
                  <div
                    style="
                      background: var(--primary);
                      color: black;
                      border-radius: 50%;
                      width: 18px;
                      height: 18px;
                      display: inline-flex;
                      align-items: center;
                      justify-content: center;
                      margin-top: 5px;
                    "
                  >
                    1
                  </div>
                </div>
              </div>

              <div class="contact-item" data-role="Owner (Manager)">
                <div style="position: relative">
                  <div
                    class="user-avatar"
                    style="background: #f1c40f; color: black"
                  >
                    MD
                  </div>
                  <div class="online-dot" style="background: #e74c3c"></div>
                </div>
                <div class="contact-info">
                  <div class="contact-name">Mr. Director</div>
                  <div class="contact-preview">Please approve the invoice.</div>
                </div>
                <div class="contact-meta">
                  <div>Yesterday</div>
                </div>
              </div>

              <div class="contact-item" data-role="Technician">
                <div style="position: relative">
                  <div class="user-avatar" style="background: #34495e">MW</div>
                </div>
                <div class="contact-info">
                  <div class="contact-name">Mike Williams</div>
                  <div class="contact-preview">
                    I left the tools in the van.
                  </div>
                </div>
                <div class="contact-meta">
                  <div>Feb 05</div>
                </div>
              </div>
            </div>
          </div>

          <div class="chat-window">
            <div class="chat-header">
              <div style="display: flex; gap: 15px; align-items: center">
                <div
                  id="activeUserAvatar"
                  class="user-avatar"
                  style="background: #8e44ad"
                >
                  AC
                </div>
                <div>
                  <h3
                    id="activeUserName"
                    style="color: white; font-size: 1rem; margin-bottom: 2px"
                  >
                    Alpha Corp HQ
                  </h3>
                  <p
                    id="activeUserRole"
                    style="color: var(--primary); font-size: 0.8rem"
                  >
                    Customer (Alpha Corp)
                  </p>
                </div>
              </div>
              <div style="display: flex; gap: 10px">
                <button class="btn-icon btn-view">
                  <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
              </div>
            </div>

            <div id="chatBody" class="chat-body">
              <div
                style="
                  text-align: center;
                  margin-bottom: 20px;
                  opacity: 0.5;
                  font-size: 0.8rem;
                "
              >
                Encryption: On <i class="fa-solid fa-lock"></i><br />Today
              </div>

              <div class="message-bubble msg-in">
                Hello John! We have an issue with the server in Room 3B.
                <span class="msg-time">10:42 AM</span>
              </div>

              <div class="message-bubble msg-out">
                Hi! I see the ticket. Is it completely down or just slow?
                <span class="msg-time"
                  >10:43 AM <i class="fa-solid fa-check-double"></i
                ></span>
              </div>

              <div class="message-bubble msg-in">
                It's shutting down randomly. Are you available tomorrow morning?
                <span class="msg-time">10:45 AM</span>
              </div>
            </div>

            <div class="chat-footer">
              <button
                class="btn-icon"
                style="background: transparent; font-size: 1.2rem"
              >
                <i class="fa-solid fa-paperclip"></i>
              </button>
              <input
                type="text"
                id="chatInput"
                class="chat-input"
                placeholder="Type a message..."
              />
              <button
                id="btnSend"
                class="btn btn-primary"
                style="
                  border-radius: 50%;
                  width: 48px;
                  height: 48px;
                  padding: 0;
                "
              >
                <i class="fa-solid fa-paper-plane"></i>
              </button>
            </div>
          </div>
        </div>
      </main>
    </div>

    <script src="../js/messages.js"></script>
    <script src="../js/dashboard.js"></script>
  </body>
</html>
