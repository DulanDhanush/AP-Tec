<?php
declare(strict_types=1);

require_once __DIR__ . "/../php/auth.php";
require_once __DIR__ . "/../php/db.php";

$u = require_login(["customer"]); // keeps your auth rule

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

$customerId = (int)($u["user_id"] ?? 0);

/**
 * Generate references like ORD-260208-483921 or REP-260208-193844
 */
function make_ref(string $prefix): string {
    $date = date("ymd");
    $rand = random_int(100000, 999999);
    return "{$prefix}-{$date}-{$rand}";
}

/**
 * Map task status to progress step 1-4
 * 1=Request Received, 2=Diagnostics, 3=Repairing, 4=Completed
 */
function task_step(string $status): int {
    $status = strtolower($status);
    return match ($status) {
        "pending" => 1,
        "in progress" => 3,          // you can change to 2 if you want
        "waiting for parts" => 3,
        "completed" => 4,
        default => 1,
    };
}

$flash = "";

/* =========================
   HANDLE NEW REQUEST SUBMIT
   ========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "create_request") {
    $type = trim((string)($_POST["request_type"] ?? ""));
    $device = trim((string)($_POST["device_name"] ?? ""));
    $desc = trim((string)($_POST["description"] ?? ""));
    $preferred = trim((string)($_POST["preferred_date"] ?? "")); // YYYY-MM-DD

    if ($type === "" || $device === "" || $desc === "") {
        $flash = "Please fill Request Type, Device/Item Name, and Description.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($type === "Order Supplies") {
                // Create order
                $orderRef = make_ref("ORD");
                $stmt = $pdo->prepare("
                    INSERT INTO orders (order_reference, customer_id, total_amount, status, tracking_step)
                    VALUES (:ref, :cid, 0.00, 'Pending', 1)
                ");
                $stmt->execute([
                    ":ref" => $orderRef,
                    ":cid" => $customerId
                ]);
                $orderId = (int)$pdo->lastInsertId();

                // Try match inventory item by name (exact match)
                $itemStmt = $pdo->prepare("SELECT item_id, unit_price FROM inventory WHERE item_name = :name LIMIT 1");
                $itemStmt->execute([":name" => $device]);
                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

                $total = 0.00;

                if ($item) {
                    $itemId = (int)$item["item_id"];
                    $unitPrice = (float)$item["unit_price"];
                    $qty = 1;

                    $oi = $pdo->prepare("
                        INSERT INTO order_items (order_id, item_id, quantity, price_at_purchase)
                        VALUES (:oid, :iid, :qty, :price)
                    ");
                    $oi->execute([
                        ":oid" => $orderId,
                        ":iid" => $itemId,
                        ":qty" => $qty,
                        ":price" => $unitPrice
                    ]);

                    $total = $unitPrice * $qty;

                    $upd = $pdo->prepare("UPDATE orders SET total_amount = :t WHERE order_id = :oid");
                    $upd->execute([":t" => $total, ":oid" => $orderId]);
                } else {
                    // If no inventory match, still keep the order (total 0)
                    // Optionally you could create a support ticket instead, but this keeps it simple.
                }

                $pdo->commit();
                header("Location: customer_dashboard.php?ok=1");
                exit;
            }

            // Repair/Maintenance OR Software Support => create task
            $taskRef = make_ref("REP");
            $titlePrefix = ($type === "Software Support") ? "Software Support" : "Repair / Maintenance";
            $title = $titlePrefix . ": " . $device;

            $dueDate = null;
            if ($preferred !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferred)) {
                $dueDate = $preferred;
            }

            $t = $pdo->prepare("
                INSERT INTO tasks (task_reference, title, description, customer_id, status, priority, due_date, location)
                VALUES (:ref, :title, :desc, :cid, 'Pending', 'Normal', :due, NULL)
            ");
            $t->execute([
                ":ref" => $taskRef,
                ":title" => $title,
                ":desc" => $desc,
                ":cid" => $customerId,
                ":due" => $dueDate
            ]);

            $pdo->commit();
            header("Location: customer_dashboard.php?ok=1");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $flash = "Server error while creating request: " . $e->getMessage();
        }
    }
}

/* =========================
   LOAD DASHBOARD DATA
   ========================= */

// Fresh user data (avatar, full name, etc.)
$userStmt = $pdo->prepare("SELECT user_id, full_name, username, avatar_initials, avatar_color FROM users WHERE user_id = :id LIMIT 1");
$userStmt->execute([":id" => $customerId]);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$displayName = (string)($userRow["full_name"] ?? $u["full_name"] ?? $u["username"] ?? "Customer");
$avatarInit  = (string)($userRow["avatar_initials"] ?? "U");
$avatarColor = (string)($userRow["avatar_color"] ?? "#0d2c4d");

// Total Due
$dueStmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS due
    FROM invoices
    WHERE customer_id = :cid
      AND status IN ('Unpaid','Overdue')
