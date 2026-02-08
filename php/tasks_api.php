<?php
// php/tasks_api.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$u = require_login_api(["Employee"]);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$action = (string)($_GET["action"] ?? "");
$userId = (int)($u["user_id"] ?? 0);
if ($userId <= 0) j(["ok"=>false,"error"=>"Not logged in"], 401);

function filterToDbStatus(string $filter): ?string {
  $f = strtolower(trim($filter));
  return match ($f) {
    "pending"   => "Pending",
    "progress"  => "In Progress",
    "waiting"   => "Waiting for Parts",
    "completed" => "Completed",
    default     => null, // all
  };
}

function dbStatusToJs(?string $dbStatus): string {
  $s = strtolower(trim((string)$dbStatus));
  return match ($s) {
    "pending"            => "pending",
    "in progress"        => "progress",
    "waiting for parts"  => "waiting",
    "completed"          => "completed",
    default              => "pending",
  };
}

function dbPriorityToJs(?string $dbPri): string {
  $p = strtolower(trim((string)$dbPri));
  return match ($p) {
    "urgent", "high" => "high",
    "low"            => "low",
    default          => "med", // normal
  };
}

try {
  // Ensure PDO throws exceptions (in case db.php forgot this)
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if ($action === "list") {
    $filter = (string)($_GET["filter"] ?? "all");
    $dbStatus = filterToDbStatus($filter);

    $where = "t.assigned_to = :uid";
    $params = [":uid" => $userId];

    if ($dbStatus !== null) {
      $where .= " AND t.status = :st";
      $params[":st"] = $dbStatus;
    }

    $sql = "
      SELECT
        t.task_id,
        COALESCE(t.task_reference, CONCAT('TSK-', LPAD(t.task_id, 4, '0'))) AS task_code,
        t.title,
        t.location,
        t.description,
        t.priority,
        t.status,
        t.due_date AS due_at,
        t.created_at
      FROM tasks t
      WHERE $where
      ORDER BY
        CASE t.priority
          WHEN 'Urgent' THEN 1
          WHEN 'High'   THEN 2
          WHEN 'Normal' THEN 3
          WHEN 'Low'    THEN 4
          ELSE 5
        END,
        COALESCE(t.due_date, '2099-12-31') ASC,
        t.task_id DESC
      LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
      $r["status"] = dbStatusToJs($r["status"] ?? "Pending");
      $r["priority"] = dbPriorityToJs($r["priority"] ?? "Normal");
    }
    unset($r);

    j(["ok"=>true, "tasks"=>$rows]);
  }

  if ($action === "get") {
    $taskId = (int)($_GET["task_id"] ?? 0);
    if ($taskId <= 0) j(["ok"=>false,"error"=>"Missing task_id"], 400);

    $stmt = $pdo->prepare("
      SELECT
        t.task_id,
        COALESCE(t.task_reference, CONCAT('TSK-', LPAD(t.task_id, 4, '0'))) AS task_code,
        t.title,
        t.location,
        t.description,
        t.priority,
        t.status,
        t.due_date AS due_at,
        t.technician_notes,
        t.proof_image_path
      FROM tasks t
      WHERE t.task_id = :tid AND t.assigned_to = :uid
      LIMIT 1
    ");
    $stmt->execute([":tid"=>$taskId, ":uid"=>$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) j(["ok"=>false,"error"=>"Task not found"], 404);

    $row["status"] = dbStatusToJs($row["status"] ?? "Pending");
    $row["priority"] = dbPriorityToJs($row["priority"] ?? "Normal");

    j(["ok"=>true, "task"=>$row]);
  }

  if ($action === "update") {
    $input = json_decode((string)file_get_contents("php://input"), true);
    if (!is_array($input)) $input = [];

    $taskId = (int)($input["task_id"] ?? 0);
    $statusIn = strtolower(trim((string)($input["status"] ?? "")));
    $notes = trim((string)($input["notes"] ?? ""));

    $dbStatus = match ($statusIn) {
      "pending" => "Pending",
      "in progress", "progress" => "In Progress",
      "waiting", "waiting for parts" => "Waiting for Parts",
      "completed" => "Completed",
      default => "",
    };

    if ($taskId <= 0 || $dbStatus === "") j(["ok"=>false,"error"=>"Invalid data"], 400);

    $stmt = $pdo->prepare("SELECT task_id FROM tasks WHERE task_id=:tid AND assigned_to=:uid LIMIT 1");
    $stmt->execute([":tid"=>$taskId, ":uid"=>$userId]);
    if (!$stmt->fetchColumn()) j(["ok"=>false,"error"=>"Task not found"], 404);

    $stmt = $pdo->prepare("
      UPDATE tasks
      SET status = :st,
          technician_notes = :notes
      WHERE task_id = :tid AND assigned_to = :uid
    ");
    $stmt->execute([
      ":st" => $dbStatus,
      ":notes" => ($notes !== "" ? $notes : null),
      ":tid" => $taskId,
      ":uid" => $userId
    ]);

    j(["ok"=>true]);
  }

  j(["ok"=>false,"error"=>"Invalid action"], 400);

} catch (Throwable $e) {
  // ✅ TEMP: expose the real reason
  error_log("tasks_api.php ERROR: " . $e->getMessage());
  j([
    "ok" => false,
    "error" => "Server error",
    "detail" => $e->getMessage(),   // <-- copy this detail and send me
  ], 500);
}