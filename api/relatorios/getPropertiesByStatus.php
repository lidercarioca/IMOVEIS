<?php
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/AuthMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/TenantMiddleware.php';

\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();
global $pdo;

header('Content-Type: application/json');

try {
    $periodo = $_GET['periodo'] ?? 'mes';
    $status = $_GET['status'] ?? '';
    $tipo = $_GET['tipo'] ?? '';

    // Calcula datas baseado no período
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

    // Query: Imóveis por status - agora via ReportService
    $reportService = new \App\Service\ReportService($pdo);
    $resultados = $reportService->getPropertiesByStatusReport($tenantId, $status, $tipo);

    // Mapeia status para labels
    $statusMap = [
        'active' => 'Disponível',
        'pending' => 'Pendente',
        'vendido' => 'Vendido',
        'alugado' => 'Alugado',
        'inactive' => 'Inativo'
    ];

    $dados = array_map(function ($row) use ($statusMap) {
        return [
            'status' => $statusMap[$row['status']] ?? $row['status'],
            'label' => $statusMap[$row['status']] ?? $row['status'],
            'count' => (int)$row['count'],
            'quantidade' => (int)$row['count']
        ];
    }, $resultados);

    echo json_encode([
        'success' => true,
        'data' => $dados,
        'periodo' => $periodo,
        'dataInicio' => $dataInicio,
        'dataFim' => $dataFim
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