");
$dueStmt->execute([":cid" => $customerId]);
$totalDue = (float)($dueStmt->fetchColumn() ?: 0);

// Active Orders count
$activeOrdersStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders
    WHERE customer_id = :cid
      AND status NOT IN ('Delivered','Cancelled')
");
$activeOrdersStmt->execute([":cid" => $customerId]);
$activeOrders = (int)($activeOrdersStmt->fetchColumn() ?: 0);

// Active Repair task (latest not completed/cancelled)
$taskStmt = $pdo->prepare("
    SELECT 
        t.task_id, t.task_reference, t.title, t.location, t.status, t.created_at,
        tech.full_name AS technician_name
    FROM tasks t
    LEFT JOIN users tech ON tech.user_id = t.assigned_to
    WHERE t.customer_id = :cid
      AND t.status NOT IN ('Completed','Cancelled')
    ORDER BY t.created_at DESC
    LIMIT 1
");
$taskStmt->execute([":cid" => $customerId]);
$activeTask = $taskStmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Recent Activity (orders + tasks)
$activityStmt = $pdo->prepare("
    (SELECT 
        'order' AS kind,
        o.order_id AS id,
        o.order_reference AS ref,
        o.order_date AS dt,
        o.status AS st,
        COALESCE((
            SELECT CONCAT(i.item_name, ' (x', oi.quantity, ')')
            FROM order_items oi 
            JOIN inventory i ON i.item_id = oi.item_id
            WHERE oi.order_id = o.order_id
            LIMIT 1
        ), 'Order') AS title,
        inv.invoice_id AS invoice_id
     FROM orders o
     LEFT JOIN invoices inv ON inv.order_id = o.order_id AND inv.customer_id = o.customer_id
     WHERE o.customer_id = :cid1
    )
    UNION ALL
    (SELECT
        'task' AS kind,
        t.task_id AS id,
        t.task_reference AS ref,
        t.created_at AS dt,
        t.status AS st,
        t.title AS title,
        NULL AS invoice_id
     FROM tasks t
     WHERE t.customer_id = :cid2
    )
    ORDER BY dt DESC
    LIMIT 5
");
$activityStmt->execute([":cid1" => $customerId, ":cid2" => $customerId]);
$recent = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

/* Format money */
function money(float $v): string {
    return number_format($v, 2, ".", ",");
}

// Progress step for active task
$step = $activeTask ? task_step((string)$activeTask["status"]) : 0;

// “Ticket” label: use task_reference if exists else task_id
$ticketLabel = $activeTask ? ((string)($activeTask["task_reference"] ?? ("REP-" . $activeTask["task_id"]))) : "";
$taskTitle = $activeTask ? (string)$activeTask["title"] : "No active repair";
$taskLoc = $activeTask ? (string)($activeTask["location"] ?? "") : "";
$techName = $activeTask ? ((string)($activeTask["technician_name"] ?? "Not assigned yet")) : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal | AP Tec</title>
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
                    <a href="customer_dashboard.php" class="nav-link active">
                        <i class="fa-solid fa-gauge-high"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customer_orders.php" class="nav-link">
                        <i class="fa-solid fa-box-open"></i> My Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customer_invoices.php" class="nav-link">
                        <i class="fa-solid fa-file-invoice"></i> Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customer_support.html" class="nav-link">
                        <i class="fa-solid fa-headset"></i> Support
                    </a>
                </li>
                 <li class="nav-item" style="margin-top: auto;">
                    <a href="../index.html" class="nav-link logout-btn" style="color: #e74c3c;">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            
            <header class="top-bar">
                <div class="page-title">
                    <h1>My Dashboard</h1>
                    <p style="color: var(--secondary);">Welcome back, <?= h($displayName) ?>.</p>

                    <?php if (isset($_GET["ok"])): ?>
                        <p style="margin-top:8px;color:#64ffda;">✅ Request submitted successfully.</p>
                    <?php endif; ?>

                    <?php if ($flash !== ""): ?>
                        <p style="margin-top:8px;color:#ff7675;"><?= h($flash) ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; gap: 15px; align-items: center;">
                    <button id="btnNewRequest" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> New Request
                    </button>
                    <div class="user-profile">
                        <span><?= h($displayName) ?></span>
                        <div class="user-avatar" style="background: <?= h($avatarColor) ?>;"><?= h($avatarInit) ?></div>
                    </div>
                </div>
            </header>

            <div class="dashboard-grid">
                
                <div class="wide-widget" style="grid-column: span 4; height: auto; min-height: 250px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h3 style="color: white;">
                            Active Repair:
                            <span style="color: var(--primary);">
                                <?= h($taskTitle) ?><?= $taskLoc ? " (" . h($taskLoc) . ")" : "" ?>
                            </span>
                        </h3>
                        <?php if ($activeTask): ?>
                            <span class="status-badge status-pending" style="font-size: 0.9rem;"><?= h($ticketLabel) ?></span>
                        <?php else: ?>
                            <span class="status-badge status-active" style="font-size: 0.9rem;">No Ticket</span>
                        <?php endif; ?>
                    </div>

                    <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 30px;">
                        Technician Assigned: <?= h($techName) ?>
                    </p>

                    <div class="track-container">
                        <div class="track-progress-bar"></div>

                        <?php
                            // step classes: completed, active, none
                            $s1 = ($step >= 1) ? "completed" : "";
                            $s2 = ($step >= 2) ? "completed" : "";
                            $s3 = ($step >= 3) ? "completed" : "";
                            $s4 = ($step >= 4) ? "completed" : "";

                            // mark exactly current as active (only if there is a task)
                            if ($activeTask) {
                                if ($step === 1) $s1 = "active";
                                if ($step === 2) $s2 = "active";
                                if ($step === 3) $s3 = "active";
                                if ($step === 4) $s4 = "active";
                            }
                        ?>

                        <div class="step-item <?= h($s1) ?>">
                            <div class="step-circle"><i class="fa-solid fa-check"></i></div>
                            <div class="step-text">Request Received</div>
                        </div>

                        <div class="step-item <?= h($s2) ?>">
                            <div class="step-circle"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                            <div class="step-text">Diagnostics</div>
                        </div>

                        <div class="step-item <?= h($s3) ?>">
                            <div class="step-circle"><i class="fa-solid fa-gears"></i></div>
                            <div class="step-text">Repairing</div>
                        </div>

                        <div class="step-item <?= h($s4) ?>">
                            <div class="step-circle"><i class="fa-solid fa-flag-checkered"></i></div>
                            <div class="step-text">Completed</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3 style="color: #64ffda;"><?= h(money($totalDue)) ?></h3>
                        <p>Total Due</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?= (int)$activeOrders ?></h3>
                        <p>Active Orders</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
                </div>

                <div class="wide-widget" style="grid-column: span 2; height: auto;">
                    <h3 style="color: white; margin-bottom: 15px;">Recent Activity</h3>
                    <table class="table-responsive">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Service / Item</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recent): ?>
                                <tr>
                                    <td colspan="5" style="color: var(--secondary); padding: 14px;">
                                        No recent activity yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent as $row): ?>
                                    <?php
                                        $ref = (string)($row["ref"] ?? "");
                                        $title = (string)($row["title"] ?? "");
                                        $dt = (string)($row["dt"] ?? "");
                                        $status = (string)($row["st"] ?? "");
                                        $invoiceId = $row["invoice_id"] ?? null;

                                        $dateLabel = $dt ? date("M d", strtotime($dt)) : "-";

                                        $badgeClass = "status-pending";
                                        $stLower = strtolower($status);
                                        if (in_array($stLower, ["delivered","completed","resolved","closed"], true)) $badgeClass = "status-active";
                                        if (in_array($stLower, ["cancelled"], true)) $badgeClass = "status-inactive";
                                    ?>
                                    <tr>
                                        <td class="font-mono"><?= h($ref !== "" ? $ref : (string)$row["kind"]) ?></td>
                                        <td><?= h($title) ?></td>
                                        <td><?= h($dateLabel) ?></td>
                                        <td><span class="status-badge <?= h($badgeClass) ?>"><?= h($status) ?></span></td>
                                        <td>
                                            <?php if ($invoiceId): ?>
                                                <a class="btn-glass" style="padding: 5px 10px; font-size: 0.8rem; display:inline-block; text-decoration:none;"
                                                   href="customer_invoices.html?invoice_id=<?= (int)$invoiceId ?>">
                                                    Invoice
                                                </a>
                                            <?php else: ?>
                                                <button class="btn-glass" style="padding: 5px 10px; font-size: 0.8rem; opacity:0.6;" disabled>
                                                    Invoice
                                                </button>
                                            <?php endif; ?>
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

    <div class="modal-overlay" id="requestModal">
        <div class="modal-content">
            <i class="fa-solid fa-xmark close-modal"></i>
            
            <div class="modal-header">
                <h2>Request Service</h2>
                <p style="color: var(--secondary);">Describe the issue or select items to order.</p>
            </div>
            
            <form method="POST" action="customer_dashboard.php">
                <input type="hidden" name="action" value="create_request">

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Request Type</label>
                    <select class="modal-input" name="request_type" required>
                        <option>Repair / Maintenance</option>
                        <option>Order Supplies</option>
                        <option>Software Support</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Device / Item Name</label>
                    <input type="text" class="modal-input" name="device_name" placeholder="e.g. Main Hall Printer" required>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Description of Issue</label>
                    <textarea class="modal-input" name="description" rows="4" placeholder="It's making a loud noise when printing..." required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Preferred Date</label>
                    <input type="date" name="preferred_date" class="date-input" style="width: 100%; background: rgba(0,0,0,0.2); color: white;">
                </div>

                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="button" class="btn btn-cancel" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/customer.js"></script>
    <script src="../js/dashboard.js"></script>
    <script src="../js/customer_dashboard.js"></script>

</body>
</html>