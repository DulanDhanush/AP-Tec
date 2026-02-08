<?php
// php/logs_api.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

require_login_api("Admin");

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$from  = trim((string)($_GET["from"] ?? "")); // YYYY-MM-DD
$to    = trim((string)($_GET["to"] ?? ""));   // YYYY-MM-DD
$level = strtoupper(trim((string)($_GET["level"] ?? "ALL")));
$q     = trim((string)($_GET["q"] ?? ""));

$where = [];
$params = [];

// Date range
if ($from !== "") {
    $where[] = "DATE(sl.created_at) >= :from";
    $params[":from"] = $from;
}
if ($to !== "") {
    $where[] = "DATE(sl.created_at) <= :to";
    $params[":to"] = $to;
}

// Level filter
if ($level !== "" && $level !== "ALL") {
    // UI uses ERROR as "CRITICAL" label, DB is ERROR
    $where[] = "UPPER(sl.level) = :lvl";
    $params[":lvl"] = $level;
}

// Search (log id / user / ip / message / module)
if ($q !== "") {
    $like = "%" . $q . "%";
    $where[] = "(
        CAST(sl.log_id AS CHAR) LIKE :q1 OR
        COALESCE(u.username,'') LIKE :q2 OR
        COALESCE(sl.ip_address,'') LIKE :q3 OR
        COALESCE(sl.module,'') LIKE :q4 OR
        COALESCE(sl.message,'') LIKE :q5
    )";
    $params[":q1"] = $like;
    $params[":q2"] = $like;
    $params[":q3"] = $like;
    $params[":q4"] = $like;
    $params[":q5"] = $like;
}

// Detect if `details` column exists (optional)
$hasDetails = false;
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM system_logs LIKE 'details'")->fetch();
    $hasDetails = $colCheck ? true : false;
} catch (Throwable $e) {
    $hasDetails = false;
}

$detailsSelect = $hasDetails ? "sl.details" : "NULL AS details";

$sql = "
    SELECT
        sl.log_id,
        sl.created_at,
        UPPER(sl.level) AS level,
        sl.module,
        sl.message,
        sl.ip_address,
        sl.user_id,
        COALESCE(u.username, 'SYSTEM') AS username,
        {$detailsSelect}
    FROM system_logs sl
    LEFT JOIN users u ON u.user_id = sl.user_id
";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY sl.created_at DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

echo json_encode(["ok" => true, "data" => $data]);