<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/logger.php";

$me = require_login_api(["Admin", "Owner"]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function j(array $data, int $code = 200): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit;
}

$raw = file_get_contents("php://input");
$body = json_decode($raw ?: "{}", true);
if (!is_array($body)) j(["ok" => false, "error" => "INVALID_JSON"], 400);

$id = (int)($body["id"] ?? 0);
$status = trim((string)($body["status"] ?? ""));

if ($id <= 0) j(["ok" => false, "error" => "BAD_ID"], 400);

// allow statuses your UI uses
$allowedStatuses = ["Active", "Inactive", "Banned"];
if (!in_array($status, $allowedStatuses, true)) {
    j(["ok" => false, "error" => "INVALID_STATUS"], 400);
}

// prevent self-deactivate
if ((int)$me["user_id"] === $id) {
    j(["ok" => false, "error" => "CANNOT_CHANGE_SELF"], 400);
}

try {
    $stmt = $pdo->prepare("UPDATE users SET status = :s WHERE user_id = :id");
    $stmt->execute([":s" => $status, ":id" => $id]);

    $lvl = ($status === "Active") ? "INFO" : "WARNING";
    log_event($pdo, $lvl, "Users", "Changed user #{$id} status to {$status}", (int)$me["user_id"]);

    j(["ok" => true]);
} catch (Throwable $e) {
    j(["ok" => false, "error" => "STATUS_FAILED", "details" => $e->getMessage()], 500);
}