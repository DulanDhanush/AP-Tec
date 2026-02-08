<?php
// php/system_logs_api.php
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

$action = (string)($_GET["action"] ?? "list");
if ($action !== "list") {
    j(["ok" => false, "error" => "Invalid action"], 400);
}

// Filters
$from  = trim((string)($_GET["from"] ?? ""));   // YYYY-MM-DD
$to    = trim((string)($_GET["to"] ?? ""));     // YYYY-MM-DD
$level = strtoupper(trim((string)($_GET["level"] ?? "ALL")));
$q     = trim((string)($_GET["q"] ?? ""));

// Paging (optional)
$limit  = (int)($_GET["limit"] ?? 200);
$offset = (int)($_GET["offset"] ?? 0);
if ($limit <= 0 || $limit > 500) $limit = 200;
if ($offset < 0) $offset = 0;

// Build SQL safely
$where = [];
$params = [];

if ($from !== "") {
    $where[] = "sl.created_at >= :fromdt";
    $params[":fromdt"] = $from . " 00:00:00";
}
if ($to !== "") {
    // include whole 'to' day by using < next day
    $where[] = "sl.created_at < DATE_ADD(:todt, INTERVAL 1 DAY)";
    $params[":todt"] = $to . " 00:00:00";
}
if ($level !== "" && $level !== "ALL") {
    if (!in_array($level, ["INFO","WARNING","ERROR"], true)) {
        j(["ok" => false, "error" => "Invalid level"], 400);
    }
    $where[] = "sl.level = :lvl";
    $params[":lvl"] = $level;
}

if ($q !== "") {
    // Search: log_id exact / user / message / module / ip
    $where[] = "(sl.log_id = :qid OR u.username LIKE :qlike OR u.full_name LIKE :qlike OR sl.module LIKE :qlike OR sl.message LIKE :qlike OR sl.ip_address LIKE :qlike)";
    $params[":qid"] = ctype_digit($q) ? (int)$q : 0;
    $params[":qlike"] = "%" . $q . "%";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$sql = "
    SELECT
        sl.log_id,
        sl.level,
        sl.module,
        sl.message,
        sl.ip_address,
        sl.created_at,
        sl.user_id,
        u.username,
        u.full_name
    FROM system_logs sl
    LEFT JOIN users u ON u.user_id = sl.user_id
    $whereSql
    ORDER BY sl.created_at DESC, sl.log_id DESC
    LIMIT :limit OFFSET :offset
";

try {
    $stmt = $pdo->prepare($sql);

    foreach ($params as $k => $v) {
        if ($k === ":qid") {
            $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($k, $v);
        }
    }
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    j(["ok" => true, "rows" => $rows]);
} catch (Throwable $e) {
    j(["ok" => false, "error" => "DB error: " . $e->getMessage()], 500);
}