<?php
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

checkApiAuth();

$authContext = \App\Auth\AuthContext::getInstance();
if (!$authContext->isAdmin() && !$authContext->isSuperAdmin()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Apenas administradores podem acessar relatórios.'
    ]);
    exit;
}

// Inicializa isolamento por tenant
initializeTenantMiddleware($GLOBALS['pdo']);
$tenantId = getCurrentTenantId();

try {
    $requestData = json_decode(file_get_contents('php://input'), true);
    $reportType = $requestData['type'] ?? '';
    $period = $requestData['period'] ?? 'month';

    $reportService = new \App\Service\ReportService($pdo);
    $response = $reportService->getReports([
        'type' => $reportType,
        'period' => $period,
        'tenantId' => $tenantId,
    ]);

    echo json_encode($response);
} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar relatório: ' . $e->getMessage()
    ]);
}
