<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/logger.php";

$me = require_login_api(["Admin", "Owner"]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function j(array $data, int $code = 200): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit;
}

$raw = file_get_contents("php://input");
$body = json_decode($raw ?: "{}", true);
if (!is_array($body)) j(["ok" => false, "error" => "INVALID_JSON"], 400);

$id        = (int)($body["id"] ?? 0);
$full_name = trim((string)($body["full_name"] ?? ""));
$email     = trim((string)($body["email"] ?? ""));
$role      = trim((string)($body["role"] ?? ""));
$password  = (string)($body["password"] ?? "");

// validate role against your ENUM
$allowedRoles = ["Admin","Owner","Employee","Customer"];
if ($role !== "" && !in_array($role, $allowedRoles, true)) {
    j(["ok" => false, "error" => "INVALID_ROLE"], 400);
}

if ($full_name === "" || $email === "") {
    j(["ok" => false, "error" => "MISSING_FIELDS"], 400);
}

// Build username from email (simple + stable)
$username = preg_replace('/[^a-zA-Z0-9_.-]/', '', strtok($email, "@") ?: "user");
if ($username === "") $username = "user";

try {
    if ($id <= 0) {
        // CREATE
        // status default
        $stmt = $pdo->prepare("
            INSERT INTO users (username, full_name, email, password_hash, role, status, created_at)
            VALUES (:username, :full_name, :email, :pw, :role, 'Active', NOW())
        ");
        $stmt->execute([
            ":username"  => $username,
            ":full_name" => $full_name,
            ":email"     => $email,
            ":pw"        => ($password !== "" ? $password : "Welcome123"), // plain, as you want
            ":role"      => ($role !== "" ? $role : "Customer"),
        ]);

        $newId = (int)$pdo->lastInsertId();

        // ✅ your log enum is INFO/WARNING/ERROR
        log_event($pdo, "INFO", "Users", "Created user #{$newId} ({$email})", (int)$me["user_id"]);

        j(["ok" => true, "id" => $newId]);
    } else {
        // UPDATE
        $sets = [];
        $params = [":id" => $id];

        $sets[] = "full_name = :n"; $params[":n"] = $full_name;
        $sets[] = "email = :e";     $params[":e"] = $email;
        if ($role !== "") { $sets[] = "role = :r"; $params[":r"] = $role; }

        // only change password if provided (edit mode)
        if (trim($password) !== "") {
            $sets[] = "password_hash = :pw";
            $params[":pw"] = $password; // plain
        }

        $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $sets) . " WHERE user_id = :id");
        $stmt->execute($params);

        log_event($pdo, "INFO", "Users", "Updated user #{$id} ({$email})", (int)$me["user_id"]);

        j(["ok" => true]);
    }
} catch (Throwable $e) {
    j(["ok" => false, "error" => "SAVE_FAILED", "details" => $e->getMessage()], 500);
}