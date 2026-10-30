<?php
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Service/AgendamentoService.php';

use App\Service\AgendamentoService;

checkAuth();
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

try {
    $filtro = InputValidator::getEnum($_GET['filtro'] ?? '', ['proximos', 'passados', 'todos'], 'Filtro', 'proximos');

    $mesInput = $_GET['mes'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $mesInput)) {
        throw new Exception('Parâmetro "mes" deve estar no formato YYYY-MM');
    }

    $mesComDia = $mesInput . '-01';
    $mesValidado = InputValidator::getDate($mesComDia, 'Mês', false);
    $mes = substr($mesValidado, 0, 7);

    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();
    $service = new AgendamentoService($pdo);
    $isAdmin = $service->isAdmin($userId, $tenantId);

    if (isset($_GET['id'])) {
        $id = InputValidator::getInt($_GET['id'], 'ID do agendamento', 1);
        $agendamento = $service->get($id, $tenantId);

        if ($agendamento === null) {
            throw new Exception('Agendamento não encontrado');
        }

        echo json_encode([
            'success' => true,
            'data' => [$agendamento],
            'total' => 1
        ]);
        exit;
    }

    $agendamentos = $service->list([
        'tenantId' => $tenantId,
        'userId' => $userId,
        'isAdmin' => $isAdmin,
        'filtro' => $filtro,
        'mes' => $mes,
        'status' => $_GET['status'] ?? null,
    ]);

    echo json_encode([
        'success' => true,
        'data' => $agendamentos,
        'total' => count($agendamentos)
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
