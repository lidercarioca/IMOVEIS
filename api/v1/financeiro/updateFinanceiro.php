<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

checkAuth();
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $authContext = \App\Auth\AuthContext::getInstance();
    $currentUserId = $authContext->isAuthenticated() ? $authContext->getUserId() : ($_SESSION['user_id'] ?? null);
    $isAdmin = $authContext->isAuthenticated() ? $authContext->isAdmin() : (function_exists('isAdmin') ? isAdmin() : false);

    $service = new \App\Services\FinanceService($pdo);
    $response = $service->updateTransaction((int)($data['id'] ?? 0), $data, (int)($currentUserId ?? 0), $isAdmin);

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
