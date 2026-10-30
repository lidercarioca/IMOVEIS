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

    // Query: Imóveis vendidos/alugados por período - agora via ReportService
    $reportService = new \App\Service\ReportService($pdo);
    $resultados = $reportService->getSoldRentedReport($tenantId, $dataInicio, $dataFim, $tipo);

    // Mapeia status para português
    $statusMap = [
        'completed' => 'Concluído',
        'pending' => 'Pendente',
        'cancelled' => 'Cancelado',
        'sold' => 'Vendido',
        'rented' => 'Alugado'
    ];

    $dados = array_map(function ($row) use ($statusMap) {
        return [
            'status' => $statusMap[$row['status']] ?? $row['status'],
            'count' => (int)($row['count'] ?? 0),
            'valor_total' => (float)($row['valor_total'] ?? 0),
            'valor_medio' => (float)($row['valor_medio'] ?? 0)
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
