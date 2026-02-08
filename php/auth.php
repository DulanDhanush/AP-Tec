<?php
// php/auth.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * @param string|array $roles Allowed role(s)
 */
function require_login(string|array $roles = []): array
{
    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_values(array_filter(array_map('strval', $allowed)));

    $uid  = (int)($_SESSION["user_id"] ?? 0);
    $role = (string)($_SESSION["role"] ?? "");

    if ($uid <= 0) {
        header("Location: ../html/login.html");
        exit;
    }

    if (!empty($allowed) && !in_array($role, $allowed, true)) {
        header("Location: ../html/login.html");
        exit;
    }

    return [
        "user_id"    => $uid,
        "username"   => (string)($_SESSION["username"] ?? ""),
        "role"       => $role,
        "full_name"  => (string)($_SESSION["name"] ?? ""),
        "name"       => (string)($_SESSION["name"] ?? ""),
    ];
}

/**
 * For JSON API endpoints
 * @param string|array $roles Allowed role(s)
 */
function require_login_api(string|array $roles = []): array
{
    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_values(array_filter(array_map('strval', $allowed)));

    $uid  = (int)($_SESSION["user_id"] ?? 0);
    $role = (string)($_SESSION["role"] ?? "");

    header("Content-Type: application/json; charset=utf-8");

    if ($uid <= 0) {
        http_response_code(401);
        echo json_encode(["ok" => false, "error" => "Not logged in"]);
        exit;
    }

    if (!empty($allowed) && !in_array($role, $allowed, true)) {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "Forbidden"]);
        exit;
    }

    return [
        "user_id"    => $uid,
        "username"   => (string)($_SESSION["username"] ?? ""),
        "role"       => $role,
        "full_name"  => (string)($_SESSION["name"] ?? ""),
        "name"       => (string)($_SESSION["name"] ?? ""),
    ];
    
}
