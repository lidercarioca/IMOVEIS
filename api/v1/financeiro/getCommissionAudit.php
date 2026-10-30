<?php

/**
 * GET /api/v1/financeiro/getCommissionAudit.php
 * 
 * Retorna histórico/auditoria de uma comissão específica
 * Query params:
 * - commission_id: ID da comissão (obrigatório)
 * - include_calculations: true|false - mostrar cálculos detalhados
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $currentUserId = $authContext->isAuthenticated() ? $authContext->getUserId() : ($_SESSION['user_id'] ?? null);

    if ($currentUserId === null || (int)$currentUserId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }

    initializeTenantMiddleware($GLOBALS['pdo']);
    $commissionId = $_GET['commission_id'] ?? null;
    $includeCalcs = $_GET['include_calculations'] === 'true';

    if (!$commissionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'commission_id obrigatório']);
        exit;
    }

    $service = new \App\Services\FinanceService($GLOBALS['pdo']);
    $response = $service->getCommissionAudit((int)$commissionId, $includeCalcs);

    if (!$response['success']) {
        http_response_code(500);
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar auditoria',
        'details' => $e->getMessage()
    ]);
}
