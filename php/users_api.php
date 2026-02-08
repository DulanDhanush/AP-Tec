<?php
// php/users_api.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

require_login_api("Admin");

// Always JSON
header("Content-Type: application/json; charset=utf-8");

// no-cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$q    = trim((string)($_GET["q"] ?? ""));
$role = trim((string)($_GET["role"] ?? "All"));

$where  = [];
$params = [];

// ✅ Use separate placeholders (prevents HY093)
if ($q !== "") {
    $where[] = "(
        full_name LIKE :q1 OR
        username  LIKE :q2 OR
        email     LIKE :q3 OR
        CAST(user_id AS CHAR) LIKE :q4
    )";
    $like = "%" . $q . "%";
    $params[":q1"] = $like;
    $params[":q2"] = $like;
    $params[":q3"] = $like;
    $params[":q4"] = $like;
}

if ($role !== "" && $role !== "All") {
    $where[] = "role = :role";
    $params[":role"] = $role;
}

$sql = "SELECT user_id, full_name, username, email, role, status FROM users";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY user_id DESC LIMIT 500";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(["ok" => true, "data" => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "Server error",
        // ✅ uncomment this line only while debugging
        // "debug" => $e->getMessage()
    ]);
}