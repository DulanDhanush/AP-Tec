<?php
// php/notifications_api.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$u = require_login_api(["Admin", "Owner"]);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

function get_json_body(): array {
  $raw = file_get_contents("php://input");
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

$action = (string)($_GET["action"] ?? "");

$userId = (int)($u["user_id"] ?? 0);
if ($userId <= 0) j(["ok" => false, "error" => "Unauthorized"], 401);

/**
 * ACTIONS:
 *  - list
 *  - mark_read (POST JSON: {notif_id})
 *  - mark_all_read
 *  - settings_get
 *  - settings_save (POST JSON: {low_inventory, server_downtime, daily_summary, new_user_signups})
 */

try {

  // 1) LIST notifications (global + this user)
  if ($action === "list") {
    $limit = (int)($_GET["limit"] ?? 50);
    if ($limit <= 0 || $limit > 200) $limit = 50;

    $stmt = $pdo->prepare("
      SELECT
        n.notif_id, n.user_id, n.type, n.title, n.message, n.is_read, n.created_at
      FROM notifications n
      WHERE (n.user_id = :uid OR n.user_id IS NULL)
      ORDER BY n.created_at DESC, n.notif_id DESC
      LIMIT :lim
    ");
    $stmt->bindValue(":uid", $userId, PDO::PARAM_INT);
    $stmt->bindValue(":lim", $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // unread count
    $stmt2 = $pdo->prepare("
      SELECT COUNT(*) AS unread_count
      FROM notifications
      WHERE (user_id = :uid OR user_id IS NULL)
        AND is_read = 0
    ");
    $stmt2->execute([":uid" => $userId]);
    $unread = (int)($stmt2->fetch(PDO::FETCH_ASSOC)["unread_count"] ?? 0);

    j(["ok" => true, "rows" => $rows, "unread" => $unread]);
  }

  // 2) MARK ONE AS READ
  if ($action === "mark_read") {
    $b = get_json_body();
    $notifId = (int)($b["notif_id"] ?? 0);
    if ($notifId <= 0) j(["ok" => false, "error" => "Invalid notif_id"], 400);

    // Only allow marking if it's visible to this user (user_id = this OR NULL)
    $stmt = $pdo->prepare("
      UPDATE notifications
      SET is_read = 1
      WHERE notif_id = :nid
        AND (user_id = :uid OR user_id IS NULL)
    ");
    $stmt->execute([":nid" => $notifId, ":uid" => $userId]);

    j(["ok" => true]);
  }

  // 3) MARK ALL AS READ (for this user's visible notifications)
  if ($action === "mark_all_read") {
    $stmt = $pdo->prepare("
      UPDATE notifications
      SET is_read = 1
      WHERE (user_id = :uid OR user_id IS NULL)
        AND is_read = 0
    ");
    $stmt->execute([":uid" => $userId]);

    j(["ok" => true, "updated" => $stmt->rowCount()]);
  }

  // 4) GET SETTINGS
  if ($action === "settings_get") {
    $stmt = $pdo->prepare("
      SELECT user_id, low_inventory, server_downtime, daily_summary, new_user_signups
      FROM notification_settings
      WHERE user_id = :uid
      LIMIT 1
    ");
    $stmt->execute([":uid" => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      // create defaults row automatically
      $pdo->prepare("
        INSERT INTO notification_settings (user_id, low_inventory, server_downtime, daily_summary, new_user_signups)
        VALUES (:uid, 1, 1, 0, 1)
      ")->execute([":uid" => $userId]);

      $row = [
        "user_id" => $userId,
        "low_inventory" => 1,
        "server_downtime" => 1,
        "daily_summary" => 0,
        "new_user_signups" => 1,
      ];
    }

    j(["ok" => true, "settings" => $row]);
  }

  // 5) SAVE SETTINGS
  if ($action === "settings_save") {
    $b = get_json_body();

    // normalize to 0/1
    $low  = !empty($b["low_inventory"]) ? 1 : 0;
    $down = !empty($b["server_downtime"]) ? 1 : 0;
    $daily= !empty($b["daily_summary"]) ? 1 : 0;
    $newU = !empty($b["new_user_signups"]) ? 1 : 0;

    $stmt = $pdo->prepare("
      INSERT INTO notification_settings (user_id, low_inventory, server_downtime, daily_summary, new_user_signups)
      VALUES (:uid, :low, :down, :daily, :newu)
      ON DUPLICATE KEY UPDATE
        low_inventory = VALUES(low_inventory),
        server_downtime = VALUES(server_downtime),
        daily_summary = VALUES(daily_summary),
        new_user_signups = VALUES(new_user_signups)
    ");
    $stmt->execute([
      ":uid" => $userId,
      ":low" => $low,
      ":down" => $down,
      ":daily" => $daily,
      ":newu" => $newU
    ]);

    j(["ok" => true]);
  }

  j(["ok" => false, "error" => "Unknown action"], 400);

} catch (Throwable $e) {
  j(["ok" => false, "error" => "Server error: " . $e->getMessage()], 500);
}