<?php
header("Content-Type: application/json");
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/middleware/api_auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Infrastructure/Lead/LeadRepository.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Application/Lead/LeadService.php';

use App\Infrastructure\User\UserRepository;

checkAuth();
checkApiAuth();
// Garantir acesso ao PDO global (pode ser indefinido em alguns contextos)
global $pdo;

try {
    // Initialize tenant isolation (dentro do try para capturar falhas de inicialização)
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    // Verifica se é um POST
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Método não permitido.");
    }

    // Verifica se é admin (apenas admin pode atribuir leads) - silenciosamente retorna sucesso se não for
    if (!isAdmin()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Operação concluída']);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Dados JSON inválidos");
    }

    // Validação básica
    if (!isset($data['lead_id']) || !isset($data['assigned_user_id'])) {
        throw new Exception("Parâmetros obrigatórios faltando");
    }

    $leadId = intval($data['lead_id']);
    $assignedUserId = intval($data['assigned_user_id']) ?: null; // null se for 0 ou vazio

    $repository = new \App\Infrastructure\Lead\LeadRepository($pdo);
    $leadService = new \App\Application\Lead\LeadService($repository);
    $lead = $leadService->getLead($tenantId, $leadId);
    if (!$lead) {
        throw new Exception("Lead não encontrado.");
    }

    // Se assignedUserId não for null, verifica se o usuário existe E pertence ao tenant
    if ($assignedUserId) {
        $userRepository = new UserRepository($pdo);
        if (!$userRepository->existsInTenant($assignedUserId, $tenantId)) {
            throw new Exception("Usuário não encontrado.");
        }
    }

    $leadService->assignLeadWithNotification($tenantId, $leadId, $assignedUserId);

    echo json_encode([
        "success" => true,
        "message" => $assignedUserId ? "Lead atribuído com sucesso" : "Atribuição removida com sucesso"
    ]);
} catch (\Throwable $e) {
    $code = $e->getMessage() === "Método não permitido." ? 405 : 500;
    http_response_code($code);
    error_log("Erro ao atribuir lead: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
