<?php
// php/task_proof_upload.php
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
  $taskId = (int)($_POST["task_id"] ?? 0);
  if ($taskId <= 0) j(["ok"=>false,"error"=>"Missing task_id"], 400);

  if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    j(["ok"=>false,"error"=>"No file uploaded"], 400);
  }

  // ensure task belongs to employee
  $stmt = $pdo->prepare("SELECT task_id FROM tasks WHERE task_id=:tid AND assigned_to=:uid LIMIT 1");
  $stmt->execute([":tid"=>$taskId, ":uid"=>$userId]);
  if (!$stmt->fetchColumn()) j(["ok"=>false,"error"=>"Task not found"], 404);

  $tmp = $_FILES["file"]["tmp_name"];
  $name = $_FILES["file"]["name"] ?? "proof.jpg";
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $allowed = ["jpg","jpeg","png","webp"];

  if (!in_array($ext, $allowed, true)) {
    j(["ok"=>false,"error"=>"Only JPG/PNG/WEBP allowed"], 400);
  }

  $dir = __DIR__ . "/../uploads/task_proofs";
  if (!is_dir($dir)) mkdir($dir, 0777, true);

  $filename = "task_" . $taskId . "_" . time() . "." . $ext;
  $dest = $dir . "/" . $filename;

  if (!move_uploaded_file($tmp, $dest)) {
    j(["ok"=>false,"error"=>"Upload failed"], 500);
  }

  $relPath = "uploads/task_proofs/" . $filename;

  $stmt = $pdo->prepare("UPDATE tasks SET proof_image_path=:p WHERE task_id=:tid AND assigned_to=:uid");
  $stmt->execute([":p"=>$relPath, ":tid"=>$taskId, ":uid"=>$userId]);

  j(["ok"=>true, "path"=>$relPath, "filename"=>$filename]);

} catch (Throwable $e) {
  j(["ok"=>false,"error"=>"Server error"], 500);
}