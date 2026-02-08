<?php
// php/backup_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";   // must provide $pdo
require_once __DIR__ . "/auth.php"; // must provide require_login_api()

if (file_exists(__DIR__ . "/google_drive.php")) {
  require_once __DIR__ . "/google_drive.php";
}

// ✅ Windows XAMPP paths
$MYSQL_BIN     = "C:\\xampp\\mysql\\bin\\mysql.exe";
$MYSQLDUMP_BIN = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

// ✅ IMPORTANT: role lowercase
$u = require_login_api(["admin"]);

// =========================
// DB CONFIG
// =========================
$DB_HOST = "localhost";
$DB_PORT = "3306";
$DB_NAME = "aptec_db";
$DB_USER = "root";
$DB_PASS = "";

// =========================
// BACKUP FOLDER
// =========================
$BACKUP_DIR = realpath(__DIR__ . "/../backups");
if ($BACKUP_DIR === false) {
  $dir = __DIR__ . "/../backups";
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  $BACKUP_DIR = realpath($dir) ?: $dir;
}

// -------------------------
// Helpers
// -------------------------
function j(array $arr, int $code = 200): void {
  http_response_code($code);
  header("Content-Type: application/json; charset=utf-8");
  header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
  header("Pragma: no-cache");
  header("Expires: 0");
  echo json_encode($arr);
  exit;
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

function must_be_inside_dir(string $path, string $dir): bool {
  $rp = realpath($path);
  $rd = realpath($dir);
  if ($rp === false || $rd === false) return false;

  $rp = rtrim($rp, "\\/") . DIRECTORY_SEPARATOR;
  $rd = rtrim($rd, "\\/") . DIRECTORY_SEPARATOR;

  return str_starts_with($rp, $rd);
}

function drop_all_tables(PDO $pdo): void {
  $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

  $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")
               ->fetchAll(PDO::FETCH_NUM);
  foreach ($views as $v) {
    $pdo->exec("DROP VIEW IF EXISTS `{$v[0]}`");
  }

  $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
                ->fetchAll(PDO::FETCH_NUM);
  foreach ($tables as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `{$t[0]}`");
  }

  $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
}

/**
 * ✅ Auto-create backups table if missing (prevents fatal errors)
 */
function ensure_backups_table(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS backups (
      id INT AUTO_INCREMENT PRIMARY KEY,
      backup_code VARCHAR(50) NOT NULL,
      file_name VARCHAR(255) NOT NULL,
      file_path VARCHAR(500) NOT NULL,
      file_size_bytes BIGINT NOT NULL DEFAULT 0,
      backup_type VARCHAR(20) NOT NULL DEFAULT 'Manual',
      status VARCHAR(20) NOT NULL DEFAULT 'Verified',
      drive_file_id VARCHAR(255) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (created_at),
      INDEX (backup_code),
      INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
}

// ✅ call it once for every request
ensure_backups_table($pdo);

$action = (string)($_GET["action"] ?? "");

// -------------------------
// DOWNLOAD
// -------------------------
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
  if (!must_be_inside_dir($path, $GLOBALS["BACKUP_DIR"])) j(["ok" => false, "message" => "Invalid file path"], 403);

  header_remove();
  header("Content-Type: application/sql");
  header("Content-Disposition: attachment; filename=\"" . basename($name) . "\"");
  header("Content-Length: " . filesize($path));
  header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
  header("Pragma: no-cache");
  readfile($path);
  exit;
}

// -------------------------
// LIST
// -------------------------
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

// -------------------------
// CREATE
// -------------------------
if ($action === "create") {
  set_time_limit(0);

  if (!file_exists($MYSQLDUMP_BIN)) {
    j(["ok"=>false,"message"=>"mysqldump.exe not found","path"=>$MYSQLDUMP_BIN], 500);
  }

  if (!is_dir($BACKUP_DIR) || !is_writable($BACKUP_DIR)) {
    j(["ok"=>false,"message"=>"Backups folder is not writable","backup_dir"=>$BACKUP_DIR], 500);
  }

  $now = new DateTime("now");
  $code = "BK-" . $now->format("Y-m-d") . "-" . $now->format("His");
  $fileName = safe_filename($code . ".sql");
  $filePath = $BACKUP_DIR . DIRECTORY_SEPARATOR . $fileName;

  $cmd = '"' . $MYSQLDUMP_BIN . '"'
      . ' --host=' . escapeshellarg($DB_HOST)
      . ' --port=' . escapeshellarg($DB_PORT)
      . ' --user=' . escapeshellarg($DB_USER);

  if ($DB_PASS !== "") {
    $cmd .= ' --password=' . escapeshellarg($DB_PASS);
  }

  $cmd .= ' --default-character-set=utf8mb4'
       . ' --routines --events --triggers'
       . ' --single-transaction'
       . ' --result-file=' . escapeshellarg($filePath)
       . ' ' . escapeshellarg($DB_NAME);

  [$ret, $out] = run_cmd($cmd);

  if ($ret !== 0 || !file_exists($filePath) || filesize($filePath) === 0) {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO backups (backup_code, file_name, file_path, file_size_bytes, backup_type, status)
        VALUES (:code, :fn, :fp, 0, 'Manual', 'Failed')
      ");
      $stmt->execute([":code"=>$code, ":fn"=>$fileName, ":fp"=>$filePath]);
    } catch (Throwable $e) {}

    j(["ok"=>false,"message"=>"Backup failed","details"=>$out], 500);
  }

  $size = (int)filesize($filePath);

  $driveId = null;
  $driveError = null;
  if (function_exists("gd_upload_file")) {
    try {
      $up = gd_upload_file($filePath, $fileName);
      $driveId = $up["id"] ?? null;
    } catch (Throwable $e) {
      $driveError = $e->getMessage();
    }
  }

  $stmt = $pdo->prepare("
    INSERT INTO backups (backup_code, file_name, file_path, file_size_bytes, backup_type, status, drive_file_id)
    VALUES (:code, :fn, :fp, :sz, 'Manual', 'Verified', :did)
  ");
  $stmt->execute([
    ":code"=>$code,
    ":fn"=>$fileName,
    ":fp"=>$filePath,
    ":sz"=>$size,
    ":did"=>$driveId
  ]);

  j([
    "ok"=>true,
    "message"=>$driveId ? "Backup created + uploaded to Drive" : "Backup created",
    "drive_error"=>$driveError
  ]);
}

// -------------------------
// DELETE
// -------------------------
if ($action === "delete") {
  $id = (int)($_POST["id"] ?? 0);
  if ($id <= 0) j(["ok" => false, "message" => "Invalid id"], 400);

  $stmt = $pdo->prepare("SELECT file_path FROM backups WHERE id = :id");
  $stmt->execute([":id" => $id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) j(["ok" => false, "message" => "Backup not found"], 404);

  $path = (string)$row["file_path"];
  if (file_exists($path) && must_be_inside_dir($path, $BACKUP_DIR)) {
    @unlink($path);
  }

  $stmt = $pdo->prepare("DELETE FROM backups WHERE id = :id");
  $stmt->execute([":id" => $id]);

  j(["ok" => true, "message" => "Backup deleted"]);
}

// -------------------------
// RESTORE
// -------------------------
if ($action === "restore") {
  set_time_limit(0);

  if (!file_exists($MYSQL_BIN)) {
    j(["ok"=>false,"message"=>"mysql.exe not found","path"=>$MYSQL_BIN], 500);
  }

  if (!isset($_FILES["file"])) j(["ok"=>false,"message"=>"No file uploaded"], 400);

  $f = $_FILES["file"];
  if (($f["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    j(["ok"=>false,"message"=>"Upload error: " . ($f["error"] ?? "unknown")], 400);
  }

  $tmp  = (string)$f["tmp_name"];
  $orig = safe_filename((string)($f["name"] ?? "restore.sql"));
  if (!preg_match('/\.sql$/i', $orig)) j(["ok"=>false,"message"=>"Only .sql restore is supported"], 400);

  if (!is_dir($BACKUP_DIR) || !is_writable($BACKUP_DIR)) {
    j(["ok"=>false,"message"=>"Backups folder is not writable","backup_dir"=>$BACKUP_DIR], 500);
  }

  $restorePath = $BACKUP_DIR . DIRECTORY_SEPARATOR . "RESTORE_" . time() . "_" . $orig;
  if (!move_uploaded_file($tmp, $restorePath)) {
    if (!copy($tmp, $restorePath)) j(["ok"=>false,"message"=>"Failed to save uploaded file"], 500);
  }

  $restoreDriveId = null;
  $restoreDriveError = null;
  if (function_exists("gd_upload_file")) {
    try {
      $driveName = "RESTORE_" . date("Y-m-d_His") . "_" . basename($restorePath);
      $up = gd_upload_file($restorePath, $driveName);
      $restoreDriveId = $up["id"] ?? null;
    } catch (Throwable $e) {
      $restoreDriveError = $e->getMessage();
    }
  }

  drop_all_tables($pdo);

  $sourcePath = str_replace("\\", "/", $restorePath);

  $cmd = '"' . $MYSQL_BIN . '"'
      . ' --host=' . escapeshellarg($DB_HOST)
      . ' --port=' . escapeshellarg($DB_PORT)
      . ' --user=' . escapeshellarg($DB_USER);

  if ($DB_PASS !== "") {
    $cmd .= ' --password=' . escapeshellarg($DB_PASS);
  }

  $cmd .= ' --default-character-set=utf8mb4 '
       . escapeshellarg($DB_NAME)
       . ' -e ' . escapeshellarg('SOURCE ' . $sourcePath);

  [$ret, $out] = run_cmd($cmd);

  if ($ret !== 0) {
    j([
      "ok"=>false,
      "message"=>"Restore failed",
      "details"=>$out ?: "No output. Check SQL errors / permissions / exec disabled.",
      "restore_drive_file_id"=>$restoreDriveId,
      "drive_error"=>$restoreDriveError
    ], 500);
  }

  // After restore, recreate backups table (because drop_all_tables removed it)
  ensure_backups_table($pdo);

  // Save restore in backups table (best effort)
  try {
    $sz = file_exists($restorePath) ? (int)filesize($restorePath) : 0;
    $stmt = $pdo->prepare("
      INSERT INTO backups (backup_code, file_name, file_path, file_size_bytes, backup_type, status, drive_file_id)
      VALUES (:code, :fn, :fp, :sz, 'Manual', 'Verified', :did)
    ");
    $stmt->execute([
      ":code"=>"RESTORE-" . date("Ymd-His"),
      ":fn"=>basename($restorePath),
      ":fp"=>$restorePath,
      ":sz"=>$sz,
      ":did"=>$restoreDriveId
    ]);
  } catch (Throwable $e) { }

  j([
    "ok"=>true,
    "message"=>"System restored successfully" . ($restoreDriveId ? " + uploaded SQL to Drive" : ""),
    "restore_drive_file_id"=>$restoreDriveId,
    "drive_error"=>$restoreDriveError
  ]);
}

j(["ok" => false, "message" => "Unknown action"], 400);