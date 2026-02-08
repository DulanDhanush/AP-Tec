<?php
// php/employee_performance_report.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/fpdf/fpdf.php";
require_login_api("Owner");

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

// Detect date columns
$hasCreatedAt   = has_column($pdo, "tasks", "created_at");
$hasUpdatedAt   = has_column($pdo, "tasks", "updated_at");
$hasCompletedAt = has_column($pdo, "tasks", "completed_at");

$completionExpr = $hasCompletedAt ? "t.completed_at" : ($hasUpdatedAt ? "t.updated_at" : ($hasCreatedAt ? "t.created_at" : "NOW()"));
$taskCreatedExpr = $hasCreatedAt ? "t.created_at" : ($hasUpdatedAt ? "t.updated_at" : "NOW()");

/* ===================== DATA QUERIES ===================== */

// Summary
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
$sumRow = $stmt->fetch() ?: ["total_completed_with_due" => 0, "on_time" => 0];

$total = (int)$sumRow["total_completed_with_due"];
$onTime = (int)$sumRow["on_time"];
$onTimePct = ($total > 0) ? (int)round(($onTime / $total) * 100) : 0;

$stmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role='Employee'");
$totalTech = (int)($stmt->fetch()["c"] ?? 0);

$stmt = $pdo->query("
  SELECT COUNT(*) AS c
  FROM users u
  LEFT JOIN technician_status ts ON ts.user_id = u.user_id
  WHERE u.role='Employee'
    AND u.status='Active'
    AND COALESCE(ts.is_available, 1) = 1
");
$activeTech = (int)($stmt->fetch()["c"] ?? 0);

// Top performer
$stmt = $pdo->prepare("
  SELECT
    u.user_id,
    u.full_name,
    COUNT(t.task_id) AS completed_tasks,
    ROUND(AVG(t.customer_rating), 1) AS avg_rating
  FROM users u
  JOIN tasks t ON t.assigned_to = u.user_id
  WHERE u.role='Employee'
    AND LOWER(t.status) IN ('completed','complete','done')
    AND $completionExpr >= :ms2 AND $completionExpr < :me2
  GROUP BY u.user_id, u.full_name
  ORDER BY completed_tasks DESC, avg_rating DESC
  LIMIT 1
");
$stmt->execute([":ms2" => $mStart, ":me2" => $mEnd]);
$top = $stmt->fetch();
if (!$top) {
  $top = ["full_name" => "—", "completed_tasks" => 0, "avg_rating" => "—"];
}

// Technicians roster
$stmt = $pdo->prepare("
  SELECT
    u.user_id,
    u.full_name,
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
        AND (" . str_replace("t.", "t3.", $completionExpr) . ") >= :ms3
        AND (" . str_replace("t.", "t3.", $completionExpr) . ") < :me3
    ) AS tasks_month,

    (
      SELECT ROUND(AVG(t4.customer_rating), 1)
      FROM tasks t4
      WHERE t4.assigned_to = u.user_id
        AND t4.customer_rating IS NOT NULL
        AND (" . str_replace("t.", "t4.", $taskCreatedExpr) . ") >= :ms4
        AND (" . str_replace("t.", "t4.", $taskCreatedExpr) . ") < :me4
    ) AS avg_rating
  FROM users u
  LEFT JOIN technician_status ts ON ts.user_id = u.user_id
  WHERE u.role='Employee'
  ORDER BY tasks_month DESC, avg_rating DESC, u.full_name ASC
");
$stmt->execute([
  ":ms3" => $mStart, ":me3" => $mEnd,
  ":ms4" => $mStart, ":me4" => $mEnd,
]);
$techs = $stmt->fetchAll();

// Feedback
$limit = 6;
$stmt = $pdo->prepare("
  SELECT
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
$feedback = $stmt->fetchAll();

/* ===================== PDF OUTPUT ===================== */

$pdf = new FPDF("P", "mm", "A4");
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

// Header
$pdf->SetFont("Arial", "B", 16);
$pdf->Cell(0, 10, "AP Tec - Employee Performance Report", 0, 1, "L");

$pdf->SetFont("Arial", "", 11);
$pdf->Cell(0, 6, "Period: " . date("Y-m-01") . " to " . date("Y-m-t"), 0, 1, "L");
$pdf->Ln(2);

// Summary block
$pdf->SetFont("Arial", "B", 12);
$pdf->Cell(0, 8, "Summary", 0, 1, "L");

$pdf->SetFont("Arial", "", 11);
$pdf->Cell(0, 6, "Top Performer: " . $top["full_name"], 0, 1, "L");
$pdf->Cell(0, 6, "Completed Tasks (Month): " . (string)$top["completed_tasks"], 0, 1, "L");
$pdf->Cell(0, 6, "Avg Rating (Top): " . (string)$top["avg_rating"], 0, 1, "L");
$pdf->Cell(0, 6, "On-Time Completion: " . $onTimePct . "%", 0, 1, "L");
$pdf->Cell(0, 6, "Technicians Active: " . $activeTech . "/" . $totalTech, 0, 1, "L");
$pdf->Ln(4);

// Technician roster table
$pdf->SetFont("Arial", "B", 12);
$pdf->Cell(0, 8, "Technician Roster", 0, 1, "L");

$pdf->SetFont("Arial", "B", 10);
$pdf->Cell(70, 8, "Technician", 1, 0, "L");
$pdf->Cell(30, 8, "Status", 1, 0, "L");
$pdf->Cell(25, 8, "Tasks (Mo)", 1, 0, "C");
$pdf->Cell(25, 8, "Avg Rating", 1, 1, "C");

$pdf->SetFont("Arial", "", 10);
foreach ($techs as $t) {
  $status = status_for_user($t);
  $tasksMo = (string)((int)$t["tasks_month"]);
  $avg = ($t["avg_rating"] !== null) ? (string)$t["avg_rating"] : "-";

  $pdf->Cell(70, 8, $t["full_name"], 1, 0, "L");
  $pdf->Cell(30, 8, $status, 1, 0, "L");
  $pdf->Cell(25, 8, $tasksMo, 1, 0, "C");
  $pdf->Cell(25, 8, $avg, 1, 1, "C");
}

$pdf->Ln(4);

// Feedback section
$pdf->SetFont("Arial", "B", 12);
$pdf->Cell(0, 8, "Recent Customer Feedback", 0, 1, "L");

$pdf->SetFont("Arial", "", 10);
if (!$feedback) {
  $pdf->Cell(0, 6, "No feedback available.", 0, 1, "L");
} else {
  foreach ($feedback as $f) {
    $line1 = ($f["customer_name"] ?? "Customer") . "  |  Rating: " . (string)$f["customer_rating"];
    $line2 = "Technician: " . ($f["technician_name"] ?? "-");
    $line3 = '"' . (string)$f["customer_feedback"] . '"';

    $pdf->SetFont("Arial", "B", 10);
    $pdf->MultiCell(0, 6, $line1);
    $pdf->SetFont("Arial", "", 10);
    $pdf->MultiCell(0, 6, $line2);
    $pdf->MultiCell(0, 6, $line3);
    $pdf->Ln(2);
  }
}

// Output PDF
header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=employee_performance_report.pdf");
$pdf->Output("I", "employee_performance_report.pdf");
exit;