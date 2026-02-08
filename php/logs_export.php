<?php
// php/logs_export.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

require_login("Admin"); // export is a normal request (not fetch), redirect is OK

$from  = trim((string)($_GET["from"] ?? ""));
$to    = trim((string)($_GET["to"] ?? ""));
$level = strtoupper(trim((string)($_GET["level"] ?? "ALL")));
$q     = trim((string)($_GET["q"] ?? ""));

$where = [];
$params = [];

if ($from !== "") { $where[] = "DATE(sl.created_at) >= :from"; $params[":from"] = $from; }
if ($to !== "")   { $where[] = "DATE(sl.created_at) <= :to";   $params[":to"] = $to; }

if ($level !== "" && $level !== "ALL") {
    $where[] = "UPPER(sl.level) = :lvl";
    $params[":lvl"] = $level;
}

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

$sql = "
    SELECT
        sl.created_at,
        UPPER(sl.level) AS level,
        sl.module,
        sl.message,
        COALESCE(u.username, 'SYSTEM') AS username,
        COALESCE(sl.ip_address,'') AS ip_address
    FROM system_logs sl
    LEFT JOIN users u ON u.user_id = sl.user_id
";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY sl.created_at DESC LIMIT 2000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=system_logs.csv");

$out = fopen("php://output", "w");
fputcsv($out, ["Timestamp","Level","Module","Message","User","IP"]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r["created_at"],
        $r["level"],
        $r["module"],
        $r["message"],
        $r["username"],
        $r["ip_address"]
    ]);
}
fclose($out);
exit;