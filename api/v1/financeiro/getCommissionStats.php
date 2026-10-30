<?php

/**
 * GET /api/v1/financeiro/getCommissionStats.php
 * 
 * Obtém estatísticas de comissões por vendedor
 * Query params:
 * - user_id: ID do vendedor (obrigatório)
 * - start_date: YYYY-MM-DD (opcional, padrão: 30 dias atrás)
 * - end_date: YYYY-MM-DD (opcional, padrão: hoje)
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/utils/InputValidator.php';
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

    $userId = !empty($_GET['user_id']) ? InputValidator::getInt($_GET['user_id'], 'ID do usuário', 1) : null;
    $startDate = isset($_GET['start_date']) ? InputValidator::getDate($_GET['start_date'], 'Data inicial', false) : null;
    $endDate = isset($_GET['end_date']) ? InputValidator::getDate($_GET['end_date'], 'Data final', false) : null;

    $service = new FinanceService($GLOBALS['pdo']);
    $result = $service->getCommissionStatsForApi($userId, $startDate, $endDate);

    http_response_code(200);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar estatísticas'
    ]);
}
