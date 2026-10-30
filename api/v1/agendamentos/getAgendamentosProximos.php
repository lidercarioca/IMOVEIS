<?php
require dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require dirname(dirname(dirname(__DIR__))) . '/auth.php';
require dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require dirname(dirname(dirname(__DIR__))) . '/src/Service/AgendamentoService.php';

header('Content-Type: application/json; charset=utf-8');

use App\Service\AgendamentoService;

try {
    checkAuth();
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();

    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new AgendamentoService($pdo);
    $isAdmin = $service->isAdmin($userId, $tenantId);
    $resultado = $service->getUpcomingSummary($tenantId, $userId, $isAdmin);

    echo json_encode([
        'success' => true,
        'data' => $resultado['data'],
        'hoje' => $resultado['hoje'],
        'amanha' => $resultado['amanha'],
        'total' => $resultado['total']
    ], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
