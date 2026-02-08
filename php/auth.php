<?php
// php/auth.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/db.php";

/**
 * Require login for normal pages.
 * @param string|array $roles Allowed role(s)
 * @return array user info: user_id, username, full_name, role, status
 */
function require_login(string|array $roles = []): array
{
    global $pdo;

    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_values(array_filter(array_map(
        fn($r) => strtolower(trim((string)$r)),
        $allowed
    )));

    $uid = (int)($_SESSION["user_id"] ?? 0);
    if ($uid <= 0) {
        header("Location: ../html/login.html");
        exit;
    }

    // ✅ always fetch real user from DB
    $stmt = $pdo->prepare("
        SELECT user_id, username, full_name, role, status
        FROM users
        WHERE user_id = :uid
        LIMIT 1
    ");
    $stmt->execute([":uid" => $uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: ../html/login.html");
        exit;
    }

    // ✅ block non-active accounts
    if ((string)($user["status"] ?? "") !== "Active") {
        session_destroy();
        header("Location: ../html/login.html");
        exit;
    }

    $role = strtolower(trim((string)($user["role"] ?? "")));
    if (!empty($allowed) && !in_array($role, $allowed, true)) {
        header("Location: ../html/login.html");
        exit;
    }

    // ✅ keep session synced (so dashboard always changes correctly)
    $_SESSION["username"]  = (string)($user["username"] ?? "");
    $_SESSION["full_name"] = (string)($user["full_name"] ?? "");
    $_SESSION["role"]      = $role;

    return [
        "user_id"   => (int)$user["user_id"],
        "username"  => (string)$user["username"],
        "full_name" => (string)$user["full_name"],
        "role"      => $role,
        "status"    => (string)$user["status"],
    ];
}
function require_login_api(array $roles = []): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    require_once __DIR__ . "/db.php";
    global $pdo;

    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");

    $uid = (int)($_SESSION["user_id"] ?? 0);
    if ($uid <= 0) {
        http_response_code(401);
        echo json_encode(["ok" => false, "error" => "NOT_LOGGED_IN"]);
        exit;
    }

    // ✅ Always use DB as the source of truth
    try {
        $stmt = $pdo->prepare("
            SELECT user_id, username, full_name, role, status, email
            FROM users
            WHERE user_id = :uid
            LIMIT 1
        ");
        $stmt->execute([":uid" => $uid]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            http_response_code(401);
            echo json_encode(["ok" => false, "error" => "SESSION_INVALID"]);
            exit;
        }

        if ((string)$u["status"] !== "Active") {
            http_response_code(403);
            echo json_encode(["ok" => false, "error" => "ACCOUNT_INACTIVE"]);
            exit;
        }

        $dbRole = (string)$u["role"]; // Admin/Owner/Employee/Customer

        // ✅ Role check (case-insensitive safe)
        if (!empty($roles)) {
            $allowed = array_map(fn($r) => strtolower(trim((string)$r)), $roles);
            if (!in_array(strtolower($dbRole), $allowed, true)) {
                http_response_code(403);
                echo json_encode([
                    "ok" => false,
                    "error" => "FORBIDDEN",
                    "role" => $dbRole
                ]);
                exit;
            }
        }

        // Keep session synced (optional but helpful)
        $_SESSION["role"] = $dbRole;
        $_SESSION["username"] = (string)$u["username"];
        $_SESSION["full_name"] = (string)$u["full_name"];

        return $u;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["ok" => false, "error" => "AUTH_DB_ERROR", "details" => $e->getMessage()]);
        exit;
    }
}