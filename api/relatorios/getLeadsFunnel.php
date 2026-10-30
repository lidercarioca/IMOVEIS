<?php
$rootPath = dirname(dirname(dirname(__FILE__)));
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/api/v1/middleware/AuthMiddleware.php';
require_once $rootPath . '/api/v1/middleware/TenantMiddleware.php';

\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();
global $pdo;

header('Content-Type: application/json');

try {
    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $isAdmin = \App\Auth\AuthContext::getInstance()->isAdmin();

    $reportService = new \App\Service\ReportService($pdo);
    echo json_encode($reportService->getLeadsFunnel($tenantId, 7, $userId, $isAdmin));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar funil de leads',
        'details' => $e->getMessage()
    ]);
}
