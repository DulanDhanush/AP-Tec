<?php
// php/db.php
declare(strict_types=1);

$DB_HOST = "localhost";
$DB_PORT = "3306";          // change if your MySQL port is different (your dump shows 3307 sometimes)
$DB_NAME = "aptec_db";
$DB_USER = "root";
$DB_PASS = "";              // set your password if you have one

$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    die("Database connection failed.");
}