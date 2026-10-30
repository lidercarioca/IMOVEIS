<?php

use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;
use App\Services\AlertService;

if (!defined('TESTING_ENDPOINT')) {
    header('Content-Type: application/json');
}

if (!defined('TESTING_ENDPOINT')) {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Services/AlertService.php';
} else {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Services/AlertService.php';
    $GLOBALS['pdo'] = $GLOBALS['pdo'] ?? getConnection();
}

try {
    AuthMiddleware::handle();

    $alertId = $_GET['alert_id'] ?? null;

    if (empty($alertId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do alerta obrigatório']);
        exit;
    }

    $pdo = $GLOBALS['pdo'];
    $tenantId = TenantMiddleware::resolveTenantId();

    $authContext = \App\Auth\AuthContext::getInstance();
    $resolvedUserId = $authContext->getUserId();
    $userFilter = $authContext->isAdmin() ? null : $resolvedUserId;

    $service = new AlertService($pdo);
    $result = $service->deleteAlert($tenantId, (int)$alertId, $userFilter);

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
}
