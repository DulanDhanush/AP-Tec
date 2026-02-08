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

// steps: 1..4
function status_to_step(string $status): int {
  $s = strtolower(trim($status));
  if ($s === "pending") return 1;
  if ($s === "in progress") return 2;
  if ($s === "waiting for parts") return 3;
  if ($s === "completed") return 4;
  return 1;
}

if ($action === "summary") {

  // customer info
  $stmt = $pdo->prepare("SELECT full_name, avatar_initials FROM users WHERE user_id = ? LIMIT 1");
  $stmt->execute([$me]);
  $meRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  // active orders count
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM tasks
    WHERE customer_id = ?
      AND status NOT IN ('Completed','Cancelled')
  ");
  $stmt->execute([$me]);
  $activeOrders = (int)$stmt->fetchColumn();

  // active repair (latest open task)
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
    SELECT task_reference, title, created_at, status
    FROM tasks
    WHERE customer_id = ?
    ORDER BY created_at DESC
    LIMIT 6
  ");
  $stmt->execute([$me]);
  $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // total due (if invoices table exists)
  $totalDue = 0.0;

  if (table_exists($pdo, "invoices")) {
    $cols = $pdo->query("SHOW COLUMNS FROM invoices")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map(fn($r) => $r["Field"], $cols);

    if (in_array("customer_id", $colNames, true) && in_array("total_amount", $colNames, true)) {
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

  $step = $activeRepair ? status_to_step((string)$activeRepair["status"]) : 0;

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
      "task_reference" => (string)$activeRepair["task_reference"],
      "title" => (string)$activeRepair["title"],
      "location" => (string)($activeRepair["location"] ?? ""),
      "status" => (string)$activeRepair["status"],
      "technician_name" => (string)($activeRepair["technician_name"] ?? "Not Assigned"),
      "step" => $step
    ] : null,
    "recent_activity" => array_map(function($r){
      return [
        "order_id" => (string)$r["task_reference"],
        "service"  => (string)$r["title"],
        "date"     => (string)$r["created_at"],
        "status"   => (string)$r["status"],
      ];
    }, $recent),
  ]);
}

j(["ok"=>false,"message"=>"Unknown action"], 400);