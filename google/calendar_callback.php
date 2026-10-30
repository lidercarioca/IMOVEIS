<?php
session_start();

/**
 * Google OAuth Callback - Google Calendar
 * Processa a resposta da autorização do Google
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_manager.php';
require_once __DIR__ . '/GoogleCalendarService.php';
require_once __DIR__ . '/../src/Infrastructure/Google/GoogleCalendarConnectionRepository.php';
require_once __DIR__ . '/google_oauth_state.php';

try {
    $tenantId = (int) ($_SESSION['google_oauth_tenant_id'] ?? 0);
    $userId = (int) ($_SESSION['google_oauth_user_id'] ?? 0);
    $state = $_GET['state'] ?? '';

    if (!google_calendar_is_valid_oauth_state($state, $tenantId, $userId)) {
        throw new RuntimeException('Estado OAuth inválido, expirado ou pertencente a outro usuário/tenant.');
    }

    $userStmt = $GLOBALS['pdo']->prepare('SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $userStmt->execute([$userId, $tenantId]);
    if (!$userStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('Usuário não pertence ao tenant atual.');
    }

    $calendarService = new GoogleCalendarService($tenantId, $userId, $GLOBALS['pdo']);
    $client = $calendarService->getClient();

    if (!isset($_GET['code'])) {
        throw new RuntimeException('Código de autorização não recebido do Google');
    }

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        throw new RuntimeException('Erro ao obter token do Google: ' . \GoogleCalendarService::sanitizeGoogleErrorDetails($token));
    }

    $calendarService->saveAccessToken($token);
    google_calendar_consume_oauth_state();

    header('Location: /painel.php?google_calendar=success');
    exit();
} catch (Throwable $e) {
    google_calendar_consume_oauth_state();
    header('Location: /painel.php?google_calendar=error&message=' . urlencode($e->getMessage()));
    exit();
}
