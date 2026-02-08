<?php
// php/notifications_api.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$u = require_login_api(["Admin","Owner"]);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$action = (string)($_GET["action"] ?? "");

function j(array $arr, int $code = 200): void {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if ($action === "list") {
    // ✅ Admin sees ALL notifications (system inbox)
    $stmt = $pdo->query("
        SELECT notif_id, user_id, type, title, message, is_read, created_at
        FROM notifications
        ORDER BY created_at DESC
        LIMIT 200
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Count ALL unread
    $unread = (int)$pdo->query("
        SELECT COUNT(*)
        FROM notifications
        WHERE is_read = 0
    ")->fetchColumn();

    j(["ok" => true, "notifications" => $rows, "unread" => $unread]);
}

if ($action === "mark_all_read") {
    // ✅ Mark ALL as read
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE is_read = 0
    ");
    $stmt->execute();

    j(["ok" => true, "updated" => $stmt->rowCount()]);
}

j(["ok" => false, "error" => "Invalid action"], 400);