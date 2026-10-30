<?php
header("Content-Type: application/json");
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Infrastructure/Lead/LeadRepository.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Application/Lead/LeadService.php';

checkAuth();
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

// Helper de logging de ações do usuário
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/logger_functions.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    // Verifica se é admin
    $isAdmin = isAdmin();
    $userId = $_SESSION['user_id'] ?? null;

    // Verifica se é uma requisição POST (múltiplos leads) ou GET (lead único)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Recebe os dados JSON do corpo da requisição
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Dados JSON inválidos");
        }

        if (!isset($data['leadIds']) || !is_array($data['leadIds'])) {
            throw new Exception("IDs não fornecidos corretamente");
        }

        $repository = new \App\Infrastructure\Lead\LeadRepository($pdo);
        $leadService = new \App\Application\Lead\LeadService($repository);
        $leadService->deleteLeadsWithAccess($tenantId, $data['leadIds'], $userId, $isAdmin);
        // Log exclusão em lote de leads
        log_user_action('lead_delete', ['lead_ids' => $data['leadIds']]);

        echo json_encode([
            "success" => true,
            "message" => count($data['leadIds']) > 1 ?
                "Leads excluídos com sucesso" :
                "Lead excluído com sucesso"
        ]);
    } else {
        // Método GET para exclusão individual (mantém compatibilidade)
        if (!isset($_GET['id'])) {
            throw new Exception("ID não fornecido");
        }

        $id = intval($_GET['id']);

        $repository = new \App\Infrastructure\Lead\LeadRepository($pdo);
        $leadService = new \App\Application\Lead\LeadService($repository);
        $leadService->deleteLeadWithAccess($tenantId, $id, $userId, $isAdmin);

        // Log exclusão de lead único
        log_user_action('lead_delete', ['lead_id' => $id]);

        echo json_encode([
            "success" => true,
            "message" => "Lead excluído com sucesso"
        ]);
    }
} catch (Exception $e) {
    error_log("Erro ao excluir lead(s): " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
