<?php
// php/schedule_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";     // provides $pdo
require_once __DIR__ . "/auth.php";   // provides require_login_api()

// Allow Employee/Technician roles to access (change role name if yours differs)
$u = require_login_api("Employee"); // if your role is "Technician", change to that

header("Content-Type: application/json; charset=utf-8");
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
if ($userId <= 0) j(["ok" => false, "message" => "Unauthorized"], 401);

function must(string $k): string {
    $v = trim((string)($_POST[$k] ?? ""));
    if ($v === "") j(["ok" => false, "message" => "Missing: $k"], 400);
    return $v;
}

function parse_date(string $ymd): string {
    // expects YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) j(["ok" => false, "message" => "Invalid date"], 400);
    return $ymd;
}

function parse_time(string $hm): string {
    // expects HH:MM
    if (!preg_match('/^\d{2}:\d{2}$/', $hm)) j(["ok" => false, "message" => "Invalid time"], 400);
    return $hm;
}

function parse_type(string $t): string {
    $t = strtolower(trim($t));
    $allowed = ["routine","urgent","leave"];
    if (!in_array($t, $allowed, true)) j(["ok" => false, "message" => "Invalid type"], 400);
    return $t;
}

if ($action === "month") {
    // Return event counts per day for calendar dots
    // GET: ?action=month&ym=2026-02
    $ym = (string)($_GET["ym"] ?? "");
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) j(["ok" => false, "message" => "Invalid ym"], 400);

    $start = $ym . "-01 00:00:00";
    $end = (new DateTime($ym . "-01"))->modify("+1 month")->format("Y-m-01 00:00:00");

    $stmt = $pdo->prepare("
        SELECT DATE(start_at) AS day,
               SUM(type='urgent') AS urgent_count,
               SUM(type='routine') AS routine_count,
               SUM(type='leave') AS leave_count,
               COUNT(*) AS total
        FROM schedule_items
        WHERE user_id = :uid
          AND start_at >= :start AND start_at < :end
        GROUP BY DATE(start_at)
        ORDER BY day ASC
    ");
    $stmt->execute([
        ":uid" => $userId,
        ":start" => $start,
        ":end" => $end
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // map day => counts
    $map = [];
    foreach ($rows as $r) {
        $map[$r["day"]] = [
            "urgent" => (int)$r["urgent_count"],
            "routine" => (int)$r["routine_count"],
            "leave" => (int)$r["leave_count"],
            "total" => (int)$r["total"],
        ];
    }

    j(["ok" => true, "ym" => $ym, "days" => $map]);
}

if ($action === "day") {
    // Return schedule items for a specific day
    // GET: ?action=day&date=2026-02-07
    $date = parse_date((string)($_GET["date"] ?? ""));
    $start = $date . " 00:00:00";
    $end = $date . " 23:59:59";

    $stmt = $pdo->prepare("
        SELECT schedule_id, title, note, type, start_at
        FROM schedule_items
        WHERE user_id = :uid AND start_at BETWEEN :start AND :end
        ORDER BY start_at ASC
    ");
    $stmt->execute([
        ":uid" => $userId,
        ":start" => $start,
        ":end" => $end,
    ]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    j(["ok" => true, "date" => $date, "items" => $items]);
}

if ($action === "create") {
    // POST: title, date, time, type, note(optional)
    $title = must("title");
    $date = parse_date(must("date"));
    $time = parse_time(must("time"));
    $type = parse_type((string)($_POST["type"] ?? "routine"));
    $note = trim((string)($_POST["note"] ?? ""));

    $startAt = $date . " " . $time . ":00";

    $stmt = $pdo->prepare("
        INSERT INTO schedule_items (user_id, title, note, type, start_at)
        VALUES (:uid, :title, :note, :type, :start_at)
    ");
    $stmt->execute([
        ":uid" => $userId,
        ":title" => $title,
        ":note" => $note !== "" ? $note : null,
        ":type" => $type,
        ":start_at" => $startAt
    ]);

    j(["ok" => true, "message" => "Saved", "schedule_id" => (int)$pdo->lastInsertId()]);
}

if ($action === "delete") {
    // POST: schedule_id
    $sid = (int)($_POST["schedule_id"] ?? 0);
    if ($sid <= 0) j(["ok" => false, "message" => "Invalid schedule_id"], 400);

    $stmt = $pdo->prepare("DELETE FROM schedule_items WHERE schedule_id = :sid AND user_id = :uid");
    $stmt->execute([":sid" => $sid, ":uid" => $userId]);

    j(["ok" => true, "message" => "Deleted"]);
}

if ($action === "update") {
    // POST: schedule_id, title, date, time, type, note
    $sid = (int)($_POST["schedule_id"] ?? 0);
    if ($sid <= 0) j(["ok" => false, "message" => "Invalid schedule_id"], 400);

    $title = must("title");
    $date = parse_date(must("date"));
    $time = parse_time(must("time"));
    $type = parse_type((string)($_POST["type"] ?? "routine"));
    $note = trim((string)($_POST["note"] ?? ""));

    $startAt = $date . " " . $time . ":00";

    $stmt = $pdo->prepare("
        UPDATE schedule_items
        SET title = :title, note = :note, type = :type, start_at = :start_at
        WHERE schedule_id = :sid AND user_id = :uid
    ");
    $stmt->execute([
        ":title" => $title,
        ":note" => $note !== "" ? $note : null,
        ":type" => $type,
        ":start_at" => $startAt,
        ":sid" => $sid,
        ":uid" => $userId
    ]);

    j(["ok" => true, "message" => "Updated"]);
}

j(["ok" => false, "message" => "Unknown action"], 400);