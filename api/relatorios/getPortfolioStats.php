<?php

/**
 * Portfolio Statistics API
 * Retorna análise de portfolio - tipos de imóvel, investimentos, tendências de preço
 * COM ISOLAMENTO DE TENANT_ID
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__FILE__))) . '/auth.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/database.php';
require_once dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__FILE__))) . '/app/utils/error_logger.php';
require_once dirname(dirname(dirname(__FILE__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__FILE__))) . '/app/middleware/api_auth.php';

checkAuth();
checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json');

try {
    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $isAdmin = \App\Auth\AuthContext::getInstance()->isAdmin();

    $reportService = new \App\Service\ReportService($pdo);
    $response = $reportService->getPortfolioStats($tenantId, $userId, $isAdmin);

    echo json_encode($response);
} catch (Throwable $e) {
    $context = [
        'user_id' => \App\Auth\AuthContext::getInstance()->getUserId(),
        'is_admin' => \App\Auth\AuthContext::getInstance()->isAdmin(),
        'tenant_id' => $tenantId ?? null,
    ];
    if (function_exists('log_exception')) {
        log_exception($e, $context);
    }

    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Erro ao carregar estatísticas do portfolio'
    ];
    if (getenv('ENVIRONMENT') === 'development') {
        $response['details'] = $e->getMessage();
        $response['trace'] = $e->getTraceAsString();
    }
    echo json_encode($response);
}
