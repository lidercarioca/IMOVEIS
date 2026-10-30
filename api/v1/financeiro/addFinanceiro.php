<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

checkAuth();
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $authContext = \App\Auth\AuthContext::getInstance();
    $isAdmin = $authContext->isAuthenticated() ? $authContext->isAdmin() : (function_exists('isAdmin') ? isAdmin() : false);

    $service = new \App\Services\FinanceService($pdo);
    $response = $service->createTransaction($data, $isAdmin);

    if (!$response['success']) {
        throw new Exception($response['error']);
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
