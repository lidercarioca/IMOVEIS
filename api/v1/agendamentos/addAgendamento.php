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
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $propertyId = $input['property_id'] ?? null;
        $dataAgendamento = $input['data_agendamento'] ?? null;

        if (!$propertyId || !$dataAgendamento) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Propriedade e data são obrigatórios'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = \App\Auth\AuthContext::getInstance()->getUserId();
        $service = new AgendamentoService($pdo);
        $agendamento = $service->create($input, $tenantId, $userId);

        if ($agendamento) {
            echo json_encode(['success' => true, 'data' => $agendamento], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Erro ao criar agendamento'], JSON_UNESCAPED_UNICODE);
        }
    } elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $agendamentoId = $input['id'] ?? null;
        $dataAgendamento = $input['data_agendamento'] ?? null;

        $service = new AgendamentoService($pdo);
        $result = $service->updateDate($agendamentoId, $dataAgendamento, $tenantId);

        echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
