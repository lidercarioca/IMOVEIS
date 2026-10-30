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

    // Faixas de preço
    $faixas = [
        ['min' => 0, 'max' => 200000, 'label' => 'Até R$ 200.000'],
        ['min' => 200000, 'max' => 500000, 'label' => 'R$ 200.000 - R$ 500.000'],
        ['min' => 500000, 'max' => 1000000, 'label' => 'R$ 500.000 - R$ 1.000.000'],
        ['min' => 1000000, 'max' => 5000000, 'label' => 'R$ 1.000.000 - R$ 5.000.000'],
        ['min' => 5000000, 'max' => PHP_INT_MAX, 'label' => 'Acima de R$ 5.000.000']
    ];

    // Query: Contagem por faixa de preço - agora via ReportService
    $reportService = new \App\Service\ReportService($pdo);
    $dados = $reportService->getPropertiesByPriceReport($tenantId, $faixas, $status, $tipo);

    // Adiciona label a cada item
    foreach ($dados as &$item) {
        $item['label'] = $item['faixa'] ?? '';
        $item['quantidade'] = (int)($item['count'] ?? 0);
    }
    unset($item);

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
