<?php
// php/approvals_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

$u = require_login_api("Owner");

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
$ownerId = (int)($u["user_id"] ?? 0);

function client_ip(): string {
  return $_SERVER["REMOTE_ADDR"] ?? "unknown";
}

function log_event(PDO $pdo, string $level, string $module, string $message, ?int $userId): void {
  $stmt = $pdo->prepare("
    INSERT INTO system_logs (level, module, message, user_id, ip_address)
    VALUES (:level, :module, :message, :user_id, :ip)
  ");
  $stmt->execute([
    ":level" => $level,
    ":module" => $module,
    ":message" => $message,
    ":user_id" => $userId,
    ":ip" => client_ip()
  ]);
}

function notify_user(PDO $pdo, int $toUserId, string $type, string $title, string $message): void {
  $stmt = $pdo->prepare("
    INSERT INTO notifications (user_id, type, title, message, is_read)
    VALUES (:uid, :type, :title, :msg, 0)
  ");
  $stmt->execute([
    ":uid" => $toUserId,
    ":type" => $type,
    ":title" => $title,
    ":msg" => $message
  ]);
}

if ($action === "list") {
  // ✅ LEFT JOIN so it still works even if user record missing
  $stmt = $pdo->prepare("
    SELECT
      a.approval_id,
      a.requester_id,
      a.type,
      a.details,
      a.amount,
      a.status,
      a.created_at,
      COALESCE(u.full_name, CONCAT('User #', a.requester_id)) AS requester_name
    FROM approvals a
    LEFT JOIN users u ON u.user_id = a.requester_id
    WHERE a.status = 'Pending'
    ORDER BY a.created_at DESC
  ");
  $stmt->execute();
  j(["ok" => true, "items" => $stmt->fetchAll()]);
}

if ($action === "update") {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw ?: "{}", true);

  $approvalId = (int)($data["approval_id"] ?? 0);
  $decision = (string)($data["decision"] ?? "");

  if ($approvalId <= 0 || !in_array($decision, ["Approved", "Rejected"], true)) {
    j(["ok" => false, "error" => "Invalid request"], 400);
  }

  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare("
      SELECT approval_id, requester_id, type, amount, status
      FROM approvals
      WHERE approval_id = :id
      FOR UPDATE
    ");
    $stmt->execute([":id" => $approvalId]);
    $row = $stmt->fetch();

    if (!$row) {
      $pdo->rollBack();
      j(["ok" => false, "error" => "Approval not found"], 404);
    }

    if ($row["status"] !== "Pending") {
      $pdo->rollBack();
      j(["ok" => false, "error" => "Already decided"], 409);
    }

    $upd = $pdo->prepare("
      UPDATE approvals
      SET status = :status, reviewed_by = :owner
      WHERE approval_id = :id
    ");
    $upd->execute([
      ":status" => $decision,
      ":owner" => $ownerId,
      ":id" => $approvalId
    ]);

    $requesterId = (int)$row["requester_id"];
    $amount = (float)($row["amount"] ?? 0);

    notify_user(
      $pdo,
      $requesterId,
      $decision === "Approved" ? "Success" : "Alert",
      "Approval {$decision}",
      "{$row["type"]} (#{$approvalId}) was {$decision}. Amount: " . number_format($amount, 2)
    );

    log_event($pdo, "INFO", "Approvals", "approval_id={$approvalId} -> {$decision}", $ownerId);

    $pdo->commit();
    j(["ok" => true, "status" => $decision]);

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    j(["ok" => false, "error" => "Server error"], 500);
  }
}

j(["ok" => false, "error" => "Unknown action"], 404);