<?php

/**
 * POST /api/v1/financeiro/cancelCommission.php
 * 
 * Cancela uma comissão (soft delete)
 * Body JSON:
 * {
 *   "commission_id": int,
 *   "reason": "string (opcional)"
 * }
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Infrastructure\User\UserRepository;
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

    // Apenas gerentes/admins podem cancelar
    $userRepository = new UserRepository($GLOBALS['pdo']);
    $userRole = strtolower(trim($userRepository->getRole((int)$currentUserId, $tenantId) ?? ''));
    if (!in_array($userRole, ['admin', 'administrator', 'gerente'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permissão negada']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['commission_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID da comissão obrigatório']);
        exit;
    }

    $service = new FinanceService($GLOBALS['pdo']);
    $response = $service->cancelCommission((int)$data['commission_id'], $data['reason'] ?? 'Cancelado pelo gerente');

    if (!$response['success']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $response['error']]);
        exit;
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno']);
}
