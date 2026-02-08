<?php
require_once __DIR__ . "/../php/auth.php";
require_once __DIR__ . "/../php/db.php";

$u = require_login("customer");

// fetch avatar fields (your auth.php doesn't select them)
$st = $pdo->prepare("SELECT avatar_initials, avatar_color FROM users WHERE user_id=? LIMIT 1");
$st->execute([(int)$u["user_id"]]);
$av = $st->fetch(PDO::FETCH_ASSOC) ?: ["avatar_initials" => "U", "avatar_color" => "#0d2c4d"];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Invoices | AP Tec</title>

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
            <a href="customer_dashboard.php" class="nav-link">
              <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a>
          </li>

          <li class="nav-item">
            <a href="customer_orders.php" class="nav-link">
              <i class="fa-solid fa-box-open"></i> My Orders
            </a>
          </li>

          <li class="nav-item">
            <a href="customer_invoices.php" class="nav-link active">
              <i class="fa-solid fa-file-invoice-dollar"></i> Invoices
            </a>
          </li>

          <li class="nav-item">
            <a href="customer_support.php" class="nav-link">
              <i class="fa-solid fa-headset"></i> Support
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
            <h1>Billing & Payments</h1>
            <p style="color: var(--secondary)">
              View billing history and settle outstanding invoices.
            </p>
          </div>

          <!-- ✅ FIXED: properly closed user-profile div -->
          <div class="user-profile">
            <span><?= htmlspecialchars((string)$u["full_name"]) ?></span>
            <div
              class="user-avatar"
              style="background: <?= htmlspecialchars((string)$av["avatar_color"]) ?>"
            >
              <?= htmlspecialchars((string)$av["avatar_initials"]) ?>
            </div>
          </div>
        </header>

        <div class="dashboard-grid">
          <div class="stat-card" style="border-left: 4px solid #e74c3c">
            <div class="stat-info">
              <h3 id="statOutstanding" style="color: #e74c3c">0.00</h3>
              <p>Total Outstanding</p>
            </div>
            <div class="stat-icon" style="color: #e74c3c">
              <i class="fa-solid fa-circle-exclamation"></i>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <h3 id="statPaidYtd" style="color: #64ffda">0.00</h3>
              <p>Total Paid (YTD)</p>
            </div>
            <div class="stat-icon">
              <i class="fa-solid fa-wallet"></i>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <h3 id="statLastPaid">—</h3>
              <p>Last Payment Date</p>
            </div>
            <div class="stat-icon">
              <i class="fa-regular fa-calendar-check"></i>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <h3 id="statOverdue">0</h3>
              <p>Invoice Overdue</p>
            </div>
            <div class="stat-icon">
              <i class="fa-solid fa-bell"></i>
            </div>
          </div>

          <div class="wide-widget" style="grid-column: span 4; height: auto">
            <div class="tab-container">
              <button class="tab-btn active" data-filter="all">
                All Invoices
              </button>
              <button class="tab-btn" data-filter="unpaid">Unpaid / Due</button>
              <button class="tab-btn" data-filter="paid">Paid History</button>
            </div>

            <table class="table-responsive">
              <thead>
                <tr>
                  <th>Invoice #</th>
                  <th>Issue Date</th>
                  <th>Due Date</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>

              <!-- ✅ IMPORTANT: id must be invoiceTbody -->
              <tbody id="invoiceTbody">
                <!-- JS will render -->
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>

    <div class="modal-overlay" id="payModal">
      <div class="modal-content">
        <i class="fa-solid fa-xmark close-modal"></i>

        <div class="modal-header">
          <h2>Secure Payment</h2>
          <p style="color: var(--secondary)">
            Settling Invoice:
            <span
              id="modalInvNum"
              class="font-mono"
              style="color: var(--primary)"
              >INV-000</span
            >
          </p>
        </div>

        <div style="text-align: center; margin-bottom: 20px">
          <h1 id="modalAmount" style="color: white; font-size: 2.5rem">
            0.00
          </h1>
        </div>

        <div class="credit-card-visual">
          <div class="cc-chip"></div>
          <div class="cc-number">•••• •••• •••• 4242</div>
          <div class="cc-meta">
            <span><?= htmlspecialchars((string)$u["full_name"]) ?></span>
            <span>EXP 12/28</span>
          </div>
        </div>

        <form id="paymentForm">
          <input type="hidden" id="modalInvoiceId" value="" />

          <div class="form-group">
            <label class="form-label" style="color: var(--secondary)"
              >Cardholder Name</label
            >
            <input
              id="cardholder"
              type="text"
              class="modal-input"
              value="<?= htmlspecialchars((string)$u["full_name"]) ?>"
            />
          </div>

          <div style="display: flex; gap: 15px">
            <div class="form-group" style="flex: 2">
              <label class="form-label" style="color: var(--secondary)"
                >Card Number</label
              >
              <input
                id="cardNumber"
                type="text"
                class="modal-input"
                placeholder="0000 0000 0000 0000"
              />
            </div>

            <div class="form-group" style="flex: 1">
              <label class="form-label" style="color: var(--secondary)"
                >CVC</label
              >
              <input
                id="cvc"
                type="text"
                class="modal-input"
                placeholder="123"
              />
            </div>
          </div>

          <button
            type="submit"
            class="btn btn-primary"
            style="width: 100%; margin-top: 20px"
          >
            Confirm Payment
            <i class="fa-solid fa-lock" style="margin-left: 5px"></i>
          </button>
        </form>
      </div>
    </div>

    <script src="../js/customer_invoices.js"></script>
    <script src="../js/dashboard.js"></script>
  </body>
</html>