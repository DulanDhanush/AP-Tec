<?php
// php/messages_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

// Change this role to match your system (Employee / Technician etc.)
$u = require_login_api("Employee");

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j(array $arr, int $code = 200): void {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

$me = (int)($u["user_id"] ?? 0);
if ($me <= 0) j(["ok" => false, "message" => "Unauthorized"], 401);

$action = (string)($_GET["action"] ?? "");

/* =========================
   CONTACTS
   - shows only users who have chatted with me
========================= */
if ($action === "contacts") {

    // 1) find all distinct other users from messages
    $sql = "
        SELECT DISTINCT other_id
        FROM (
            SELECT receiver_id AS other_id
            FROM messages
            WHERE sender_id = ?
            UNION
            SELECT sender_id AS other_id
            FROM messages
            WHERE receiver_id = ?
        ) x
        ORDER BY other_id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$me, $me]);
    $others = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $items = [];

    foreach ($others as $otherId) {
        $otherId = (int)$otherId;
        if ($otherId <= 0) continue;

        // 2) other user info
        $uStmt = $pdo->prepare("SELECT user_id, full_name, role FROM users WHERE user_id = ? LIMIT 1");
        $uStmt->execute([$otherId]);
        $otherUser = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$otherUser) continue;

        // 3) last message between me and other
        $lSql = "
            SELECT message_text, sent_at
            FROM messages
            WHERE (sender_id = ? AND receiver_id = ?)
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY sent_at DESC, message_id DESC
            LIMIT 1
        ";
        $lStmt = $pdo->prepare($lSql);
        $lStmt->execute([$me, $otherId, $otherId, $me]);
        $last = $lStmt->fetch(PDO::FETCH_ASSOC);

        // 4) unread count
        $cStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM messages
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $cStmt->execute([$otherId, $me]);
        $unread = (int)$cStmt->fetchColumn();

        $items[] = [
            "user_id"       => (int)$otherUser["user_id"],
            "full_name"     => (string)$otherUser["full_name"],
            "role"          => (string)($otherUser["role"] ?? ""),
            "last_message"  => (string)($last["message_text"] ?? ""),
            "last_at"       => (string)($last["sent_at"] ?? ""),
            "unread"        => $unread
        ];
    }

    // sort by latest
    usort($items, function ($a, $b) {
        return strcmp((string)$b["last_at"], (string)$a["last_at"]);
    });

    j(["ok" => true, "items" => $items]);
}

/* =========================
   THREAD (messages with one user)
========================= */
if ($action === "thread") {
    $other = (int)($_GET["user_id"] ?? 0);
    if ($other <= 0) j(["ok" => false, "message" => "Invalid user_id"], 400);

    $stmt = $pdo->prepare("
        SELECT message_id, sender_id, receiver_id, message_text, is_read, sent_at
        FROM messages
        WHERE (sender_id = ? AND receiver_id = ?)
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY sent_at ASC, message_id ASC
        LIMIT 500
    ");
    $stmt->execute([$me, $other, $other, $me]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // mark as read (other -> me)
    $mark = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $mark->execute([$other, $me]);

    j(["ok" => true, "items" => $items]);
}

/* =========================
   SEND
========================= */
if ($action === "send") {
    $receiverId = (int)($_POST["receiver_id"] ?? 0);
    $message = trim((string)($_POST["message"] ?? ""));

    if ($receiverId <= 0) j(["ok" => false, "message" => "Invalid receiver_id"], 400);
    if ($message === "") j(["ok" => false, "message" => "Message is empty"], 400);
    if (mb_strlen($message) > 4000) j(["ok" => false, "message" => "Message too long"], 400);

    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message_text, is_read, sent_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$me, $receiverId, $message]);

    j(["ok" => true, "message" => "Sent", "message_id" => (int)$pdo->lastInsertId()]);
}

j(["ok" => false, "message" => "Unknown action"], 400);