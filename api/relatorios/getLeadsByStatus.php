<?php
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/AuthMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/TenantMiddleware.php';

\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();

header('Content-Type: application/json');

try {
    $periodo = $_GET['periodo'] ?? 'mes';
    $status = $_GET['status'] ?? '';

    $dataFim = date('Y-m-d');
    if ($periodo === 'mes') {
        $dataInicio = date('Y-m-01');
    } elseif ($periodo === 'trimestre') {
        $trimestre = ceil(date('m') / 3);
        $dataInicio = date('Y-' . str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
    } elseif ($periodo === 'ano') {
        $dataInicio = date('Y-01-01');
    } else {
        $dataInicio = date('Y-m-01', strtotime('-30 days'));
    }

    $reportService = new \App\Service\ReportService($pdo);
    $payload = $reportService->getLeadsByStatus($tenantId, $status, $periodo);
    $payload['periodo'] = $periodo;
    $payload['dataInicio'] = $dataInicio;
    $payload['dataFim'] = $dataFim;

    echo json_encode($payload);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
