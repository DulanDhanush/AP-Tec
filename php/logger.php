<?php
// php/logger.php
declare(strict_types=1);

function log_event(PDO $pdo, string $level, string $module, string $message, ?int $userId = null): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'System';

    $stmt = $pdo->prepare("
        INSERT INTO system_logs (level, module, message, user_id, ip_address, created_at)
        VALUES (:level, :module, :message, :user_id, :ip, NOW())
    ");

    $stmt->execute([
        ":level" => $level,
        ":module" => $module,
        ":message" => $message,
        ":user_id" => $userId,
        ":ip" => $ip
    ]);
}