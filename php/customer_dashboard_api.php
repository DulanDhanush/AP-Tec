<?php
// php/customer_dashboard_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

$u = require_login_api("Customer");

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

$me = (int)($u["user_id"] ?? 0);
if ($me <= 0) j(["ok"=>false,"message"=>"Unauthorized"], 401);

$action = (string)($_GET["action"] ?? "summary");

function table_exists(PDO $pdo, string $table): bool {
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
  ");
  $stmt->execute([$table]);
  return (int)$stmt->fetchColumn() > 0;
}

// Map your tasks.status (ENUM Title Case) to step index
function status_to_step(string $status): int {
  $s = strtolower(trim($status));
  if ($s === "pending") return 1;                 // Request Received
  if ($s === "in progress") return 2;             // Diagnostics
  if ($s === "waiting for parts") return 3;       // Repairing
  if ($s === "completed") return 4;               // Completed
  return 1;
}

// ---------- SUMMARY (all widgets) ----------
if ($action === "summary") {

  // customer header
  $stmt = $pdo->prepare("SELECT full_name, avatar_initials FROM users WHERE user_id = ? LIMIT 1");
  $stmt->execute([$me]);
  $meRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  // active orders
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM tasks
    WHERE customer_id = ?
      AND status NOT IN ('Completed','Cancelled')
  ");
  $stmt->execute([$me]);
  $activeOrders = (int)$stmt->fetchColumn();

  // active repair (latest not completed/cancelled)
  $stmt = $pdo->prepare("
    SELECT
      t.task_id,
      t.task_reference,
      t.title,
      t.location,
      t.status,
      tech.full_name AS technician_name
    FROM tasks t
    LEFT JOIN users tech ON tech.user_id = t.assigned_to
    WHERE t.customer_id = ?
      AND t.status NOT IN ('Completed','Cancelled')
    ORDER BY t.created_at DESC
    LIMIT 1
  ");
  $stmt->execute([$me]);
  $activeRepair = $stmt->fetch(PDO::FETCH_ASSOC);

  // recent activity (latest 6 tasks)
  $stmt = $pdo->prepare("
    SELECT
      t.task_reference,
      t.title,
      t.created_at,
      t.status
    FROM tasks t
    WHERE t.customer_id = ?
    ORDER BY t.created_at DESC
    LIMIT 6
  ");
  $stmt->execute([$me]);
  $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // total due (real)
  // If you have invoices/payments table, use it.
  // If not, return 0 for now (or compute dummy from tasks if you later add task_cost column)
  $totalDue = 0.0;

  // Optional: if you already have an invoices table with (customer_id, total_amount, paid_amount)
  if (table_exists($pdo, "invoices")) {
    // Try common columns safely
    // If your invoices columns are different, tell me and I adjust.
    $cols = $pdo->query("SHOW COLUMNS FROM invoices")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map(fn($r) => $r["Field"], $cols);

    if (in_array("customer_id", $colNames, true) && in_array("total_amount", $colNames, true)) {
      // paid columns may differ
      if (in_array("paid_amount", $colNames, true)) {
        $stmt = $pdo->prepare("
          SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount,0)),0)
          FROM invoices
          WHERE customer_id = ?
        ");
        $stmt->execute([$me]);
        $totalDue = (float)$stmt->fetchColumn();
      } elseif (in_array("is_paid", $colNames, true)) {
        $stmt = $pdo->prepare("
          SELECT COALESCE(SUM(total_amount),0)
          FROM invoices
          WHERE customer_id = ? AND is_paid = 0
        ");
        $stmt->execute([$me]);
        $totalDue = (float)$stmt->fetchColumn();
      } else {
        // fallback: sum total_amount
        $stmt = $pdo->prepare("
          SELECT COALESCE(SUM(total_amount),0)
          FROM invoices
          WHERE customer_id = ?
        ");
        $stmt->execute([$me]);
        $totalDue = (float)$stmt->fetchColumn();
      }
    }
  }

  $step = 0;
  if ($activeRepair) $step = status_to_step((string)$activeRepair["status"]);

  j([
    "ok" => true,
    "customer" => [
      "full_name" => (string)($meRow["full_name"] ?? ""),
      "avatar_initials" => (string)($meRow["avatar_initials"] ?? "CU"),
    ],
    "widgets" => [
      "total_due" => $totalDue,
      "active_orders" => $activeOrders,
    ],
    "active_repair" => $activeRepair ? [
      "task_reference" => $activeRepair["task_reference"],
      "title" => $activeRepair["title"],
      "location" => $activeRepair["location"],
      "status" => $activeRepair["status"],
      "technician_name" => $activeRepair["technician_name"] ?? "Not Assigned",
      "step" => $step
    ] : null,
    "recent_activity" => array_map(function($r){
      return [
        "order_id" => (string)$r["task_reference"],
        "service" => (string)$r["title"],
        "date" => (string)$r["created_at"],
        "status" => (string)$r["status"],
      ];
    }, $recent),
  ]);
}

// Optional: return only recent
if ($action === "recent") {
  $stmt = $pdo->prepare("
    SELECT task_reference, title, created_at, status
    FROM tasks
    WHERE customer_id = ?
    ORDER BY created_at DESC
    LIMIT 10
  ");
  $stmt->execute([$me]);
  j(["ok"=>true, "items"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

j(["ok"=>false,"message"=>"Unknown action"], 400);