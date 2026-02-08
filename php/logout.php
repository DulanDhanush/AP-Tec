<?php
declare(strict_types=1);

session_start();

// Clear session array
$_SESSION = [];

// Remove session cookie too
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// ✅ Prevent caching logout page too
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login
header("Location: ../html/login.html");
exit;