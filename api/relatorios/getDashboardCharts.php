<?php

/**
 * Dashboard Charts Endpoint
 * 
 * Retorna gráficos para o dashboard:
 * - Tendência de vendas
 * - Funil de leads
 * - Desempenho por categoria
 * - Vendas por bairro
 * 
 * COM ISOLAMENTO DE TENANT_ID
 */

// Buffer de saída para evitar que warnings/notice quebrem o JSON
ob_start();

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/middleware/api_auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/AuthMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/TenantMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

// Fluxo canônico do projeto: identidade autenticada via AuthContext e tenant via TenantContext/TenantMiddleware.
\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();

// Garante que a resposta será JSON
header('Content-Type: application/json');

try {
    // Parâmetros da requisição
    $period = (int)($_GET['period'] ?? 90);
    $chartType = $_GET['type'] ?? 'all';

    // Instanciar e chamar ReportService
    $reportService = new \App\Service\ReportService($pdo);
    $result = $reportService->getDashboardCharts([
        'tenantId' => $tenantId,
        'period' => $period,
        'type' => $chartType,
    ]);

    // Limpa qualquer output gerado por includes/avisos e envia JSON limpo
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro em getDashboardCharts: " . $e->getMessage());
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar gráficos do dashboard',
        'details' => $e->getMessage()
    ]);
}
