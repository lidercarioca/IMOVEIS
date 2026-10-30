<?php
session_start();

/**
 * Endpoint: Inicia fluxo OAuth para autorizar acesso ao Google Calendar
 * Redireciona o usuário para a Google Consent Screen
 *
 * Acesso: http://localhost/google/oauth.php
 */

require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/google_oauth_state.php';

try {
    $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($tenantId <= 0 || $userId <= 0) {
        header('Location: /login.php');
        exit;
    }

    $userStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $userStmt->execute([$userId, $tenantId]);
    if (!$userStmt->fetch(PDO::FETCH_ASSOC)) {
        header('Location: /login.php');
        exit;
    }

    $state = google_calendar_build_oauth_state($tenantId, $userId)['state'];

    $client = new \Google_Client();
    $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
    $client->setRedirectUri(getenv('GOOGLE_OAUTH_REDIRECT') ?: 'http://localhost/google/oauth_callback.php');
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setState($state);
    $client->setScopes([
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events'
    ]);

    $authUrl = $client->createAuthUrl();
    header('Location: ' . $authUrl);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Erro ao iniciar OAuth: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit(1);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao iniciar OAuth: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit(1);
}
