<?php
// php/user_status.php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";
$loggerPath = __DIR__ . "/logger.php";
if (file_exists($loggerPath)) require_once $loggerPath;

$admin = require_login("Admin");

$input = json_decode(file_get_contents("php://input"), true) ?? [];
$id = (int)($input["id"] ?? 0);
$newStatus = trim((string)($input["status"] ?? "Inactive"));

$allowed = ["Active","Inactive","Banned"];
if ($id <= 0 || !in_array($newStatus, $allowed, true)) {
    echo json_encode(["ok" => false, "error" => "Invalid request"]);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET status = :s WHERE user_id = :id");
$stmt->execute([":s" => $newStatus, ":id" => $id]);

if (function_exists("log_event")) {
    log_event($pdo, "WARNING", "UserMgmt", "Admin {$admin['username']} set user #{$id} status to {$newStatus}", $admin["user_id"]);
}

echo json_encode(["ok" => true, "message" => "Status updated"]);