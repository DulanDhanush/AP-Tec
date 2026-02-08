<?php
require_once __DIR__ . "/db.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function j($a, $c = 200) { http_response_code($c); echo json_encode($a); exit; }
function read_json() {
  $raw = file_get_contents("php://input");
  $d = json_decode($raw ? $raw : "{}", true);
  return is_array($d) ? $d : [];
}

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = (int)($_SESSION["user_id"] ?? 0);
$role   = (string)($_SESSION["role"] ?? "");

$action = (string)($_GET["action"] ?? "");
$body   = read_json();

global $pdo;

if ($userId <= 0) j(["ok"=>false,"error"=>"Unauthorized"], 401);

$roleNorm = strtolower(trim($role));
if ($roleNorm !== "employee") j(["ok"=>false,"error"=>"Forbidden"], 403);

/** ME */
if ($action === "me") {
  j(["ok"=>true, "user_id"=>$userId, "role"=>$role]);
}

/** Employee inbox: list customers who chat with this employee */
if ($action === "inbox") {
  $st = $pdo->prepare("
    SELECT cmE.convo_id,
           u.user_id AS customer_id,
           u.full_name AS customer_name,
           u.avatar_initials,
           u.avatar_color
    FROM conversation_members cmE
    JOIN conversation_members cmC ON cmC.convo_id = cmE.convo_id AND cmC.user_id <> cmE.user_id
    JOIN users u ON u.user_id = cmC.user_id
    WHERE cmE.user_id = ?
      AND u.role = 'Customer'
    ORDER BY cmE.convo_id DESC
  ");
  $st->execute([$userId]);
  j(["ok"=>true, "conversations"=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}

/** Employee select a customer to chat with (store in session) */
if ($action === "select") {
  $customerId = (int)($body["customer_id"] ?? 0);
  if ($customerId <= 0) j(["ok"=>false,"error"=>"customer_id required"], 422);

  // verify conversation exists between employee and that customer
  $st = $pdo->prepare("
    SELECT cm1.convo_id
    FROM conversation_members cm1
    JOIN conversation_members cm2 ON cm2.convo_id = cm1.convo_id
    WHERE cm1.user_id = ?
      AND cm2.user_id = ?
    LIMIT 1
  ");
  $st->execute([$userId, $customerId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) j(["ok"=>false,"error"=>"No conversation with this customer"], 404);

  $_SESSION["emp_chat_customer_id"] = $customerId;
  j(["ok"=>true, "customer_id"=>$customerId]);
}

/** Employee history (uses selected customer if not provided) */
if ($action === "history") {
  $customerId = (int)($_GET["customer_id"] ?? ($_SESSION["emp_chat_customer_id"] ?? 0));
  if ($customerId <= 0) j(["ok"=>false,"error"=>"customer_id required"], 422);

  $st = $pdo->prepare("
    SELECT message_id, sender_id, receiver_id, message_text, is_read, sent_at
    FROM messages
    WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
    ORDER BY message_id ASC
    LIMIT 500
  ");
  $st->execute([$userId, $customerId, $customerId, $userId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // mark customer->employee read
  $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
      ->execute([$customerId, $userId]);

  j(["ok"=>true, "customer_id"=>$customerId, "messages"=>$rows]);
}

/** Employee send */
if ($action === "send") {
  $customerId = (int)($body["customer_id"] ?? ($_SESSION["emp_chat_customer_id"] ?? 0));
  $text = trim((string)($body["message"] ?? ""));
  if ($customerId <= 0) j(["ok"=>false,"error"=>"customer_id required"], 422);
  if ($text === "") j(["ok"=>false,"error"=>"Empty message"], 422);

  $st = $pdo->prepare("
    INSERT INTO messages (sender_id, receiver_id, message_text, is_read)
    VALUES (?, ?, ?, 0)
  ");
  $st->execute([$userId, $customerId, $text]);

  $newId = (int)$pdo->lastInsertId();
  $st = $pdo->prepare("SELECT sent_at FROM messages WHERE message_id=?");
  $st->execute([$newId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  $sentAt = $row ? $row["sent_at"] : date("Y-m-d H:i:s");

  j(["ok"=>true, "message_id"=>$newId, "sent_at"=>$sentAt]);
}

/** Employee poll */
if ($action === "poll") {
  $customerId = (int)($_GET["customer_id"] ?? ($_SESSION["emp_chat_customer_id"] ?? 0));
  $lastId = (int)($_GET["last_id"] ?? 0);
  if ($customerId <= 0) j(["ok"=>false,"error"=>"customer_id required"], 422);

  $st = $pdo->prepare("
    SELECT message_id, sender_id, receiver_id, message_text, is_read, sent_at
    FROM messages
    WHERE message_id > ?
      AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
    ORDER BY message_id ASC
    LIMIT 200
  ");
  $st->execute([$lastId, $userId, $customerId, $customerId, $userId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // mark customer->employee read
  $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
      ->execute([$customerId, $userId]);

  j(["ok"=>true, "messages"=>$rows]);
}

j(["ok"=>false,"error"=>"Unknown action"], 400);