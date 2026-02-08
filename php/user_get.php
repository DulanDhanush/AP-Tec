<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

$me = require_login_api(["Admin", "Owner"]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function j(array $data, int $code = 200): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit;
}

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) j(["ok" => false, "error" => "BAD_ID"], 400);

try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, full_name, email, role, status
        FROM users
        WHERE user_id = :id
        LIMIT 1
    ");
    $stmt->execute([":id" => $id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) j(["ok" => false, "error" => "NOT_FOUND"], 404);

    j(["ok" => true, "data" => $u]);
} catch (Throwable $e) {
    j(["ok" => false, "error" => "GET_FAILED", "details" => $e->getMessage()], 500);
}