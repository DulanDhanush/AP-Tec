<?php
// php/route_api.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$u = require_login_api(["Employee"]);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$userId = (int)($u["user_id"] ?? 0);
if ($userId <= 0) j(["ok"=>false,"error"=>"Not logged in"], 401);

try {
  // tasks due today for this employee
  $stmt = $pdo->prepare("
    SELECT
      COALESCE(task_reference, CONCAT('TSK-', LPAD(task_id, 4, '0'))) AS ticket,
      title,
      location,
      status,
      due_date
    FROM tasks
    WHERE assigned_to = :uid
      AND due_date = CURDATE()
      AND status NOT IN ('Cancelled','Completed')
    ORDER BY
      CASE priority
        WHEN 'Urgent' THEN 1
        WHEN 'High' THEN 2
        WHEN 'Normal' THEN 3
        WHEN 'Low' THEN 4
        ELSE 5
      END,
      task_id DESC
    LIMIT 20
  ");
  $stmt->execute([":uid"=>$userId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // If no due times exist, we generate simple timeline times:
  $baseTimes = ["09:00 AM","10:30 AM","02:00 PM","03:30 PM","05:00 PM"];
  $items = [];
  $i = 0;

  // Always add Check-in as first
  $items[] = [
    "time" => "09:00 AM",
    "title" => "Check-in",
    "sub" => "Head Office",
    "active" => true
  ];

  foreach ($rows as $r) {
    $i++;
    $items[] = [
      "time" => $baseTimes[min($i, count($baseTimes)-1)],
      "title" => (string)$r["location"] ?: "Customer Site",
      "sub" => (string)$r["title"],
      "active" => false
    ];
  }

  j(["ok"=>true, "items"=>$items]);

} catch (Throwable $e) {
  j(["ok"=>false,"error"=>"Server error"], 500);
}