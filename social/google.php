<?php
require_once __DIR__ . '/../app/security/Security.php';
Security::setSecurityHeaders();
Security::blockLogsAccess();
Security::init();

require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../vendor/autoload.php';

$googleClientId = getenv('GOOGLE_CLIENT_ID');
$googleClientSecret = getenv('GOOGLE_CLIENT_SECRET');
if (empty($googleClientId) || empty($googleClientSecret)) {
    header('Location: /login.php?social_error=' . urlencode('Login Google não está configurado.')); 
    exit;
}

$redirectUri = getenv('GOOGLE_LOGIN_OAUTH_REDIRECT') ?: null;
if (empty($redirectUri)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $redirectUri = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/social/google_callback.php';
}

$client = new Google_Client();
$client->setClientId($googleClientId);
$client->setClientSecret($googleClientSecret);
$client->setRedirectUri($redirectUri);
$client->setScopes(['openid', 'email', 'profile']);
$client->setAccessType('offline');
$client->setPrompt('select_account');

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
