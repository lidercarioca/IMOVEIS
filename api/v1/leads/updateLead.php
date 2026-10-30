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

try {
    // Verifica se é um POST
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Método não permitido.");
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Dados JSON inválidos");
    }

    // Verificação básica
    if (!isset($data['id'])) {
        throw new Exception("ID do lead não informado.");
    }

    // Validar acesso ao lead (tenant isolation)
    canAccessResource('leads', $data['id']);

    // Verifica se é admin
    $isAdmin = isAdmin();
    $userId = $_SESSION['user_id'] ?? null;

    // Permite atualizar status e/ou notes
    $updateData = [];

    // Se for apenas registrar uma interação (visualização), apenas atualiza updated_at
    if (isset($data['interaction'])) {
        $repository = new \App\Infrastructure\Lead\LeadRepository($pdo);
        $leadService = new \App\Application\Lead\LeadService($repository);
        $leadService->touchLead($tenantId, $data['id'], $userId, $isAdmin);

        echo json_encode([
            "success" => true,
            "message" => "Interação registrada com sucesso"
        ]);
        exit;
    }

    if (isset($data['status'])) {
        $updateData['status'] = $data['status'];
    }
    if (array_key_exists('notes', $data)) {
        $updateData['notes'] = $data['notes'];
    }
    if (array_key_exists('proposal_sent', $data)) {
        // aceitar 0/1 ou boolean
        $proposalVal = $data['proposal_sent'] ? 1 : 0;
        $updateData['proposal_sent'] = $proposalVal;
        $updateData['proposal_sent_at'] = $proposalVal ? date('Y-m-d H:i:s') : null;
    }

    // Se nenhum campo foi selecionado, retorna erro
    if (count($updateData) === 0) {
        throw new Exception("Nenhum campo para atualizar");
    }

    $repository = new \App\Infrastructure\Lead\LeadRepository($pdo);
    $leadService = new \App\Application\Lead\LeadService($repository);
    $leadService->updateLeadWithAudit($tenantId, $data['id'], $updateData, $userId, $isAdmin);

    echo json_encode([
        "success" => true,
        "message" => "Lead atualizado com sucesso"
    ]);
} catch (Exception $e) {
    $code = $e->getMessage() === "Método não permitido." ? 405 : 500;
    http_response_code($code);
    error_log("Erro ao atualizar lead: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
