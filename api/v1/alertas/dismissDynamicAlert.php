<?php

use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;
use App\Services\AlertService;

/**
 * POST /api/v1/alertas/dismissDynamicAlert.php
 * 
 * Resolve/marca alerta como resolvido
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';

try {
    AuthMiddleware::handle();

    global $pdo;
    $tenantId = TenantMiddleware::resolveTenantId();
    $resolvedUserId = \App\Auth\AuthContext::getInstance()->getUserId();

    $data = json_decode(file_get_contents('php://input'), true);
    $alertId = $data['alert_id'] ?? null;
    $alertType = $data['alert_type'] ?? null;
    $relatedEntityId = $data['related_entity_id'] ?? null;

    if (empty($alertId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do alerta obrigatório']);
        exit;
    }

    $service = new AlertService($pdo);
    $result = $service->dismissDynamicAlert($tenantId, $alertType, $relatedEntityId, $alertId, $resolvedUserId);

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
        'error' => 'Erro: ' . $e->getMessage()
    ]);
}
