<?php

/**
 * API para gerenciar configurações de metas COM ISOLAMENTO DE TENANT
 */
header('Content-Type: application/json');
require_once '../../../config/session_manager.php';
require_once '../../../auth.php';
require_once '../../../config/database.php';
require_once '../../../app/helpers/tenant_isolation_helpers.php';
require_once '../../../vendor/autoload.php';

use App\Service\GoalConfigService;

checkAuth();
initializeTenantMiddleware($pdo);

$isAdmin = isAdmin();
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Apenas administradores podem editar metas']);
    exit;
}

try {
    $tenantId = getCurrentTenantId();
    $method = $_SERVER['REQUEST_METHOD'];
    $service = new GoalConfigService($pdo);

    if ($method === 'GET') {
        echo json_encode([
            'success' => true,
            'data' => $service->listGoals($tenantId)
        ]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['goal_id'], $data['target_value'])) {
            throw new Exception('Dados inválidos');
        }

        $result = $service->saveGoal(
            $tenantId,
            (string) $data['goal_id'],
            (float) $data['target_value'],
            (int) ($data['period_days'] ?? 30),
            isset($data['is_active']) ? (int) $data['is_active'] : 1
        );

        echo json_encode($result);
    } else {
        throw new Exception('Método não permitido');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
