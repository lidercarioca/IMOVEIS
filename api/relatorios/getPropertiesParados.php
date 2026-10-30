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
    $dias = intval($_GET['dias'] ?? 30);
    $tipo = $_GET['tipo'] ?? '';

    // Data limite para "parado"
    $dataLimite = date('Y-m-d', strtotime("-$dias days"));

    // Query: Imóveis parados - agora via ReportService
    $reportService = new \App\Service\ReportService($pdo);
    $resultados = $reportService->getParkedPropertiesReport($tenantId, $dias, $tipo);

    $statusMap = ['active' => 'Ativo', 'inactive' => 'Inativo', 'sold' => 'Vendido', 'rented' => 'Alugado'];

    $dados = array_map(function ($row) use ($statusMap) {
        $diasParado = (int)floor((time() - strtotime($row['created_at'])) / 86400);
        return [
            'id' => $row['id'],
            'title' => $row['title'],
            'price' => (float)$row['price'],
            'status' => $statusMap[$row['status']] ?? $row['status'],
            'diasParado' => $diasParado,
            'label' => $row['title'],
            'valor' => (float)$row['price'],
            'dias' => $diasParado
        ];
    }, $resultados);

    echo json_encode([
        'success' => true,
        'data' => $dados,
        'diasParadoMinimo' => $dias,
        'total' => count($dados)
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
