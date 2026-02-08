<?php
// php/availability_api.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$u = require_login_api(["Employee"]);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j(array $arr, int $code = 200): void {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

$action = (string)($_GET["action"] ?? "");
$userId = (int)($u["user_id"] ?? 0);

if ($action === "get") {
    $stmt = $pdo->prepare("SELECT is_available FROM technician_status WHERE user_id=:uid LIMIT 1");
    $stmt->execute([":uid"=>$userId]);
    $val = $stmt->fetchColumn();
    $isAvail = $val === false ? 1 : (int)$val;
    j(["ok"=>true, "is_available"=>$isAvail]);
}

if ($action === "set") {
    $input = json_decode((string)file_get_contents("php://input"), true);
    if (!is_array($input)) $input = [];
    $isAvail = isset($input["is_available"]) ? (int)((bool)$input["is_available"]) : 1;

    $stmt = $pdo->prepare("
      INSERT INTO technician_status (user_id, is_available)
      VALUES (:uid, :v)
      ON DUPLICATE KEY UPDATE is_available = VALUES(is_available)
    ");
    $stmt->execute([":uid"=>$userId, ":v"=>$isAvail]);

    j(["ok"=>true]);
}

j(["ok"=>false,"error"=>"Invalid action"], 400);