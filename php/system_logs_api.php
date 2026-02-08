<?php
declare(strict_types=1);

// ✅ prevent PHP warnings from breaking JSON
ini_set("display_errors", "0");

require_once __DIR__ . "/../php/auth.php";
require_once __DIR__ . "/../php/db.php";

$action = (string)($_GET["action"] ?? "");

if ($action !== "") {
    $u = require_login_api(["admin", "owner"]);

    function j(array $arr, int $code = 200): void {
        http_response_code($code);
        header("Content-Type: application/json; charset=utf-8"); // ✅ add this
        echo json_encode($arr);
        exit;
    }

    if ($action === "list") {
        $from  = trim((string)($_GET["from"] ?? ""));
        $to    = trim((string)($_GET["to"] ?? ""));
        $level = strtoupper(trim((string)($_GET["level"] ?? "ALL")));
        $q     = trim((string)($_GET["q"] ?? ""));
        $limit = (int)($_GET["limit"] ?? 200);
        if ($limit <= 0 || $limit > 1000) $limit = 200;

        $where = [];
        $params = [];

        if ($from !== "") { $where[] = "created_at >= :from"; $params[":from"] = $from." 00:00:00"; }
        if ($to !== "")   { $where[] = "created_at <= :to";   $params[":to"]   = $to." 23:59:59"; }

        if ($level !== "" && $level !== "ALL") {
            $where[] = "level = :lvl";
            $params[":lvl"] = $level;
        }

        if ($q !== "") {
            $where[] = "(CAST(log_id AS CHAR) LIKE :q OR username LIKE :q OR message LIKE :q OR ip_address LIKE :q)";
            $params[":q"] = "%".$q."%";
        }

        $whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

        $sql = "
            SELECT log_id, created_at, level, module, message, username, full_name, user_id, ip_address
            FROM system_logs
            $whereSql
            ORDER BY created_at DESC
            LIMIT $limit
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            j(["ok" => true, "rows" => $rows]);
        } catch (Throwable $e) {
            j(["ok" => false, "error" => "DB_ERROR", "detail" => $e->getMessage()], 500);
        }
    }

    j(["ok" => false, "error" => "INVALID_ACTION"], 400);
}

// Normal HTML mode...
$u = require_login(["Admin", "Owner"]);