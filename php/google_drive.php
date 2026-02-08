<?php
// php/google_drive.php
declare(strict_types=1);

/**
 * IMPORTANT:
 * Move your Drive.json and token.json OUTSIDE public web root if possible.
 * Example recommended:
 *   php/secure/Drive.json
 *   php/secure/token.json
 */

const GOOGLE_DRIVE_JSON = __DIR__ . "/../secure/Drive.json";
const GOOGLE_TOKEN_JSON = __DIR__ . "/../secure/token.json";

// Optional: upload into a specific Google Drive folder.
// Put a folder ID string here, or keep null to upload to "My Drive" root.
const DRIVE_FOLDER_ID = null; // e.g. "1AbCdEfGhIjKlMnOpQrStUvWxYz"

function gd_read_json(string $path): array {
    if (!file_exists($path)) {
        throw new RuntimeException("Missing file: " . $path);
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw ?: "{}", true);
    if (!is_array($data)) throw new RuntimeException("Invalid JSON: " . $path);
    return $data;
}

function gd_save_json(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Refresh access token using refresh_token.
 */
function gd_refresh_access_token(): string {
    $client = gd_read_json(GOOGLE_DRIVE_JSON);
    $token  = gd_read_json(GOOGLE_TOKEN_JSON);

    $clientId = $client["web"]["client_id"] ?? "";
    $clientSecret = $client["web"]["client_secret"] ?? "";
    $tokenUri = $client["web"]["token_uri"] ?? "https://oauth2.googleapis.com/token";
    $refreshToken = $token["refresh_token"] ?? "";

    if ($clientId === "" || $clientSecret === "" || $refreshToken === "") {
        throw new RuntimeException("Google OAuth config is incomplete (client_id/client_secret/refresh_token).");
    }

    $post = http_build_query([
        "client_id" => $clientId,
        "client_secret" => $clientSecret,
        "refresh_token" => $refreshToken,
        "grant_type" => "refresh_token",
    ]);

    $ch = curl_init($tokenUri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded"],
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        throw new RuntimeException("Token refresh failed: " . $err);
    }

    $data = json_decode($res, true);
    if (!is_array($data) || empty($data["access_token"])) {
        throw new RuntimeException("Token refresh failed (bad response): " . $res);
    }

    // Update token.json with new access token + created timestamp
    $token["access_token"] = $data["access_token"];
    $token["expires_in"] = $data["expires_in"] ?? 3600;
    $token["token_type"] = $data["token_type"] ?? "Bearer";
    $token["created"] = time();
    gd_save_json(GOOGLE_TOKEN_JSON, $token);

    return $token["access_token"];
}

function gd_get_access_token(): string {
    $token = gd_read_json(GOOGLE_TOKEN_JSON);

    $access = (string)($token["access_token"] ?? "");
    $created = (int)($token["created"] ?? 0);
    $expires = (int)($token["expires_in"] ?? 0);

    // Refresh if missing or expiring
    if ($access === "" || $created === 0 || $expires === 0 || (time() > ($created + $expires - 60))) {
        return gd_refresh_access_token();
    }
    return $access;
}

/**
 * Upload a local file to Google Drive.
 * Returns: ["id" => "...", "name" => "..."]
 */
function gd_upload_file(string $localPath, string $driveName, ?string $folderId = DRIVE_FOLDER_ID): array {
    if (!file_exists($localPath)) {
        throw new RuntimeException("File not found: " . $localPath);
    }

    $accessToken = gd_get_access_token();
    $mime = "application/sql";
    $fileData = file_get_contents($localPath);

    $metadata = ["name" => $driveName];
    if ($folderId) $metadata["parents"] = [$folderId];

    $boundary = "----aptec" . bin2hex(random_bytes(8));
    $body =
        "--{$boundary}\r\n" .
        "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
        json_encode($metadata) . "\r\n" .
        "--{$boundary}\r\n" .
        "Content-Type: {$mime}\r\n\r\n" .
        $fileData . "\r\n" .
        "--{$boundary}--\r\n";

    $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $accessToken,
            "Content-Type: multipart/related; boundary=" . $boundary,
        ],
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        throw new RuntimeException("Drive upload failed: " . $err);
    }
    $data = json_decode($res, true);

    if ($code < 200 || $code >= 300 || !is_array($data) || empty($data["id"])) {
        throw new RuntimeException("Drive upload failed (HTTP {$code}): " . $res);
    }

    return ["id" => $data["id"], "name" => $data["name"] ?? $driveName];
}