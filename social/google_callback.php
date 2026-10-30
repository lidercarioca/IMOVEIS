<?php
require_once __DIR__ . '/social_login_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Infrastructure/User/UserRepository.php';

use App\Infrastructure\User\UserRepository;

$debugLogPath = __DIR__ . '/../logs/social_oauth_debug.log';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', $debugLogPath);
error_reporting(E_ALL);
function debugSocialCallback(string $message)
{
    global $debugLogPath;
    $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    file_put_contents($debugLogPath, $line, FILE_APPEND | LOCK_EX);
}

debugSocialCallback('REQUEST: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
debugSocialCallback('GET: ' . json_encode($_GET, JSON_UNESCAPED_UNICODE));

define('SOCIAL_DEBUG_PATH', $debugLogPath);

$googleClientId = getenv('GOOGLE_CLIENT_ID');
$googleClientSecret = getenv('GOOGLE_CLIENT_SECRET');
if (empty($googleClientId) || empty($googleClientSecret)) {
    debugSocialCallback('Missing Google client credentials.');
    redirectWithError('Configuração do login Google não está completa.');
}

$code = $_GET['code'] ?? null;
if (empty($code)) {
    debugSocialCallback('No code parameter received.');
    redirectWithError('Falha na autenticação com Google.');
}

$redirectUri = getenv('GOOGLE_LOGIN_OAUTH_REDIRECT') ?: null;
if (empty($redirectUri)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $redirectUri = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/social/google_callback.php';
}

debugSocialCallback('Google callback redirect URI: ' . $redirectUri);

$client = new Google_Client();
$client->setClientId($googleClientId);
$client->setClientSecret($googleClientSecret);
$client->setRedirectUri($redirectUri);

try {
    $token = $client->fetchAccessTokenWithAuthCode($code);
    debugSocialCallback('Token response: ' . json_encode($token, JSON_UNESCAPED_UNICODE));
    if (isset($token['error'])) {
        debugSocialCallback('Token error: ' . ($token['error_description'] ?? $token['error']));
        redirectWithError('Erro de autenticação Google: ' . ($token['error_description'] ?? $token['error']));
    }

    if (isset($token['access_token'])) {
        $client->setAccessToken($token);
        debugSocialCallback('Set client access token');
    }

    debugSocialCallback('About to verify ID token');
    $payload = $client->verifyIdToken();
    debugSocialCallback('ID token payload: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    if (!$payload || empty($payload['email'])) {
        debugSocialCallback('Payload missing email or invalid.');
        redirectWithError('Não foi possível obter e-mail do Google.');
    }

    $email = filter_var($payload['email'], FILTER_SANITIZE_EMAIL);
    debugSocialCallback('Sanitized email: ' . $email);

    $userRepository = new UserRepository($pdo);
    $user = $userRepository->findByEmail($email);
    debugSocialCallback('Database email lookup executed for: ' . $email . ' | user found: ' . ($user ? 'yes' : 'no'));

    if (!$user || (int)($user['active'] ?? 0) !== 1) {
        $user = null;
        redirectWithError('Nenhum usuário ativo encontrado para este e-mail do Google.');
    }

    if (!performSocialLogin($pdo, $user, 'google')) {
        redirectWithError('Não foi possível efetuar login com Google.');
    }

    if (!empty($_SESSION['is_superadmin'])) {
        header('Location: /select_tenant.php');
    } else {
        header('Location: /painel.php');
    }
    exit;
} catch (Exception $e) {
    debugSocialCallback('Exception: ' . $e->getMessage());
    debugSocialCallback('Exception trace: ' . $e->getTraceAsString());
    redirectWithError('Erro no login Google: ' . $e->getMessage());
}
