<?php
session_start();

/**
 * Endpoint: Callback OAuth
 * Google redireciona aqui após autorização.
 * Troca o code por token e grava por tenant + usuário.
 */

require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/GoogleCalendarService.php';
require_once __DIR__ . '/../src/Infrastructure/Google/GoogleCalendarConnectionRepository.php';
require_once __DIR__ . '/google_oauth_state.php';

try {
    $state = $_GET['state'] ?? '';
    $tenantId = (int) ($_SESSION['google_oauth_tenant_id'] ?? 0);
    $userId = (int) ($_SESSION['google_oauth_user_id'] ?? 0);

    if (!google_calendar_is_valid_oauth_state($state, $tenantId, $userId)) {
        throw new RuntimeException('Estado OAuth inválido, expirado ou pertencente a outro usuário/tenant.');
    }

    $userStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $userStmt->execute([$userId, $tenantId]);
    if (!$userStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('Usuário não pertence ao tenant atual.');
    }

    if (!isset($_GET['code'])) {
        if (isset($_GET['error'])) {
            throw new RuntimeException('Erro Google: ' . $_GET['error']);
        }
        throw new RuntimeException('Código OAuth não fornecido.');
    }

    $client = new \Google_Client();
    $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
    $client->setRedirectUri(getenv('GOOGLE_OAUTH_REDIRECT') ?: 'http://localhost/google/oauth_callback.php');
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setScopes([
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events'
    ]);
    $client->setState($state);

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        throw new RuntimeException('Erro ao obter token do Google: ' . \GoogleCalendarService::sanitizeGoogleErrorDetails($token));
    }

    $providerAccountId = null;
    if (!empty($token['id_token'])) {
        $parts = explode('.', $token['id_token']);
        if (isset($parts[1])) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $providerAccountId = $payload['sub'] ?? null;
        }
    }

    $token['provider'] = 'google';
    $token['calendar_id'] = 'primary';
    $token['status'] = 'active';
    $token['provider_account_id'] = $providerAccountId;
    $token['scopes'] = [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events'
    ];

    $repository = new \App\Infrastructure\Google\GoogleCalendarConnectionRepository($pdo);
    $repository->upsertConnection($tenantId, $userId, $token, null, $providerAccountId, 'primary');

    google_calendar_consume_oauth_state();

    echo '<h1>✓ Autorização concluída com sucesso!</h1>';
    echo '<p>Token vinculado ao tenant e ao usuário atual.</p>';
    echo '<p><a href="/painel.php">Voltar ao painel</a></p>';
} catch (Exception $e) {
    google_calendar_consume_oauth_state();
    http_response_code(500);
    echo '<h1>✗ Erro na autorização OAuth</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/painel.php">Voltar</a></p>';
    exit(1);
} catch (Throwable $e) {
    google_calendar_consume_oauth_state();
    http_response_code(500);
    echo '<h1>✗ Erro na autorização OAuth</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/painel.php">Voltar</a></p>';
    exit(1);
}
