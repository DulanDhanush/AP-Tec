<?php
// php/backup_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";       // must provide $pdo
require_once __DIR__ . "/auth.php";     // must provide require_login_api()
require_once __DIR__ . "/google_drive.php";

// ✅ Set full paths for Windows (XAMPP)
// If your XAMPP path is different, change these:
$MYSQL_BIN     = "C:\\xampp\\mysql\\bin\\mysql.exe";
$MYSQLDUMP_BIN = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

$u = require_login_api("Admin");

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j(array $arr, int $code = 200): void {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

$action = (string)($_GET["action"] ?? "");

// === DB credentials (must match your local MySQL) ===
$DB_HOST = $DB_HOST ?? "127.0.0.1";
$DB_NAME = $DB_NAME ?? "aptec";
$DB_USER = $DB_USER ?? "root";
$DB_PASS = $DB_PASS ?? "";

// Where backup files stored:
$BACKUP_DIR = realpath(__DIR__ . "/backups");
if ($BACKUP_DIR === false) {
    $dir = __DIR__ . "/backups";
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $BACKUP_DIR = realpath($dir) ?: $dir;
}

function safe_filename(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    return $name ?: ("backup_" . time() . ".sql");
}

function run_cmd(string $cmd): array {
    $output = [];
    $ret = 0;
    exec($cmd . " 2>&1", $output, $ret);
    return [$ret, implode("\n", $output)];
}

function db_size_bytes(PDO $pdo): int {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(data_length + index_length),0) AS sz
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE()
    ");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

/**
 * ✅ Clean restore helper: drop all tables + views before importing
 */
function drop_all_tables(PDO $pdo): void {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    // Drop views first (some DBs depend on tables; either order can work, but this is safer)
    $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);
    foreach ($views as $v) {
        $view = $v[0];
        $pdo->exec("DROP VIEW IF EXISTS `$view`");
    }

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $t) {
        $table = $t[0];
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
}

/* =========================
   LIST BACKUPS
========================= */
if ($action === "list") {
    $rows = $pdo->query("
        SELECT id, backup_code, file_name, file_size_bytes, backup_type, status, drive_file_id, created_at
        FROM backups
        ORDER BY created_at DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);

    $last = $rows[0]["created_at"] ?? null;

    j([
        "ok" => true,
        "last_backup" => $last,
        "items" => $rows,
        "storage" => [
            "used_bytes" => (int)db_size_bytes($pdo),
            "limit_bytes" => 5 * 1024 * 1024 * 1024,
        ],
    ]);
}

