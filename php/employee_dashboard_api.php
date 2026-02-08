<?php
// php/employee_dashboard_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

$u = require_login_api(["Employee"]);

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

$action = (string)($_GET["action"] ?? "");

function mapTaskStatusToUpdateEnum(string $status): string {
  // tasks.status: 'Pending','In Progress','Waiting for Parts','Completed','Cancelled'
  return match ($status) {
    "Pending" => "pending",
    "In Progress" => "progress",
    "Waiting for Parts" => "waiting_parts",
    "Completed" => "completed",
    default => "pending"
  };
}

function safeFileName(string $name): string {
  $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
  return $name ?: "upload";
}

if ($action === "me") {
  // availability
  $stmt = $pdo->prepare("SELECT is_available FROM technician_status WHERE user_id = ? LIMIT 1");
  $stmt->execute([$me]);
  $isAvail = $stmt->fetchColumn();
  $isAvail = $isAvail === false ? 1 : (int)$isAvail;

  // unread messages
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
  $stmt->execute([$me]);
  $unread = (int)$stmt->fetchColumn();

  j([
    "ok" => true,
    "user" => [
      "user_id" => $me,
      "username" => (string)($u["username"] ?? ""),
      "full_name" => (string)($u["full_name"] ?? ""),
      "role" => (string)($u["role"] ?? ""),
      "is_available" => $isAvail,
      "unread_messages" => $unread
    ]
  ]);
}

if ($action === "tasks") {
  // tasks assigned to this technician
  $stmt = $pdo->prepare("
    SELECT
      t.task_id,
      t.task_reference,
      t.title,
      t.description,
      t.status,
      t.priority,
      t.due_date,
      t.location,
      t.technician_notes,
      t.proof_image_path,
      t.created_at,
      c.full_name AS customer_name
    FROM tasks t
    LEFT JOIN users c ON c.user_id = t.customer_id
    WHERE t.assigned_to = :me
    ORDER BY
      FIELD(t.status,'Pending','In Progress','Waiting for Parts','Completed','Cancelled'),
      t.due_date IS NULL, t.due_date ASC,
      t.created_at DESC
  ");
  $stmt->execute([":me" => $me]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  j(["ok"=>true, "tasks"=>$rows]);
}

if ($action === "route") {
  // Today's schedule (schedule_events)
  $stmt = $pdo->prepare("
    SELECT event_id, title, type, start_date, start_time, end_time, location, description
    FROM schedule_events
    WHERE user_id = :me AND start_date = CURDATE()
    ORDER BY start_time IS NULL, start_time ASC
  ");
  $stmt->execute([":me" => $me]);
  $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

  j(["ok"=>true, "events"=>$events]);
}

if ($action === "toggle_availability" && $_SERVER["REQUEST_METHOD"] === "POST") {
  $isAvail = (int)($_POST["is_available"] ?? -1);
  if (!in_array($isAvail, [0,1], true)) j(["ok"=>false,"message"=>"Invalid availability"], 400);

  // upsert
  $stmt = $pdo->prepare("
    INSERT INTO technician_status (user_id, is_available)
    VALUES (:uid, :a)
    ON DUPLICATE KEY UPDATE is_available = VALUES(is_available)
  ");
  $stmt->execute([":uid"=>$me, ":a"=>$isAvail]);

  j(["ok"=>true, "is_available"=>$isAvail]);
}

if ($action === "update_task" && $_SERVER["REQUEST_METHOD"] === "POST") {
  $taskId = (int)($_POST["task_id"] ?? 0);
  $newStatus = (string)($_POST["status"] ?? "");
  $notes = (string)($_POST["notes"] ?? "");

  if ($taskId <= 0) j(["ok"=>false,"message"=>"Invalid task"], 400);

  $allowedStatuses = ["Pending","In Progress","Waiting for Parts","Completed","Cancelled"];
  if (!in_array($newStatus, $allowedStatuses, true)) {
    j(["ok"=>false,"message"=>"Invalid status"], 400);
  }

  // ensure task belongs to technician
  $stmt = $pdo->prepare("SELECT task_reference FROM tasks WHERE task_id = ? AND assigned_to = ? LIMIT 1");
  $stmt->execute([$taskId, $me]);
  $taskRef = $stmt->fetchColumn();
  if ($taskRef === false) j(["ok"=>false,"message"=>"Task not found or not assigned to you"], 404);

  // handle optional upload
  $proofPath = null;
  if (!empty($_FILES["proof"]) && is_array($_FILES["proof"])) {
    if ($_FILES["proof"]["error"] === UPLOAD_ERR_OK) {
      $tmp = $_FILES["proof"]["tmp_name"];
      $orig = (string)$_FILES["proof"]["name"];
      $size = (int)$_FILES["proof"]["size"];

      if ($size > 6 * 1024 * 1024) j(["ok"=>false,"message"=>"Image too large (max 6MB)"], 400);

      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($tmp);
      $ext = match ($mime) {
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        default => ""
      };
      if ($ext === "") j(["ok"=>false,"message"=>"Only JPG/PNG/WEBP allowed"], 400);

      $dirFs = realpath(__DIR__ . "/../uploads");
      if ($dirFs === false) {
        // create uploads
        @mkdir(__DIR__ . "/../uploads", 0777, true);
        $dirFs = realpath(__DIR__ . "/../uploads");
      }
      if ($dirFs === false) j(["ok"=>false,"message"=>"Uploads folder not available"], 500);

      $safe = safeFileName(pathinfo($orig, PATHINFO_FILENAME));
      $fileName = "task_{$taskId}_" . date("Ymd_His") . "_{$safe}.{$ext}";
      $destFs = $dirFs . DIRECTORY_SEPARATOR . $fileName;

      if (!move_uploaded_file($tmp, $destFs)) {
        j(["ok"=>false,"message"=>"Upload failed"], 500);
      }

      // path stored for web usage
      $proofPath = "uploads/" . $fileName;
    }
  }

  // update task record
  $sql = "UPDATE tasks SET status = :s, technician_notes = :n";
  $params = [":s"=>$newStatus, ":n"=>$notes, ":id"=>$taskId, ":me"=>$me];
  if ($proofPath !== null) {
    $sql .= ", proof_image_path = :p";
    $params[":p"] = $proofPath;
  }
  $sql .= " WHERE task_id = :id AND assigned_to = :me";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  // insert task_updates history
  $updateEnum = mapTaskStatusToUpdateEnum($newStatus);
  $stmt = $pdo->prepare("
    INSERT INTO task_updates (task_id, technician_id, status, notes, proof_image)
    VALUES (:tid, :uid, :st, :nt, :pf)
  ");
  $stmt->execute([
    ":tid" => $taskId,
    ":uid" => $me,
    ":st" => $updateEnum,
    ":nt" => $notes,
    ":pf" => $proofPath
  ]);

  j(["ok"=>true, "message"=>"Task updated"]);
}

j(["ok"=>false,"message"=>"Unknown action"], 400);