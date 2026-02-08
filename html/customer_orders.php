<?php
declare(strict_types=1);

require_once __DIR__ . "/../php/auth.php";
require_once __DIR__ . "/../php/db.php";

$u = require_login(["customer"]);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

$customerId = (int)($u["user_id"] ?? 0);

// get customer display/avatars
$userStmt = $pdo->prepare("SELECT full_name, avatar_initials, avatar_color FROM users WHERE user_id = :id LIMIT 1");
$userStmt->execute([":id" => $customerId]);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$displayName = (string)($userRow["full_name"] ?? $u["full_name"] ?? $u["username"] ?? "Customer");
$avatarInit  = (string)($userRow["avatar_initials"] ?? "U");
$avatarColor = (string)($userRow["avatar_color"] ?? "#0d2c4d");

function badge_class(string $status): string {
  $s = strtolower($status);
  return match ($s) {
    "shipped" => "status-shipped",
    "processing" => "status-processing",
    "delivered" => "status-delivered",
    "cancelled" => "status-inactive",
    "pending" => "status-pending",
    default => "status-pending",
  };
}

// list orders with 1-line items summary
$ordersStmt = $pdo->prepare("
  SELECT 
    o.order_id,
    o.order_reference,
    o.order_date,
    o.total_amount,
    o.status,
    o.tracking_step,
    COALESCE((
      SELECT GROUP_CONCAT(CONCAT(i.item_name, ' (x', oi.quantity, ')') SEPARATOR ', ')
      FROM order_items oi
      JOIN inventory i ON i.item_id = oi.item_id
      WHERE oi.order_id = o.order_id
    ), 'Order') AS items_summary
  FROM orders o
  WHERE o.customer_id = :cid
  ORDER BY o.order_date DESC
");
$ordersStmt->execute([":cid" => $customerId]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

function money(float $v): string { return number_format($v, 2, ".", ","); }
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Orders | AP Tec</title>

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
            <a href="customer_orders.php" class="nav-link active">
              <i class="fa-solid fa-box-open"></i> My Orders
            </a>
          </li>
          <li class="nav-item">
            <a href="customer_invoices.html" class="nav-link">
              <i class="fa-solid fa-file-invoice"></i> Invoices
            </a>
          </li>
          <li class="nav-item">
            <a href="customer_support.html" class="nav-link">
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
            <h1>My Orders</h1>
            <p style="color: var(--secondary)">
              Track active shipments and view order history.
            </p>
          </div>
          <div class="user-profile">
            <span><?= h($displayName) ?></span>
            <div class="user-avatar" style="background: <?= h($avatarColor) ?>"><?= h($avatarInit) ?></div>
          </div>
        </header>

        <div class="dashboard-grid">
          <div class="wide-widget" style="grid-column: span 4; height: auto">
            <div class="tab-container">
              <button class="tab-btn active" data-filter="all">
                All Orders
              </button>
              <button class="tab-btn" data-filter="active">
                Active & Tracking
              </button>
              <button class="tab-btn" data-filter="history">
                Order History
              </button>
            </div>

            <table class="table-responsive">
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Date</th>
                  <th>Items</th>
                  <th>Total</th>
                  <th>Status</th>
                  
                </tr>
              </thead>
              <tbody>
                <?php if (!$orders): ?>
                  <tr>
                    <td colspan="6" style="color: var(--secondary); padding: 16px;">
                      No orders found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($orders as $o): ?>
                    <?php
                      $orderId = (int)$o["order_id"];
                      $ref = (string)($o["order_reference"] ?? ("ORD-" . $orderId));
                      $status = (string)$o["status"];
                      $items = (string)$o["items_summary"];
                      $dateLabel = date("M d, Y", strtotime((string)$o["order_date"]));
                      $total = (float)$o["total_amount"];
                      $badge = badge_class($status);
                    ?>
                    <tr class="order-row" data-status="<?= h($status) ?>" data-order-id="<?= $orderId ?>">
                      <td class="font-mono">#<?= h($ref) ?></td>
                      <td><?= h($dateLabel) ?></td>
                      <td><?= h($items) ?></td>
                      <td style="color: white; font-weight: 600">$<?= h(money($total)) ?></td>
                      <td>
                        <span class="status-badge <?= h($badge) ?>"><?= h($status) ?></span>
                      </td>
                      <td>
                        <div class="action-buttons">
                        

                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>

    <div class="modal-overlay" id="orderModal">
      <div class="modal-content" style="width: 600px">
        <i class="fa-solid fa-xmark close-modal"></i>

        <div class="modal-header">
          <div
            style="
              display: flex;
              justify-content: space-between;
              align-items: start;
            "
          >
            <div>
              <h2>Order Details</h2>
              <p style="color: var(--secondary)">
                Reference:
                <span
                  id="modalOrderId"
                  class="font-mono"
                  style="color: var(--primary)"
                  >#ORD-000</span
                >
              </p>
            </div>
            <span id="modalStatus" class="status-badge status-shipped"
              >Shipped</span
            >
          </div>
        </div>

        <div class="track-container" style="margin: 20px 10px 40px 10px">
          <div
            id="modalTrackBar"
            class="track-progress-bar"
            style="width: 0%"
          ></div>

          <div class="step-item" id="step1">
            <div class="step-circle">
              <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <div class="step-text">Confirmed</div>
          </div>
          <div class="step-item" id="step2">
            <div class="step-circle"><i class="fa-solid fa-box"></i></div>
            <div class="step-text">Processing</div>
          </div>
          <div class="step-item" id="step3">
            <div class="step-circle">
              <i class="fa-solid fa-truck-fast"></i>
            </div>
            <div class="step-text">Shipped</div>
          </div>
          <div class="step-item" id="step4">
            <div class="step-circle"><i class="fa-solid fa-check"></i></div>
            <div class="step-text">Delivered</div>
          </div>
        </div>

        <h4
          style="
            color: white;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-glass);
            padding-bottom: 5px;
          "
        >
          Items Ordered
        </h4>

        <div class="order-items-list" id="modalItems">
          <!-- JS fills items -->
        </div>

        <div
          style="
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
          "
        >
          <div style="text-align: left">
            <p style="color: var(--secondary); font-size: 0.9rem">
              Total Amount
            </p>
            <h2 id="modalTotal" style="color: white">0.00</h2>
          </div>
          <button class="btn btn-primary" id="btnDownloadInvoice">
            <i class="fa-solid fa-download"></i> Download Invoice
          </button>
        </div>
      </div>
    </div>

    <script src="../js/customer_orders.js"></script>
    <script src="../js/dashboard.js"></script>
  </body>
</html>