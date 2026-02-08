<?php
// php/google_config.php
declare(strict_types=1);

$autoload1 = __DIR__ . '/../vendor/autoload.php';
$autoload2 = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload1)) {
  require_once $autoload1;
} elseif (file_exists($autoload2)) {
  require_once $autoload2;
} else {
  throw new Exception("vendor/autoload.php not found. Run: composer require google/apiclient");
}

$credentialsPath = __DIR__ . '/Drive.json';
$tokenPath       = __DIR__ . '/token.json';

if (!file_exists($credentialsPath)) {
  throw new Exception("Drive.json not found at: " . $credentialsPath);
}
if (!file_exists($tokenPath)) {
  throw new Exception("token.json not found. Run setup_auth.php first.");
}

/**
 * PUT YOUR GOOGLE DRIVE FOLDER ID HERE (the AP Tec folder)
 * Example: "1AbCdEfGhIjKlMn..."
 */
$AP_TEC_FOLDER_ID = "PUT_YOUR_FOLDER_ID_HERE";

/* GOOGLE CLIENT */
$client = new Google_Client();
$client->setAuthConfig($credentialsPath);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');
$client->setScopes([Google_Service_Drive::DRIVE_FILE]);

$token = json_decode((string)file_get_contents($tokenPath), true);
if (!is_array($token)) {
  throw new Exception("token.json is invalid");
}

$client->setAccessToken($token);

/* refresh */
if ($client->isAccessTokenExpired()) {
  $refreshToken = $client->getRefreshToken() ?? ($token['refresh_token'] ?? null);
  if (!$refreshToken) {
    throw new Exception("No refresh_token found. Re-run setup_auth.php");
  }

  $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
  if (isset($newToken['error'])) {
    throw new Exception("Token refresh failed: " . ($newToken['error'] ?? 'unknown'));
  }

  if (!isset($newToken['refresh_token'])) {
    $newToken['refresh_token'] = $refreshToken;
  }

  file_put_contents($tokenPath, json_encode($newToken, JSON_UNESCAPED_SLASHES));
  $client->setAccessToken($newToken);
}

$driveService = new Google_Service_Drive($client);