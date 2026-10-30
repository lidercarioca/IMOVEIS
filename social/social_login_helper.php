<?php
require_once __DIR__ . '/../app/security/Security.php';
require_once __DIR__ . '/../app/utils/logger_functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Infrastructure/User/UserRepository.php';
require_once __DIR__ . '/../src/Application/User/UserService.php';

use App\Infrastructure\User\UserRepository;
use App\Application\User\UserService;

function buildSocialRedirectUri(string $path): string
{
    $siteUrl = getenv('SITE_URL');
    if (!empty($siteUrl)) {
        return rtrim($siteUrl, '/') . '/' . ltrim($path, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function performSocialLogin(PDO $pdo, array $user, string $provider): bool
{
    if (empty($user) || empty($user['id']) || (int)($user['active'] ?? 0) !== 1) {
        return false;
    }

    session_unset();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['tenant_id'] = $user['tenant_id'];
    $_SESSION['is_superadmin'] = (int)($user['is_superadmin'] ?? 0);
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();

    $userRepository = new UserRepository($pdo);
    $userService = new UserService($userRepository);
    $userService->recordLoginSuccess($user['id']);

    log_user_action('social_login_success', [
        'provider' => $provider,
        'user_id' => $user['id'],
        'username' => $user['username'] ?? $user['email']
    ]);

    return true;
}

function redirectWithError(string $message): void
{
    $query = http_build_query(['social_error' => $message]);
    header('Location: /login.php?' . $query);
    exit;
}
