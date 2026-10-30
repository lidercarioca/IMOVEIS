<?php

/**
 * API Endpoint: Filtered Dashboard KPIs
 * Purpose: Get KPIs with advanced filters (user, category, region)
 * Date: 2026-01-16
 * COM ISOLAMENTO DE TENANT_ID
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/middleware/api_auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/AuthMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/TenantMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

// Fluxo canônico do projeto: identidade autenticada via AuthContext e tenant via TenantContext/TenantMiddleware.
\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();

header('Content-Type: application/json');

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $actorId = $authContext->getUserId();
    $period = (int)($_GET['period'] ?? 7);
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $categoria = isset($_GET['category']) ? $_GET['category'] : null;
    $regiao = isset($_GET['region']) ? $_GET['region'] : null;

    $reportService = new \App\Service\ReportService($pdo);
    $payload = $reportService->getFilteredDashboardKPIs($tenantId, $period, $userId, $categoria, $regiao);

    echo json_encode($payload);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro em getFilteredDashboardKPIs: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar KPIs filtrados',
        'details' => $e->getMessage(),
    ]);
}
