<?php
// php/user_save.php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

// (optional) log
$loggerPath = __DIR__ . "/logger.php";
if (file_exists($loggerPath)) require_once $loggerPath;

$admin = require_login("Admin");

$input = json_decode(file_get_contents("php://input"), true) ?? [];

$id       = (int)($input["id"] ?? 0);
$fullName = trim((string)($input["full_name"] ?? ""));
$email    = trim((string)($input["email"] ?? ""));
$role     = trim((string)($input["role"] ?? "Employee"));
$password = (string)($input["password"] ?? "");

$allowedRoles = ["Employee","Customer","Owner","Admin"];
if ($fullName === "" || $email === "") {
    echo json_encode(["ok" => false, "error" => "Name and email required"]);
    exit;
}
if (!in_array($role, $allowedRoles, true)) {
    echo json_encode(["ok" => false, "error" => "Invalid role"]);
    exit;
}

// generate username if creating
function make_username(string $name): string {
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($name)));
    $base = trim($base, "_");
    if ($base === "") $base = "user";
    return substr($base, 0, 20);
}

try {
    if ($id > 0) {
        // UPDATE
        $stmt = $pdo->prepare("
            UPDATE users
            SET full_name = :n, email = :e, role = :r
            WHERE user_id = :id
        ");
        $stmt->execute([
            ":n" => $fullName,
            ":e" => $email,
            ":r" => $role,
            ":id" => $id
        ]);

        // update password only if provided
        if ($password !== "") {
            $stmt2 = $pdo->prepare("UPDATE users SET password_hash = :p WHERE user_id = :id");
            $stmt2->execute([":p" => $password, ":id" => $id]);
        }

        if (function_exists("log_event")) {
            log_event($pdo, "INFO", "UserMgmt", "Admin {$admin['username']} updated user #{$id}", $admin["user_id"]);
        }

        echo json_encode(["ok" => true, "message" => "User updated"]);
        exit;
    }

    // CREATE
    if ($password === "") $password = "Welcome123";

    // username must be unique
    $username = make_username($fullName);
    $try = 0;
    while (true) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
        $check->execute([":u" => $username]);
        $exists = (int)$check->fetchColumn();
        if ($exists === 0) break;
        $try++;
        $username = make_username($fullName) . $try;
        if ($try > 30) { $username = "user" . time(); break; }
    }

    // email unique
    $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e");
    $checkEmail->execute([":e" => $email]);
    if ((int)$checkEmail->fetchColumn() > 0) {
        echo json_encode(["ok" => false, "error" => "Email already exists"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, username, email, password_hash, role, status, created_at)
        VALUES (:n, :u, :e, :p, :r, 'Active', NOW())
    ");
    $stmt->execute([
        ":n" => $fullName,
        ":u" => $username,
        ":e" => $email,
        ":p" => $password,
        ":r" => $role
    ]);

    $newId = (int)$pdo->lastInsertId();

    if (function_exists("log_event")) {
        log_event($pdo, "INFO", "UserMgmt", "Admin {$admin['username']} created user #{$newId} ({$username})", $admin["user_id"]);
    }

    echo json_encode(["ok" => true, "message" => "User created", "id" => $newId, "username" => $username]);
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "error" => "Server error"]);
}