/* =========================
   CREATE BACKUP + UPLOAD DRIVE
========================= */
if ($action === "create") {
    set_time_limit(0);

    $now = new DateTime("now");
    $code = "BK-" . $now->format("Y-m-d") . "-" . $now->format("His");
    $fileName = safe_filename($code . ".sql");
    $filePath = $BACKUP_DIR . DIRECTORY_SEPARATOR . $fileName;

    $host = escapeshellarg($DB_HOST);
    $user = escapeshellarg($DB_USER);
    $pass = $DB_PASS !== "" ? ("-p" . escapeshellarg($DB_PASS)) : "";
    $db   = escapeshellarg($DB_NAME);

    // ✅ Use full path mysqldump.exe + Windows cmd /c for >
    $cmd = "\"{$MYSQLDUMP_BIN}\" --host={$host} --user={$user} {$pass} {$db} > " . escapeshellarg($filePath);
    $cmd = "cmd /c \"{$cmd}\"";

    [$ret, $out] = run_cmd($cmd);

    if ($ret !== 0 || !file_exists($filePath)) {
        $stmt = $pdo->prepare("
            INSERT INTO backups (backup_code, file_name, file_path, file_size_bytes, backup_type, status)
            VALUES (:code, :fn, :fp, 0, 'Manual', 'Failed')
        ");
        $stmt->execute([
            ":code" => $code,
            ":fn" => $fileName,
            ":fp" => $filePath,
        ]);

        j(["ok" => false, "message" => "Backup failed", "details" => $out], 500);
    }

    $size = (int)filesize($filePath);

    // Upload to Google Drive
    $driveId = null;
    $driveError = null;

    try {
        $up = gd_upload_file($filePath, $fileName);
        $driveId = $up["id"] ?? null;
    } catch (Throwable $e) {
        $driveError = $e->getMessage();
        $driveId = null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO backups (backup_code, file_name, file_path, file_size_bytes, backup_type, status, drive_file_id)
        VALUES (:code, :fn, :fp, :sz, 'Manual', 'Verified', :did)
    ");
    $stmt->execute([
        ":code" => $code,
        ":fn" => $fileName,
        ":fp" => $filePath,
        ":sz" => $size,
        ":did" => $driveId,
    ]);

    j([
        "ok" => true,
        "message" => $driveId ? "Backup created + uploaded to Drive" : "Backup created (Drive upload failed)",
        "drive_error" => $driveError,
        "backup" => [
            "backup_code" => $code,
            "file_name" => $fileName,
            "file_size_bytes" => $size,
            "drive_file_id" => $driveId,
            "created_at" => (new DateTime())->format("Y-m-d H:i:s"),
        ],
    ]);
}

/* =========================
   DOWNLOAD BACKUP
========================= */
if ($action === "download") {
    $id = (int)($_GET["id"] ?? 0);
    if ($id <= 0) j(["ok" => false, "message" => "Invalid id"], 400);

    $stmt = $pdo->prepare("SELECT file_name, file_path FROM backups WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) j(["ok" => false, "message" => "Backup not found"], 404);

    $path = (string)$row["file_path"];
    $name = (string)$row["file_name"];
    if (!file_exists($path)) j(["ok" => false, "message" => "File missing on server"], 404);

    header_remove("Content-Type");
    header("Content-Type: application/sql");
    header("Content-Disposition: attachment; filename=\"" . basename($name) . "\"");
    header("Content-Length: " . filesize($path));
    readfile($path);
    exit;
}

/* =========================
   DELETE BACKUP
========================= */
if ($action === "delete") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id <= 0) j(["ok" => false, "message" => "Invalid id"], 400);

    $stmt = $pdo->prepare("SELECT file_path FROM backups WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) j(["ok" => false, "message" => "Backup not found"], 404);

    $path = (string)$row["file_path"];
    if (file_exists($path)) @unlink($path);

    $stmt = $pdo->prepare("DELETE FROM backups WHERE id = :id");
    $stmt->execute([":id" => $id]);

    j(["ok" => true, "message" => "Backup deleted"]);
}

/* =========================
   RESTORE FROM UPLOADED SQL
   + upload restore SQL to Drive
   + save restore file to backups history
========================= */
if ($action === "restore") {
    set_time_limit(0);

    if (!isset($_FILES["file"])) {
        j(["ok" => false, "message" => "No file uploaded"], 400);
    }

    $f = $_FILES["file"];
    if (($f["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        j(["ok" => false, "message" => "Upload error: " . ($f["error"] ?? "unknown")], 400);
    }

    $tmp = (string)$f["tmp_name"];
    $orig = safe_filename((string)($f["name"] ?? "restore.sql"));

    if (!preg_match('/\.sql$/i', $orig)) {
        j(["ok" => false, "message" => "Only .sql restore is supported"], 400);
    }

    // Save uploaded file into backups folder
    $restorePath = $BACKUP_DIR . DIRECTORY_SEPARATOR . "RESTORE_" . time() . "_" . $orig;
    if (!move_uploaded_file($tmp, $restorePath)) {
        if (!copy($tmp, $restorePath)) {
            j(["ok" => false, "message" => "Failed to save uploaded file"], 500);
        }
    }

    // ✅ Upload the restore SQL to Google Drive (upload first so you keep the file even if restore fails)
    $restoreDriveId = null;
    $restoreDriveError = null;

    try {
        $driveName = "RESTORE_" . date("Y-m-d_His") . "_" . basename($restorePath);
        $up = gd_upload_file($restorePath, $driveName);
        $restoreDriveId = $up["id"] ?? null;
    } catch (Throwable $e) {
        $restoreDriveError = $e->getMessage();
        $restoreDriveId = null;
    }

    // ✅ Clean restore (prevents "table already exists")
    drop_all_tables($pdo);

    $host = escapeshellarg($DB_HOST);
    $user = escapeshellarg($DB_USER);
    $pass = $DB_PASS !== "" ? ("-p" . escapeshellarg($DB_PASS)) : "";
    $db   = escapeshellarg($DB_NAME);

    $cmdBase = "\"{$MYSQL_BIN}\" --host={$host} --user={$user} {$pass} --default-character-set=utf8mb4 {$db}";
    $sqlFileArg = escapeshellarg($restorePath);

    // Windows needs cmd /c for < redirection
    $cmd = "cmd /c \"{$cmdBase} < {$sqlFileArg}\"";

    [$ret, $out] = run_cmd($cmd);

    if ($ret !== 0) {
        j([
            "ok" => false,
            "message" => "Restore failed",
            "details" => $out ?: "No output. Possible reasons: wrong DB credentials, SQL errors, or exec disabled.",
            "restore_drive_file_id" => $restoreDriveId,
            "drive_error" => $restoreDriveError
        ], 500);
    }

    // ✅ Save restore file to backup history (optional)
    try {
        $sz = file_exists($restorePath) ? (int)filesize($restorePath) : 0;

        $stmt = $pdo->prepare("
            INSERT INTO backups (backup_code, file_name, file_path, file_size_bytes, backup_type, status, drive_file_id)
            VALUES (:code, :fn, :fp, :sz, 'Manual', 'Verified', :did)
        ");

        $stmt->execute([
            ":code" => "RESTORE-" . date("Ymd-His"),
            ":fn"   => basename($restorePath),
            ":fp"   => $restorePath,
            ":sz"   => $sz,
            ":did"  => $restoreDriveId
        ]);
    } catch (Throwable $e) {
        // ignore history insert errors
    }

    j([
        "ok" => true,
        "message" => "System restored successfully" . ($restoreDriveId ? " + uploaded SQL to Drive" : " (Drive upload failed)"),
        "restore_drive_file_id" => $restoreDriveId,
        "drive_error" => $restoreDriveError
    ]);
}

j(["ok" => false, "message" => "Unknown action"], 400);