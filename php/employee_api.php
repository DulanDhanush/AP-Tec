<?php
// php/employee_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

$u = require_login_api("Employee");

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

$action = (string)($_GET["action"] ?? "");
$me = (int)($u["user_id"] ?? 0);
if ($me <= 0) j(["ok"=>false,"message"=>"Unauthorized"], 401);

// Your exact ENUM values from tasks table
const STATUS_ALLOWED = ["Pending","In Progress","Waiting for Parts","Completed","Cancelled"];

const FILTER_MAP = [
  "pending"   => "Pending",
  "progress"  => "In Progress",
  "completed" => "Completed"
];

function must_status(string $s): string {
  $s = trim($s);
  if (!in_array($s, STATUS_ALLOWED, true)) j(["ok"=>false,"message"=>"Invalid status"], 400);
  return $s;
}

/* =========================================================
   AVAILABILITY TABLE (create if not exists) - SAFE
   You can also create manually using SQL, but this auto-fixes.
========================================================= */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS technician_status (
    user_id INT PRIMARY KEY,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP
  )
");

function ensure_tech_status(PDO $pdo, int $uid): void {
  $stmt = $pdo->prepare("INSERT IGNORE INTO technician_status (user_id, is_available) VALUES (?,1)");
  $stmt->execute([$uid]);
}

/* =========================
   GET AVAILABILITY
========================= */
if ($action === "get_availability") {
  ensure_tech_status($pdo, $me);

  $stmt = $pdo->prepare("SELECT is_available FROM technician_status WHERE user_id = ? LIMIT 1");
  $stmt->execute([$me]);
  $is = (int)($stmt->fetchColumn() ?? 1);

  j(["ok"=>true, "is_available"=>$is]);
}

/* =========================
   SET AVAILABILITY
========================= */
if ($action === "set_availability") {
  ensure_tech_status($pdo, $me);

  $is = (int)($_POST["is_available"] ?? 1);
  $is = $is ? 1 : 0;

  $stmt = $pdo->prepare("UPDATE technician_status SET is_available = ? WHERE user_id = ?");
  $stmt->execute([$is, $me]);

  j(["ok"=>true, "message"=>"Updated", "is_available"=>$is]);
}

/* =========================
   LIST MY TASKS
========================= */
if ($action === "list_tasks") {
  $filter = strtolower(trim((string)($_GET["filter"] ?? "all")));

  $sql = "
    SELECT
      t.task_id,
      t.task_reference,
      t.title,
      t.description,
      t.location,
      t.priority,
      t.status,
      t.technician_notes,
      DATE_FORMAT(t.due_date,'%Y-%m-%d') AS due_date,
      u.full_name AS customer_name
    FROM tasks t
    LEFT JOIN users u ON u.user_id = t.customer_id
    WHERE t.assigned_to = ?
  ";
  $params = [$me];

  if ($filter !== "all" && isset(FILTER_MAP[$filter])) {
    $sql .= " AND t.status = ? ";
    $params[] = FILTER_MAP[$filter];
  }

  // Urgent first, then High, then Normal, then Low
  $sql .= "
    ORDER BY
      FIELD(t.priority,'Urgent','High','Normal','Low'),
      COALESCE(t.due_date,'2999-12-31') ASC,
      t.task_id DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  j(["ok"=>true,"items"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

/* =========================
   UPDATE TASK (modal submit)
========================= */
if ($action === "update_task") {
  set_time_limit(0);

  $taskId = (int)($_POST["task_id"] ?? 0);
  if ($taskId <= 0) j(["ok"=>false,"message"=>"Invalid task_id"], 400);

  $status = must_status((string)($_POST["status"] ?? ""));
  $notes  = trim((string)($_POST["notes"] ?? ""));

  // Ensure task belongs to this technician
  $stmt = $pdo->prepare("SELECT task_id FROM tasks WHERE task_id = ? AND assigned_to = ? LIMIT 1");
  $stmt->execute([$taskId, $me]);
  if (!$stmt->fetchColumn()) j(["ok"=>false,"message"=>"Task not found"], 404);

  // proof upload -> saves to tasks.proof_image_path
  $proofPath = null;

  if (isset($_FILES["proof"]) && ($_FILES["proof"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (($_FILES["proof"]["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
      j(["ok"=>false,"message"=>"Proof upload error"], 400);
    }

    $tmp  = (string)$_FILES["proof"]["tmp_name"];
    $orig = (string)($_FILES["proof"]["name"] ?? "proof.jpg");
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) {
      j(["ok"=>false,"message"=>"Proof must be jpg/png/webp"], 400);
    }

    $dir = __DIR__ . "/uploads/proofs";
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $file = "task{$taskId}_tech{$me}_" . date("Ymd_His") . "." . $ext;
    $dest = $dir . "/" . $file;

    if (!move_uploaded_file($tmp, $dest)) {
      if (!copy($tmp, $dest)) j(["ok"=>false,"message"=>"Failed to save proof"], 500);
    }

    // save relative path (used later by UI)
    $proofPath = "php/uploads/proofs/" . $file;
  }

  $stmt = $pdo->prepare("
    UPDATE tasks
    SET status = ?,
        technician_notes = ?,
        proof_image_path = COALESCE(?, proof_image_path)
    WHERE task_id = ? AND assigned_to = ?
  ");
  $stmt->execute([
    $status,
    ($notes !== "" ? $notes : null),
    $proofPath,
    $taskId,
    $me
  ]);

  j(["ok"=>true,"message"=>"Task updated","proof_image_path"=>$proofPath]);
}

j(["ok"=>false,"message"=>"Unknown action"], 400);