<?php
// php/user_get.php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

require_login("Admin");

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
    echo json_encode(["ok" => false, "error" => "Invalid id"]);
    exit;
}

$stmt = $pdo->prepare("SELECT user_id, full_name, username, email, role, status FROM users WHERE user_id = :id LIMIT 1");
$stmt->execute([":id" => $id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(["ok" => false, "error" => "User not found"]);
    exit;
}

echo json_encode(["ok" => true, "data" => $user]);