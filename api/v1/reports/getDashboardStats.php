<?php
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/middleware/api_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';

// Verifica autenticação da API
checkApiAuth();

// Inicializa isolamento por tenant
initializeTenantMiddleware($GLOBALS['pdo']);
$tenantId = getCurrentTenantId();

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Access-Control-Allow-Credentials: true');

$authContext = \App\Auth\AuthContext::getInstance();
$userId = $authContext->getUserId();
$isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();

if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado'
    ]);
    exit;
}

// Regenera ID da sessão periodicamente
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Pega o período solicitado
$period = $_GET['period'] ?? '7'; // 7, 30 ou 90 dias
$period = filter_var($period, FILTER_VALIDATE_INT);
$period = in_array($period, [7, 30, 90]) ? $period : 7;

try {
    global $pdo;

    if (!isset($pdo)) {
        throw new Exception("Erro de conexão com o banco de dados");
    }

    $reportService = new \App\Service\ReportService($pdo);
    $response = $reportService->getDashboardStats([
        'tenantId' => $tenantId,
        'period' => $period,
        'isAdmin' => $isAdmin,
        'userId' => $userId,
    ]);

    echo json_encode($response, JSON_NUMERIC_CHECK);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro no getDashboardStats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar dados: ' . $e->getMessage()
    ]);
}
