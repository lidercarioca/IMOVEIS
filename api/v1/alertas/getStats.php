<?php

use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;
use App\Services\AlertService;

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Services/AlertService.php';

try {
    AuthMiddleware::handle();
    $tenantId = TenantMiddleware::resolveTenantId();

    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();

    $pdo = $GLOBALS['pdo'];
    $service = new AlertService($pdo);
    $result = $service->getStats($tenantId, $userId);

    http_response_code(200);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
