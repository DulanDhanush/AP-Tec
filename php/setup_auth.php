<?php
// php/setup_auth.php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/Drive.json');
$client->addScope(Google_Service_Drive::DRIVE_FILE);

// IMPORTANT: this must match your Google Cloud OAuth redirect URI exactly
// Example:
$client->setRedirectUri('http://localhost/DULA_FNL/php/setup_auth.php');

$client->setAccessType('offline');
$client->setPrompt('select_account consent');

$tokenPath = __DIR__ . '/token.json';

if (isset($_GET['code'])) {
  $accessToken = $client->fetchAccessTokenWithAuthCode((string)$_GET['code']);

  if (isset($accessToken['error'])) {
    throw new Exception("Auth error: " . ($accessToken['error_description'] ?? $accessToken['error']));
  }

  // Save FULL token (includes refresh_token when consent is given)
  file_put_contents($tokenPath, json_encode($accessToken, JSON_UNESCAPED_SLASHES));

  echo "<h1>Success!</h1>";
  echo "Token saved to <b>token.json</b>. You can now use Drive upload.";
  exit;
}

$authUrl = $client->createAuthUrl();
echo "<h3>One-Time Setup</h3>";
echo "<p>Authorize Google Drive access:</p>";
echo "<a href='{$authUrl}'>Click here to authorize</a>";