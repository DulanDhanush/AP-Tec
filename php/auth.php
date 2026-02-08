<?php
// php/auth.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/db.php";

function norm_role(string $r): string {
    return strtolower(trim($r));
}

/**
 * Require login for normal pages (redirect on fail)
 * @param string|array $roles Allowed role(s)
 */
function require_login(string|array $roles = []): array
{
    global $pdo;

    $uid = (int)($_SESSION["user_id"] ?? 0);
    if ($uid <= 0) {
        header("Location: ../html/login.html");
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT user_id, username, full_name, role, status, email
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

    if ((string)$user["status"] !== "Active") {
        session_destroy();
        header("Location: ../html/login.html");
        exit;
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_values(array_filter(array_map(
        fn($r) => norm_role((string)$r),
        $allowed
    )));

    $userRole = norm_role((string)($user["role"] ?? ""));

    if (!empty($allowed) && !in_array($userRole, $allowed, true)) {
        header("Location: ../html/login.html");
        exit;
    }

    // keep session synced
    $_SESSION["role"] = (string)$user["role"];
    $_SESSION["username"] = (string)$user["username"];
    $_SESSION["full_name"] = (string)($user["full_name"] ?? "");

    return $user;
}

/**
 * Require login for API (JSON on fail, NO redirects)
 * @param string|array $roles Allowed role(s)
 */
function require_login_api(string|array $roles = []): array
{
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

        $dbRoleNorm = norm_role((string)($u["role"] ?? ""));

        $allowed = is_array($roles) ? $roles : [$roles];
        $allowed = array_values(array_filter(array_map(
            fn($r) => norm_role((string)$r),
            $allowed
        )));

        if (!empty($allowed) && !in_array($dbRoleNorm, $allowed, true)) {
            http_response_code(403);
            echo json_encode([
                "ok" => false,
                "error" => "FORBIDDEN",
                "role" => (string)$u["role"]
            ]);
            exit;
        }

        // keep session synced
        $_SESSION["role"] = (string)$u["role"];
        $_SESSION["username"] = (string)$u["username"];
        $_SESSION["full_name"] = (string)($u["full_name"] ?? "");

        return $u;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["ok" => false, "error" => "AUTH_DB_ERROR"]);
        exit;
    }
}