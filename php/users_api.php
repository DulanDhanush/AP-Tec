<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

// Admin & Owner allowed in your UI page
$me = require_login_api(["Admin", "Owner"]);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function j(array $data, int $code = 200): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit;
}

$q    = trim((string)($_GET["q"] ?? ""));
$role = trim((string)($_GET["role"] ?? "All"));

$where = [];
$params = [];

if ($role !== "" && $role !== "All") {
    $where[] = "role = :role";
    $params[":role"] = $role; // must be Admin/Owner/Employee/Customer
}

if ($q !== "") {
    $where[] = "(full_name LIKE :q OR email LIKE :q OR username LIKE :q OR CAST(user_id AS CHAR) LIKE :q)";
    $params[":q"] = "%{$q}%";
}

$sql = "
    SELECT user_id, username, full_name, email, role, status
    FROM users
";
if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY user_id DESC LIMIT 500";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ JS expects json.data
    j(["ok" => true, "data" => $rows]);
} catch (Throwable $e) {
    j(["ok" => false, "error" => "LIST_FAILED", "details" => $e->getMessage()], 500);
}