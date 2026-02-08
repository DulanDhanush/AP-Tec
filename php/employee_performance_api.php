<?php
// php/employee_performance_api.php
declare(strict_types=1);

require_once __DIR__ . "/api_bootstrap.php";

require_login_api("Owner");

$action = (string)($_GET["action"] ?? "");

function j(array $arr, int $code = 200): void {
  if (ob_get_length()) ob_clean();
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

function month_range(): array {
  $start = new DateTime("first day of this month 00:00:00");
  $end   = new DateTime("first day of next month 00:00:00");
  return [$start->format("Y-m-d H:i:s"), $end->format("Y-m-d H:i:s")];
}

function has_column(PDO $pdo, string $table, string $col): bool {
  $stmt = $pdo->prepare("
    SELECT COUNT(*) c
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND COLUMN_NAME = :c
  ");
  $stmt->execute([":t" => $table, ":c" => $col]);
  return ((int)($stmt->fetch()["c"] ?? 0)) > 0;
}

function status_for_user(array $r): string {
  $accStatus = (string)($r["account_status"] ?? "Active");
  if ($accStatus !== "Active") return "On Leave";
  if (!empty($r["has_active_task"])) return "On Job";
  return ((int)($r["is_available"] ?? 1) === 1) ? "Available" : "Unavailable";
}

[$mStart, $mEnd] = month_range();

// Detect columns safely
$hasCreatedAt   = has_column($pdo, "tasks", "created_at");
$hasUpdatedAt   = has_column($pdo, "tasks", "updated_at");
$hasCompletedAt = has_column($pdo, "tasks", "completed_at");

// Choose best "task created date" expression (for filtering ratings/month etc.)
$taskCreatedExpr = $hasCreatedAt ? "t.created_at" : ($hasUpdatedAt ? "t.updated_at" : "NOW()");

// Choose best "completion date" expression
$completionExpr = $hasCompletedAt ? "t.completed_at" : ($hasUpdatedAt ? "t.updated_at" : ($hasCreatedAt ? "t.created_at" : "NOW()"));

/* ========================= SUMMARY ========================= */
if ($action === "summary") {

  // On-time completion % (completed this month, due_date exists)
  $stmt = $pdo->prepare("
    SELECT
      COUNT(*) AS total_completed_with_due,
      SUM(CASE WHEN DATE($completionExpr) <= t.due_date THEN 1 ELSE 0 END) AS on_time
    FROM tasks t
    WHERE LOWER(t.status) IN ('completed','complete','done')
      AND $completionExpr >= :ms1 AND $completionExpr < :me1
      AND t.due_date IS NOT NULL
  ");
  $stmt->execute([":ms1" => $mStart, ":me1" => $mEnd]);
  $r = $stmt->fetch() ?: ["total_completed_with_due" => 0, "on_time" => 0];

  $total = (int)$r["total_completed_with_due"];
  $onTime = (int)$r["on_time"];
  $onTimePct = ($total > 0) ? (int)round(($onTime / $total) * 100) : 0;

  // Total technicians
  $stmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role='Employee'");
  $totalTech = (int)($stmt->fetch()["c"] ?? 0);

  // Active technicians (Active + available)
  $stmt = $pdo->query("
    SELECT COUNT(*) AS c
    FROM users u
    LEFT JOIN technician_status ts ON ts.user_id = u.user_id
    WHERE u.role='Employee'
      AND u.status='Active'
      AND COALESCE(ts.is_available, 1) = 1
  ");
  $activeTech = (int)($stmt->fetch()["c"] ?? 0);

  // Top performer (MOST completed tasks this month)
  $stmt = $pdo->prepare("
    SELECT
      u.user_id,
      u.full_name,
      u.avatar_initials,
      u.avatar_color,
      COUNT(t.task_id) AS completed_tasks,
      ROUND(AVG(t.customer_rating), 1) AS avg_rating
    FROM users u
    JOIN tasks t ON t.assigned_to = u.user_id
    WHERE u.role='Employee'
      AND LOWER(t.status) IN ('completed','complete','done')
      AND $completionExpr >= :ms2 AND $completionExpr < :me2
    GROUP BY u.user_id, u.full_name, u.avatar_initials, u.avatar_color
    ORDER BY completed_tasks DESC, avg_rating DESC
    LIMIT 1
  ");
  $stmt->execute([":ms2" => $mStart, ":me2" => $mEnd]);
  $top = $stmt->fetch();

  if (!$top) {
    $top = [
      "user_id" => null,
      "full_name" => "—",
      "avatar_initials" => "—",
      "avatar_color" => "#f1c40f",
      "completed_tasks" => 0,
      "avg_rating" => null
    ];
  }

  j([
    "ok" => true,
    "on_time_pct" => $onTimePct,
    "active_techs" => $activeTech,
    "total_techs" => $totalTech,
    "top" => $top
  ]);
}

/* ========================= TECHNICIANS ========================= */
if ($action === "technicians") {

  // NOTE:
  // tasks_month = count of completed tasks in month (by completionExpr)
  // avg_rating  = avg rating in month (filter by taskCreatedExpr)

  $stmt = $pdo->prepare("
    SELECT
      u.user_id,
      u.full_name,
      u.avatar_initials,
      u.avatar_color,
      u.status AS account_status,
      COALESCE(ts.is_available,1) AS is_available,

      EXISTS(
        SELECT 1 FROM tasks t2
        WHERE t2.assigned_to = u.user_id
          AND LOWER(t2.status) IN ('pending','in progress','waiting for parts','in_progress','inprogress')
      ) AS has_active_task,

      (
        SELECT COUNT(*)
        FROM tasks t3
        WHERE t3.assigned_to = u.user_id
          AND LOWER(t3.status) IN ('completed','complete','done')
          AND (" . str_replace("t.", "t3.", $completionExpr) . ") >= :ms1
          AND (" . str_replace("t.", "t3.", $completionExpr) . ") < :me1
      ) AS tasks_month,

      (
        SELECT ROUND(AVG(t4.customer_rating), 1)
        FROM tasks t4
        WHERE t4.assigned_to = u.user_id
          AND t4.customer_rating IS NOT NULL
          AND (" . str_replace("t.", "t4.", $taskCreatedExpr) . ") >= :ms2
          AND (" . str_replace("t.", "t4.", $taskCreatedExpr) . ") < :me2
      ) AS avg_rating
    FROM users u
    LEFT JOIN technician_status ts ON ts.user_id = u.user_id
    WHERE u.role='Employee'
    ORDER BY tasks_month DESC, avg_rating DESC, u.full_name ASC
  ");

  $stmt->execute([
    ":ms1" => $mStart, ":me1" => $mEnd,
    ":ms2" => $mStart, ":me2" => $mEnd,
  ]);

  $rows = $stmt->fetchAll();

  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      "user_id" => (int)$r["user_id"],
      "full_name" => $r["full_name"],
      "avatar_initials" => $r["avatar_initials"] ?: "U",
      "avatar_color" => $r["avatar_color"] ?: "#0d2c4d",
      "status" => status_for_user($r),
      "tasks_month" => (int)$r["tasks_month"],
      "avg_rating" => ($r["avg_rating"] !== null ? (float)$r["avg_rating"] : null),
      "skills" => []
    ];
  }

  j(["ok" => true, "technicians" => $out]);
}

/* ========================= FEEDBACK ========================= */
if ($action === "feedback") {
  $limit = max(1, min(20, (int)($_GET["limit"] ?? 6)));

  $stmt = $pdo->prepare("
    SELECT
      t.task_id,
      t.customer_rating,
      t.customer_feedback,
      t.created_at,
      cu.full_name AS customer_name,
      te.full_name AS technician_name
    FROM tasks t
    LEFT JOIN users cu ON cu.user_id = t.customer_id
    LEFT JOIN users te ON te.user_id = t.assigned_to
    WHERE t.customer_rating IS NOT NULL
      AND t.customer_feedback IS NOT NULL
      AND TRIM(t.customer_feedback) <> ''
    ORDER BY t.created_at DESC
    LIMIT {$limit}
  ");
  $stmt->execute();
  $rows = $stmt->fetchAll();

  j(["ok" => true, "feedback" => $rows]);
}

/* ========================= TECH DETAIL ========================= */
if ($action === "technician_detail") {
  $techId = (int)($_GET["id"] ?? 0);
  if ($techId <= 0) j(["ok" => false, "error" => "Invalid technician id"], 400);

  $stmt = $pdo->prepare("
    SELECT user_id, username, full_name, email, phone, status, avatar_initials, avatar_color
    FROM users
    WHERE user_id = :id AND role='Employee'
    LIMIT 1
  ");
  $stmt->execute([":id" => $techId]);
  $urow = $stmt->fetch();
  if (!$urow) j(["ok" => false, "error" => "Technician not found"], 404);

  $stmt = $pdo->prepare("
    SELECT task_id, title, status, priority, due_date, customer_rating
    FROM tasks
    WHERE assigned_to = :id
    ORDER BY task_id DESC
    LIMIT 10
  ");
  $stmt->execute([":id" => $techId]);
  $tasks = $stmt->fetchAll();

  j(["ok" => true, "technician" => $urow, "recent_tasks" => $tasks]);
}

/* ========================= EXPORT CSV ========================= */
if ($action === "export_csv") {
  if (ob_get_length()) ob_clean();

  $stmt = $pdo->prepare("
    SELECT
      u.user_id,
      u.full_name,
      u.email,
      COALESCE(ts.is_available,1) AS is_available,
      (
        SELECT COUNT(*)
        FROM tasks t3
        WHERE t3.assigned_to = u.user_id
          AND LOWER(t3.status) IN ('completed','complete','done')
          AND (" . str_replace("t.", "t3.", $completionExpr) . ") >= :ms1
          AND (" . str_replace("t.", "t3.", $completionExpr) . ") < :me1
      ) AS tasks_month,
      (
        SELECT ROUND(AVG(t4.customer_rating), 2)
        FROM tasks t4
        WHERE t4.assigned_to = u.user_id
          AND t4.customer_rating IS NOT NULL
          AND (" . str_replace("t.", "t4.", $taskCreatedExpr) . ") >= :ms2
          AND (" . str_replace("t.", "t4.", $taskCreatedExpr) . ") < :me2
      ) AS avg_rating
    FROM users u
    LEFT JOIN technician_status ts ON ts.user_id = u.user_id
    WHERE u.role='Employee'
    ORDER BY tasks_month DESC, avg_rating DESC, u.full_name ASC
  ");

  $stmt->execute([
    ":ms1" => $mStart, ":me1" => $mEnd,
    ":ms2" => $mStart, ":me2" => $mEnd,
  ]);

  $rows = $stmt->fetchAll();

  header("Content-Type: text/csv; charset=utf-8");
  header("Content-Disposition: attachment; filename=employee_performance_" . date("Y-m") . ".csv");

  $out = fopen("php://output", "w");
  fputcsv($out, ["user_id","full_name","email","is_available","tasks_month","avg_rating"]);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r["user_id"],
      $r["full_name"],
      $r["email"],
      $r["is_available"],
      $r["tasks_month"],
      $r["avg_rating"],
    ]);
  }
  fclose($out);
  exit;
}

j(["ok" => false, "error" => "Unknown action"], 400);