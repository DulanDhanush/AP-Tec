<?php
require_once __DIR__ . "/db.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function j($a, $c = 200) {
  http_response_code($c);
  echo json_encode($a);
  exit;
}

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

/** DEBUG */
if ($action === "debug_session") {
  j([
    "ok" => true,
    "session_user_id" => $_SESSION["user_id"] ?? null,
    "session_role" => $_SESSION["role"] ?? null,
    "session" => $_SESSION
  ]);
}

/** ME */
if ($action === "me") {
  j(["ok" => true, "user_id" => $userId, "role" => $role]);
}

function find_existing_convo_for_customer($customerId) {
  global $pdo;

  $st = $pdo->prepare("
    SELECT cm1.convo_id,
           u.user_id AS agent_id,
           u.full_name AS agent_name,
           u.avatar_initials,
           u.avatar_color
    FROM conversation_members cm1
    JOIN conversation_members cm2
      ON cm2.convo_id = cm1.convo_id AND cm2.user_id <> cm1.user_id
    JOIN users u ON u.user_id = cm2.user_id
    WHERE cm1.user_id = ?
      AND u.role = 'Employee'
    ORDER BY cm1.convo_id DESC
    LIMIT 1
  ");
  $st->execute([(int)$customerId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  return $row ? $row : null;
}

function pick_available_agent() {
  global $pdo;

  $st = $pdo->query("
    SELECT u.user_id AS agent_id,
           u.full_name AS agent_name,
           u.avatar_initials,
           u.avatar_color
    FROM users u
    JOIN technician_status ts ON ts.user_id = u.user_id
    WHERE u.role = 'Employee'
      AND u.status = 'Active'
      AND ts.is_available = 1
    ORDER BY ts.updated_at DESC
    LIMIT 1
  ");
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) return $row;

  $st = $pdo->query("
    SELECT u.user_id AS agent_id,
           u.full_name AS agent_name,
           u.avatar_initials,
           u.avatar_color
    FROM users u
    WHERE u.role = 'Employee'
      AND u.status = 'Active'
    ORDER BY u.user_id ASC
    LIMIT 1
  ");
  $row = $st->fetch(PDO::FETCH_ASSOC);

  return $row ? $row : null;
}

function create_conversation_with_agent($customerId, $agentId) {
  global $pdo;

  $pdo->beginTransaction();
  try {
    $pdo->exec("INSERT INTO conversations (created_at) VALUES (CURRENT_TIMESTAMP)");
    $convoId = (int)$pdo->lastInsertId();

    $st = $pdo->prepare("INSERT INTO conversation_members (convo_id, user_id) VALUES (?, ?)");
    $st->execute([(int)$convoId, (int)$customerId]);
    $st->execute([(int)$convoId, (int)$agentId]);

    $pdo->commit();
    return $convoId;
  } catch (Exception $e) {
    $pdo->rollBack();
    j(["ok"=>false,"error"=>"Failed to create conversation"], 500);
  }
}

/** START */
if ($action === "start") {
  if (strtolower($role) !== "customer") j(["ok"=>false,"error"=>"Forbidden"], 403);

  if (!empty($_SESSION["chat_agent_id"]) && !empty($_SESSION["chat_convo_id"])) {
    $agentId = (int)$_SESSION["chat_agent_id"];
    $convoId = (int)$_SESSION["chat_convo_id"];

    $st = $pdo->prepare("
      SELECT user_id AS agent_id, full_name AS agent_name, avatar_initials, avatar_color
      FROM users
      WHERE user_id=? AND role='Employee'
    ");
    $st->execute([$agentId]);
    $agent = $st->fetch(PDO::FETCH_ASSOC);
    if ($agent) j(["ok"=>true,"convo_id"=>$convoId,"agent"=>$agent]);
  }

  $existing = find_existing_convo_for_customer($userId);
  if ($existing) {
    $_SESSION["chat_convo_id"] = (int)$existing["convo_id"];
    $_SESSION["chat_agent_id"] = (int)$existing["agent_id"];
    j([
      "ok"=>true,
      "convo_id" => (int)$existing["convo_id"],
      "agent" => [
        "agent_id" => (int)$existing["agent_id"],
        "agent_name" => $existing["agent_name"],
        "avatar_initials" => $existing["avatar_initials"],
        "avatar_color" => $existing["avatar_color"],
      ]
    ]);
  }

  $agent = pick_available_agent();
  if (!$agent) j(["ok"=>false,"error"=>"No employees available"], 503);

  $convoId = create_conversation_with_agent($userId, (int)$agent["agent_id"]);

  $_SESSION["chat_convo_id"] = $convoId;
  $_SESSION["chat_agent_id"] = (int)$agent["agent_id"];

  j(["ok"=>true, "convo_id"=>$convoId, "agent"=>$agent]);
}

/** HISTORY */
if ($action === "history") {
  if (strtolower($role) !== "customer") j(["ok"=>false,"error"=>"Forbidden"], 403);

  $agentId = (int)($_SESSION["chat_agent_id"] ?? 0);
  if ($agentId <= 0) j(["ok"=>false,"error"=>"Chat not started"], 409);

  $st = $pdo->prepare("
    SELECT message_id, sender_id, receiver_id, message_text, is_read, sent_at
    FROM messages
    WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
    ORDER BY message_id ASC
    LIMIT 500
  ");
  $st->execute([$userId, $agentId, $agentId, $userId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
      ->execute([$agentId, $userId]);

  j(["ok"=>true, "messages"=>$rows]);
}

/** SEND (returns id + time) */
if ($action === "send") {
  if (strtolower($role) !== "customer") j(["ok"=>false,"error"=>"Forbidden"], 403);

  $agentId = (int)($_SESSION["chat_agent_id"] ?? 0);
  $text = trim((string)($body["message"] ?? ""));
  if ($agentId <= 0) j(["ok"=>false,"error"=>"Chat not started"], 409);
  if ($text === "") j(["ok"=>false,"error"=>"Empty message"], 422);

  $st = $pdo->prepare("
    INSERT INTO messages (sender_id, receiver_id, message_text, is_read)
    VALUES (?, ?, ?, 0)
  ");
  $st->execute([$userId, $agentId, $text]);

  $newId = (int)$pdo->lastInsertId();

  $st = $pdo->prepare("SELECT sent_at FROM messages WHERE message_id=?");
  $st->execute([$newId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  $sentAt = $row ? $row["sent_at"] : date("Y-m-d H:i:s");

  j(["ok"=>true, "message_id"=>$newId, "sent_at"=>$sentAt]);
}

/** POLL */
if ($action === "poll") {
  if (strtolower($role) !== "customer") j(["ok"=>false,"error"=>"Forbidden"], 403);

  $agentId = (int)($_SESSION["chat_agent_id"] ?? 0);
  $lastId  = (int)($_GET["last_id"] ?? 0);
  if ($agentId <= 0) j(["ok"=>false,"error"=>"Chat not started"], 409);

  $st = $pdo->prepare("
    SELECT message_id, sender_id, receiver_id, message_text, is_read, sent_at
    FROM messages
    WHERE message_id > ?
      AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
    ORDER BY message_id ASC
    LIMIT 200
  ");
  $st->execute([$lastId, $userId, $agentId, $agentId, $userId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
      ->execute([$agentId, $userId]);

  j(["ok"=>true, "messages"=>$rows]);
}

j(["ok"=>false,"error"=>"Unknown action"], 400);