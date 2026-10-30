<?php

/**
 * GET /api/v1/financeiro/getCommissions.php
 * 
 * Obtém lista de comissões com filtros
 * Query params:
 * - user_id: ID do vendedor (opcional)
 * - status: pendente|aprovada|paga|cancelada (opcional)
 * - start_date: YYYY-MM-DD (opcional)
 * - end_date: YYYY-MM-DD (opcional)
 * - page: número da página (padrão: 1)
 * - limit: registros por página (padrão: 20)
 */

header('Content-Type: application/json');

// Permitir CORS se necessário
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Infrastructure\User\UserRepository;

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $currentUserId = $authContext->isAuthenticated() ? $authContext->getUserId() : ($_SESSION['user_id'] ?? null);

    // Verificar autenticação
    if ($currentUserId === null || (int)$currentUserId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }

    initializeTenantMiddleware($GLOBALS['pdo']);
    $tenantId = getCurrentTenantId();

    $userRepository = new UserRepository($GLOBALS['pdo']);
    $role = strtolower(trim($userRepository->getRole((int)$currentUserId, $tenantId) ?? ''));

    $filters = [];

    if (in_array($role, ['admin', 'administrator', 'gerente'], true)) {
        if (!empty($_GET['user_id'])) {
            $filters['user_id'] = (int)$_GET['user_id'];
        }
    } else {
        $filters['user_id'] = (int)$currentUserId;
    }

    if (!empty($_GET['status'])) {
        $validStatus = ['pendente', 'aprovada', 'paga', 'cancelada'];
        if (in_array($_GET['status'], $validStatus, true)) {
            $filters['status'] = $_GET['status'];
        }
    }

    if (!empty($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }

    if (!empty($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }

    if (empty($_GET['start_date']) && empty($_GET['end_date'])) {
        $filters['start_date'] = date('Y-m-d', strtotime('-90 days'));
        $filters['end_date'] = date('Y-m-d');
    }

    $filters['page'] = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $filters['limit'] = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;

    $service = new \App\Services\FinanceService($GLOBALS['pdo']);
    $response = $service->listCommissions($filters, (int)$currentUserId, in_array($role, ['admin', 'administrator', 'gerente'], true));

    http_response_code(200);
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
}
