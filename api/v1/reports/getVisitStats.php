<?php
// api/getVisitStats.php - Obtém estatísticas de visitas
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

checkApiAuth();

// Inicializar isolamento por tenant
initializeTenantMiddleware($GLOBALS['pdo']);
$tenantId = getCurrentTenantId();

try {
    // Período: últimos 30 dias por padrão
    $period = $_GET['period'] ?? '30'; // dias

    $reportService = new \App\Service\ReportService($pdo);
    $response = $reportService->getVisitStats([
        'tenantId' => $tenantId,
        'period' => $period,
    ]);

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
