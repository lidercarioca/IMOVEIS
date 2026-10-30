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

    if ($method === 'PUT' || $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $agendamentoId = $input['id'] ?? null;

        if (!$agendamentoId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID obrigatório'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $hasUpdateField = false;
        if (isset($input['data_agendamento']) && !empty($input['data_agendamento'])) {
            $hasUpdateField = true;
        }
        if (isset($input['status']) && !empty($input['status'])) {
            $hasUpdateField = true;
        }
        if (array_key_exists('descricao', $input)) {
            $hasUpdateField = true;
        }

        if (!$hasUpdateField) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $service = new AgendamentoService($pdo);
        $result = $service->update((int)$agendamentoId, $tenantId, $input);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Agendamento atualizado com sucesso'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao atualizar agendamento'
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
