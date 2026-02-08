<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$u = require_login_api(["Admin","Owner"]);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

$userId = (int)($u["user_id"] ?? 0);
if ($userId <= 0) j(["ok"=>false,"error"=>"Unauthorized"], 401);

$stmt = $pdo->prepare("SELECT user_id, username, full_name, role FROM users WHERE user_id = :uid LIMIT 1");
$stmt->execute([":uid" => $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) j(["ok"=>false,"error"=>"User not found"], 404);

$display = trim((string)($row["username"] ?? ""));
if ($display === "") $display = trim((string)($row["full_name"] ?? "User"));

j([
  "ok" => true,
  "user" => [
    "user_id" => (int)$row["user_id"],
    "display_name" => $display,
    "role" => (string)($row["role"] ?? "")
  ]
]);