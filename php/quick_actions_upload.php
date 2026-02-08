<?php
// php/quick_actions_upload.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
$u = require_login_api(["Employee"]);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    j(["ok"=>false,"error"=>"No file uploaded"], 400);
  }

  $name = $_FILES["file"]["name"] ?? "doc";
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $allowed = ["pdf","jpg","jpeg","png","webp"];

  if (!in_array($ext, $allowed, true)) {
    j(["ok"=>false,"error"=>"Only PDF/JPG/PNG/WEBP allowed"], 400);
  }

  $dir = __DIR__ . "/../uploads/employee_docs";
  if (!is_dir($dir)) mkdir($dir, 0777, true);

  $filename = "doc_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
  $dest = $dir . "/" . $filename;

  if (!move_uploaded_file($_FILES["file"]["tmp_name"], $dest)) {
    j(["ok"=>false,"error"=>"Upload failed"], 500);
  }

  j(["ok"=>true, "filename"=>$filename, "path"=>"uploads/employee_docs/".$filename]);

} catch (Throwable $e) {
  j(["ok"=>false,"error"=>"Server error"], 500);
}