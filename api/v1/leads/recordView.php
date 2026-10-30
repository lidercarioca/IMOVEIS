<?php
/**
 * API: Registrar Visualização de Lead
 * POST /api/v1/leads/recordView.php
 * 
 * Body (JSON):
 * {
 *   "lead_id": 1
 * }
 * 
 * Registra automaticamente que um lead foi visualizado pelo usuário logado
 */

header("Content-Type: application/json; charset=utf-8");

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/services/LeadInteractionService.php';

checkAuth();
initializeTenantMiddleware($GLOBALS['pdo']);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método não permitido");
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        throw new Exception("Dados JSON inválidos");
    }
    
    $tenantId = getCurrentTenantId();
    $userId = getCurrentUserId();
    $leadId = isset($data['lead_id']) ? intval($data['lead_id']) : null;
    
    if (!$leadId) {
        throw new Exception("lead_id é obrigatório");
    }
    
    // Verificar acesso ao lead
    canAccessResource('leads', $leadId);
    
    // Usar o serviço
    $service = new \App\Services\LeadInteractionService($GLOBALS['pdo'], $tenantId);
    $interactionId = $service->recordView($leadId, $userId);
    
    if (!$interactionId) {
        throw new Exception("Erro ao registrar visualização");
    }
    
    echo json_encode([
        'success' => true,
        'interaction_id' => $interactionId,
        'message' => 'Visualização registrada'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
