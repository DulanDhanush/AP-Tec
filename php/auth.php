<?php
// php/auth.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** normalize role strings once */
function norm_role(string $r): string {
    return strtolower(trim($r));
}

/**
 * Web pages: redirects to login if not allowed
 * @param string|array $roles Allowed role(s) (any casing)
 */
function require_login(string|array $roles = []): array
{
    $uid  = (int)($_SESSION["user_id"] ?? 0);
    $role = norm_role((string)($_SESSION["role"] ?? ""));

    if ($uid <= 0) {
        header("Location: ../html/login.html");
        exit;
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_values(array_filter(array_map(
        fn($v) => norm_role((string)$v),
        $allowed
    )));

    if (!empty($allowed) && !in_array($role, $allowed, true)) {
        // logged in but not permitted
        header("Location: ../html/login.html");
        exit;
    }

    return [
        "user_id"   => $uid,
        "username"  => (string)($_SESSION["username"] ?? ""),
        "role"      => $role,
        "full_name" => (string)($_SESSION["name"] ?? ""),
        "name"      => (string)($_SESSION["name"] ?? ""),
    ];
}

/**
 * APIs: returns JSON error instead of redirect
 * @param string|array $roles Allowed role(s) (any casing)
 */
function require_login_api(string|array $roles = []): array
{
    $uid  = (int)($_SESSION["user_id"] ?? 0);
    $role = norm_role((string)($_SESSION["role"] ?? ""));

    if ($uid <= 0) {
        http_response_code(401);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_values(array_filter(array_map(
        fn($v) => norm_role((string)$v),
        $allowed
    )));

    if (!empty($allowed) && !in_array($role, $allowed, true)) {
        http_response_code(403);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["ok" => false, "error" => "Forbidden"]);
        exit;
    }

    return [
        "user_id"   => $uid,
        "username"  => (string)($_SESSION["username"] ?? ""),
        "role"      => $role,
        "full_name" => (string)($_SESSION["name"] ?? ""),
        "name"      => (string)($_SESSION["name"] ?? ""),
    ];
}