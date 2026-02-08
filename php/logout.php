<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/logger.php";

// Capture user info BEFORE destroying session
$userId   = (int)($_SESSION["user_id"] ?? 0);
$username = (string)($_SESSION["username"] ?? "");

// ✅ Log logout event (REAL dashboard activity)
if ($userId > 0) {
    log_event($userId, "INFO", "User {$username} logged out");

    // ✅ Mark user offline (for real Active Users count)
    try {
        $stmt = $pdo->prepare("
            UPDATE users
            SET last_seen = NULL
            WHERE user_id = :uid
        ");
        $stmt->execute([":uid" => $userId]);
    } catch (Throwable) {
        // silent fail
    }
}

// Clear session array
$_SESSION = [];

// Remove session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// ✅ Prevent caching logout page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login
header("Location: ../html/login.html");
exit;