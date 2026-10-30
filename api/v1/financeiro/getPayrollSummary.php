<?php

/**
 * GET /api/v1/financeiro/getPayrollSummary.php
 * 
 * Obtém resumo de comissões para folha de pagamento
 * Query params:
 * - month: YYYY-MM (obrigatório)
 * - user_id: ID do vendedor (opcional)
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Services\FinanceService;

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $currentUserId = $authContext->isAuthenticated() ? $authContext->getUserId() : ($_SESSION['user_id'] ?? null);

    if ($currentUserId === null || (int)$currentUserId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }

    initializeTenantMiddleware($GLOBALS['pdo']);
    $tenantId = getCurrentTenantId();

    $month = $_GET['month'] ?? null;

    if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Mês obrigatório (YYYY-MM)']);
        exit;
    }

    $userId = $_GET['user_id'] ?? null;

    $service = new FinanceService($GLOBALS['pdo']);
    $result = $service->getPayrollSummaryForApi($month, $userId);

    http_response_code(200);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar resumo de folha'
    ]);
}
