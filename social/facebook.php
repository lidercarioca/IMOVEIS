<?php
require_once __DIR__ . '/../app/security/Security.php';
Security::setSecurityHeaders();
Security::blockLogsAccess();
Security::init();

require_once __DIR__ . '/../config/env_loader.php';

$facebookClientId = getenv('FACEBOOK_CLIENT_ID');
$facebookClientSecret = getenv('FACEBOOK_CLIENT_SECRET');
if (empty($facebookClientId) || empty($facebookClientSecret)) {
    header('Location: /login.php?social_error=' . urlencode('Login Facebook não está configurado.'));
    exit;
}

$redirectUri = getenv('FACEBOOK_OAUTH_REDIRECT') ?: null;
if (empty($redirectUri)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $redirectUri = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/social/facebook_callback.php';
}

$state = bin2hex(random_bytes(16));
$_SESSION['facebook_oauth_state'] = $state;

$params = http_build_query([
    'client_id' => $facebookClientId,
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'scope' => 'email,public_profile',
    'response_type' => 'code'
]);

header('Location: https://www.facebook.com/v16.0/dialog/oauth?' . $params);
exit;
