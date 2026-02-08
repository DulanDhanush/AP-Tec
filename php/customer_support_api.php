<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function j(array $data, int $code=200): void {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

function read_json(): array {
  $raw = file_get_contents("php://input");
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

/** must be logged in customer */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "Customer") {
  j(["ok"=>false, "error"=>"Unauthorized"], 401);
}

$customerId = (int)$_SESSION["user_id"];
$action = (string)($_GET["action"] ?? "");
$body = read_json();

global $pdo;

/** helper: ensure ticket belongs to this customer */
function assert_owns_ticket(int $ticketId, int $customerId): void {
  global $pdo;
  $st = $pdo->prepare("SELECT ticket_id FROM support_tickets WHERE ticket_id=? AND customer_id=?");
  $st->execute([$ticketId, $customerId]);
  if (!$st->fetch()) j(["ok"=>false, "error"=>"Ticket not found"], 404);
}

/** make TCK-0001 from ticket_id */
function make_ref(int $id): string {
  return "TCK-" . str_pad((string)$id, 4, "0", STR_PAD_LEFT);
}

/** ============ STATS ============ */
if ($action === "stats") {
  // Open tickets = Open or In Progress
  $st = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM support_tickets
    WHERE customer_id=? AND status IN ('Open','In Progress')
  ");
  $st->execute([$customerId]);
  $openCount = (int)($st->fetch()["c"] ?? 0);

  // avg minutes between ticket created_at and first Agent message
  $st = $pdo->prepare("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, m.created_at)) AS avg_mins
    FROM support_tickets t
    JOIN support_ticket_messages m ON m.ticket_id = t.ticket_id
    WHERE t.customer_id = ?
      AND m.sender_role='Agent'
      AND m.created_at = (
        SELECT MIN(m2.created_at)
        FROM support_ticket_messages m2
        WHERE m2.ticket_id=t.ticket_id AND m2.sender_role='Agent'
      )
  ");
  $st->execute([$customerId]);
  $avg = $st->fetch()["avg_mins"];
  $avg = ($avg === null) ? null : (int)round((float)$avg);

  j(["ok"=>true, "open_tickets"=>$openCount, "avg_response_minutes"=>$avg]);
}

/** ============ LIST TICKETS ============ */
if ($action === "list") {
  $st = $pdo->prepare("
    SELECT ticket_id, ticket_reference, subject, status, created_at
    FROM support_tickets
    WHERE customer_id=?
    ORDER BY created_at DESC
    LIMIT 200
  ");
  $st->execute([$customerId]);
  j(["ok"=>true, "tickets"=>$st->fetchAll()]);
}

/** ============ CREATE TICKET ============ */
if ($action === "create") {
  $subject = trim((string)($body["subject"] ?? ""));
  $description = trim((string)($body["description"] ?? ""));
  $category = (string)($body["category"] ?? "Other");     // Hardware/Software/Network/Supplies/Other
  $priority = (string)($body["priority"] ?? "Normal");    // Normal/High/Urgent

  if ($subject === "" || $description === "") {
    j(["ok"=>false, "error"=>"Subject and Description are required"], 422);
  }

  // sanitize enum fallbacks
  $validCat = ["Hardware","Software","Network","Supplies","Other"];
  if (!in_array($category, $validCat, true)) $category = "Other";
  $validPri = ["Normal","High","Urgent"];
  if (!in_array($priority, $validPri, true)) $priority = "Normal";

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("
      INSERT INTO support_tickets (ticket_reference, customer_id, subject, category, priority, description, status)
      VALUES (NULL, ?, ?, ?, ?, ?, 'Open')
    ");
    $st->execute([$customerId, $subject, $category, $priority, $description]);
    $ticketId = (int)$pdo->lastInsertId();

    $ref = make_ref($ticketId);
    $st = $pdo->prepare("UPDATE support_tickets SET ticket_reference=? WHERE ticket_id=?");
    $st->execute([$ref, $ticketId]);

    // also store first message as customer message
    $st = $pdo->prepare("
      INSERT INTO support_ticket_messages (ticket_id, sender_role, sender_id, message)
      VALUES (?, 'Customer', ?, ?)
    ");
    $st->execute([$ticketId, $customerId, $description]);

    $pdo->commit();
    j(["ok"=>true, "ticket_id"=>$ticketId, "ticket_reference"=>$ref]);
  } catch (Throwable $e) {
    $pdo->rollBack();
    j(["ok"=>false, "error"=>"Create failed"], 500);
  }
}

/** ============ VIEW TICKET ============ */
if ($action === "view") {
  $ticketId = (int)($_GET["ticket_id"] ?? 0);
  if ($ticketId <= 0) j(["ok"=>false, "error"=>"ticket_id required"], 422);

  assert_owns_ticket($ticketId, $customerId);

  $st = $pdo->prepare("
    SELECT ticket_id, ticket_reference, subject, category, priority, description, status, created_at
    FROM support_tickets
    WHERE ticket_id=? AND customer_id=?
  ");
  $st->execute([$ticketId, $customerId]);
  $ticket = $st->fetch();

  $st = $pdo->prepare("
    SELECT msg_id, sender_role, message, created_at
    FROM support_ticket_messages
    WHERE ticket_id=?
    ORDER BY created_at ASC
    LIMIT 500
  ");
  $st->execute([$ticketId]);
  $messages = $st->fetchAll();

  j(["ok"=>true, "ticket"=>$ticket, "messages"=>$messages]);
}

/** ============ ADD NOTE / UPDATE ============ */
if ($action === "update") {
  $ticketId = (int)($body["ticket_id"] ?? 0);
  $note = trim((string)($body["note"] ?? ""));

  if ($ticketId <= 0) j(["ok"=>false, "error"=>"ticket_id required"], 422);
  if ($note === "") j(["ok"=>false, "error"=>"note required"], 422);

  assert_owns_ticket($ticketId, $customerId);

  // don’t allow editing if Closed/Resolved
  $st = $pdo->prepare("SELECT status FROM support_tickets WHERE ticket_id=? AND customer_id=?");
  $st->execute([$ticketId, $customerId]);
  $status = (string)($st->fetch()["status"] ?? "");

  if (in_array($status, ["Resolved","Closed"], true)) {
    j(["ok"=>false, "error"=>"Ticket is Resolved/Closed"], 409);
  }

  $st = $pdo->prepare("
    INSERT INTO support_ticket_messages (ticket_id, sender_role, sender_id, message)
    VALUES (?, 'Customer', ?, ?)
  ");
  $st->execute([$ticketId, $customerId, $note]);

  // If ticket is Open, move to In Progress
  $st = $pdo->prepare("
    UPDATE support_tickets
    SET status = CASE WHEN status='Open' THEN 'In Progress' ELSE status END
    WHERE ticket_id=? AND customer_id=?
  ");
  $st->execute([$ticketId, $customerId]);

  j(["ok"=>true]);
}

/** ============ CLOSE (Cancel) TICKET ============ */
if ($action === "cancel") {
  $ticketId = (int)($body["ticket_id"] ?? 0);
  if ($ticketId <= 0) j(["ok"=>false, "error"=>"ticket_id required"], 422);

  assert_owns_ticket($ticketId, $customerId);

  $st = $pdo->prepare("
    UPDATE support_tickets
    SET status='Closed'
    WHERE ticket_id=? AND customer_id=?
  ");
  $st->execute([$ticketId, $customerId]);

  j(["ok"=>true]);
}

/** ============ LIVE CHAT SEND ============ */
if ($action === "send_message") {
  $ticketId = (int)($body["ticket_id"] ?? 0);
  $message = trim((string)($body["message"] ?? ""));

  if ($ticketId <= 0 || $message === "") {
    j(["ok"=>false, "error"=>"ticket_id and message required"], 422);
  }

  assert_owns_ticket($ticketId, $customerId);

  $st = $pdo->prepare("
    INSERT INTO support_ticket_messages (ticket_id, sender_role, sender_id, message)
    VALUES (?, 'Customer', ?, ?)
  ");
  $st->execute([$ticketId, $customerId, $message]);

  $st = $pdo->prepare("
    UPDATE support_tickets
    SET status = CASE WHEN status='Open' THEN 'In Progress' ELSE status END
    WHERE ticket_id=? AND customer_id=?
  ");
  $st->execute([$ticketId, $customerId]);

  j(["ok"=>true]);
}

/** ============ POLL NEW MSGS ============ */
if ($action === "poll") {
  $ticketId = (int)($_GET["ticket_id"] ?? 0);
  $after = (string)($_GET["after"] ?? "1970-01-01 00:00:00");

  if ($ticketId <= 0) j(["ok"=>false, "error"=>"ticket_id required"], 422);

  assert_owns_ticket($ticketId, $customerId);

  $st = $pdo->prepare("
    SELECT msg_id, sender_role, message, created_at
    FROM support_ticket_messages
    WHERE ticket_id=? AND created_at > ?
    ORDER BY created_at ASC
    LIMIT 200
  ");
  $st->execute([$ticketId, $after]);

  j(["ok"=>true, "messages"=>$st->fetchAll()]);
}

j(["ok"=>false, "error"=>"Unknown action"], 400);