<?php

use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

/**
 * Sincroniza agendamentos com Google Calendar
 * POST /api/v1/google/syncCalendar.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../config/env_loader.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/utils/error_logger.php';
require_once __DIR__ . '/../../../src/Infrastructure/Google/GoogleCalendarConnectionRepository.php';
require_once __DIR__ . '/../../../google/GoogleCalendarService.php';

header('Content-Type: application/json');

try {
    AuthMiddleware::handle();

    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = (int) $authContext->getUserId();
    $tenantId = (int) TenantMiddleware::resolveTenantId();

    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário não autenticado',
            'error_code' => 'USER_NOT_AUTHENTICATED'
        ]);
        exit;
    }

    if ($tenantId <= 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Tenant inválido ou não resolvido para esta sessão',
            'error_code' => 'TENANT_INVALID'
        ]);
        exit;
    }

    $syncService = new \App\Service\GoogleCalendarSyncService($pdo);
    if (!$syncService->validateUserBelongsToTenant($userId, $tenantId)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário não pertence ao tenant atual',
            'error_code' => 'USER_TENANT_MISMATCH'
        ]);
        exit;
    }

    $repository = new \App\Infrastructure\Google\GoogleCalendarConnectionRepository($pdo);
    $connection = $repository->findForUser($tenantId, $userId);

    if (!$connection || ($connection['status'] ?? 'active') !== 'active' || empty($connection['access_token'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token Google Calendar não configurado ou desconectado para este tenant/usuário. Autorize primeiro.',
            'error_code' => 'GOOGLE_CALENDAR_TOKEN_MISSING'
        ]);
        exit;
    }

    $calendarService = new GoogleCalendarService($tenantId, $userId, $pdo);
    if (!$calendarService->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token Google Calendar inválido, expirado ou desconectado.',
            'error_code' => 'GOOGLE_CALENDAR_TOKEN_INVALID'
        ]);
        exit;
    }

    $result = $calendarService->syncAllAgendamentos($userId, $tenantId);

    echo json_encode([
        'success' => true,
        'message' => 'Sincronização concluída com sucesso',
        'synced' => $result['synced'] ?? 0,
        'total' => $result['total'] ?? 0,
        'errors' => $result['errors'] ?? []
    ]);
} catch (Throwable $e) {
    error_log('Erro em syncCalendar.php: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    $response = [
        'success' => false,
        'message' => getenv('ENVIRONMENT') === 'development' ? $e->getMessage() : 'Erro interno ao sincronizar calendar',
        'error_code' => 'GOOGLE_CALENDAR_SYNC_ERROR'
    ];

    if (getenv('ENVIRONMENT') === 'development') {
        $response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5)
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
