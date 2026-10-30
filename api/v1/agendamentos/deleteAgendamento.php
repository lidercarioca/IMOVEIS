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
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $agendamentoId = $_POST['id'] ?? null;

        if (!$agendamentoId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID do agendamento obrigatório'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $isAdmin = isAdmin();

        $service = new AgendamentoService($pdo);
        $result = $service->delete((int)$agendamentoId, $tenantId, $userId, $isAdmin);

        http_response_code($result['status']);
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('deleteAgendamento error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
