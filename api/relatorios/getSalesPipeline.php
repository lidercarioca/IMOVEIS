<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/AuthMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/TenantMiddleware.php';

\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();
global $pdo;

header('Content-Type: application/json; charset=utf-8');

try {
    $reportService = new \App\Service\ReportService($pdo);
    echo json_encode($reportService->getSalesPipeline($tenantId));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar relatório'
    ]);
}
