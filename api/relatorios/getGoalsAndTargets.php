<?php

/**
 * Goals & Targets API
 * Retorna metas e objetivos do período com progresso real vs meta
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/AuthMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/TenantMiddleware.php';

\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();

header('Content-Type: application/json');

try {
    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $isAdmin = \App\Auth\AuthContext::getInstance()->isAdmin();
    $period = (int)($_GET['period'] ?? 30);

    $reportService = new \App\Service\ReportService($pdo);
    $response = $reportService->getGoalsAndTargets($tenantId, $period, $isAdmin, $userId);

    echo json_encode($response);
} catch (Throwable $e) {
    $context = [
        'user_id' => \App\Auth\AuthContext::getInstance()->getUserId(),
        'period' => $period ?? null,
        'is_admin' => \App\Auth\AuthContext::getInstance()->isAdmin(),
    ];
    if (function_exists('log_exception')) {
        log_exception($e, $context);
    }

    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Erro ao carregar metas',
    ];
    if (getenv('ENVIRONMENT') === 'development') {
        $response['details'] = $e->getMessage();
        $response['trace'] = $e->getTraceAsString();
    }
    echo json_encode($response);
}
