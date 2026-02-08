<?php
// php/api_bootstrap.php
declare(strict_types=1);

if (ob_get_level() === 0) ob_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

set_error_handler(function ($severity, $message, $file, $line) {
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
  if (ob_get_length()) ob_clean();
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "error" => "Server error: " . $e->getMessage(),
    "where" => basename($e->getFile()) . ":" . $e->getLine()
  ]);
  exit;
});

register_shutdown_function(function () {
  $err = error_get_last();
  if ($err && in_array($err["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode([
      "ok" => false,
      "error" => "Fatal error: " . $err["message"],
      "where" => basename($err["file"]) . ":" . $err["line"]
    ]);
    exit;
  }
});

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";