<?php
// php/login.php
declare(strict_types=1);

session_start();
require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../html/login.html");
    exit;
}

$username = trim((string)($_POST["username"] ?? ""));
$password = (string)($_POST["password"] ?? "");

if ($username === "" || $password === "") {
    header("Location: ../html/login.html");
    exit;
}

$sql = "
    SELECT user_id, username, email, password_hash, role, status, full_name
    FROM users
    WHERE (username = :u1 OR email = :u2)
      AND status = 'Active'
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":u1" => $username,
    ":u2" => $username
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: ../html/login.html");
    exit;
}

// ✅ PLAIN PASSWORD CHECK ONLY
$stored = (string)($user["password_hash"] ?? "");
if ($stored !== $password) {
    header("Location: ../html/login.html");
    exit;
}

// ✅ LOGIN SUCCESS (clear old session + new session id)
$_SESSION = [];
session_regenerate_id(true);

$_SESSION["user_id"]    = (int)$user["user_id"];
$_SESSION["username"]   = (string)$user["username"];
$_SESSION["full_name"]  = (string)$user["full_name"];  // ✅ store with clear key
$_SESSION["role"] = (string)$user["role"]; // Admin / Owner / Employee / Customer

$role = $_SESSION["role"];

// ✅ redirect
switch ($role) {
    case "admin":
        header("Location: ../html/admin_dashboard.php");
        break;
    case "owner":
        header("Location: ../html/owner_dashboard.php");
        break;
    case "employee":
        header("Location: ../html/employee_dashboard.php");
        break;
    default:
        header("Location: ../html/customer_dashboard.php");
        break;
}
exit;