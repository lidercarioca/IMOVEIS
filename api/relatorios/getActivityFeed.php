<?php

/**
 * Activity Feed API
 * Retorna as últimas atividades do sistema (criação, atualização, vendas, etc)
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/AuthMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/TenantMiddleware.php';

\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();

header('Content-Type: application/json');

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
    $limit = (int)($_GET['limit'] ?? 5);
    $offset = (int)($_GET['offset'] ?? 0);

    $reportService = new \App\Service\ReportService($pdo);
    $response = $reportService->getActivityFeed($tenantId, $limit, $offset, $isAdmin, $userId);

    echo json_encode($response);
} catch (Throwable $e) {
    $authContext = \App\Auth\AuthContext::getInstance();
    $context = [
        'user_id' => $authContext->getUserId(),
        'tenant_id' => $tenantId ?? null,
        'is_admin' => $authContext->isAdmin() || $authContext->isSuperAdmin(),
    ];
    if (function_exists('log_exception')) {
        log_exception($e, $context);
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar atividades',
        'details' => $e->getMessage(),
    ]);
}
