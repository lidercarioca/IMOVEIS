<?php
/**
 * API: Registrar Interação com um Lead
 * POST /api/v1/leads/recordInteraction.php
 * 
 * Body (JSON):
 * {
 *   "lead_id": 1,
 *   "type": "called|emailed|notes_added|proposal_sent|etc",
 *   "title": "Título da interação",
 *   "description": "Descrição detalhada",
 *   "old_value": "valor_anterior" (para status changes),
 *   "new_value": "valor_novo" (para status changes),
 *   "metadata": { "key": "value" } (opcional)
 * }
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
    $type = $data['type'] ?? null;
    $title = $data['title'] ?? null;
    $description = $data['description'] ?? null;
    $oldValue = $data['old_value'] ?? null;
    $newValue = $data['new_value'] ?? null;
    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null;
    
    if (!$leadId || !$type || !$title) {
        throw new Exception("lead_id, type e title são obrigatórios");
    }
    
    // Verificar acesso ao lead
    canAccessResource('leads', $leadId);
    
    // Usar o serviço
    $service = new \App\Services\LeadInteractionService($GLOBALS['pdo'], $tenantId);
    
    $interactionId = $service->recordInteraction(
        $leadId,
        $type,
        $title,
        $description,
        $oldValue,
        $newValue,
        $metadata,
        $userId
    );
    
    if (!$interactionId) {
        throw new Exception("Erro ao registrar interação");
    }
    
    echo json_encode([
        'success' => true,
        'interaction_id' => $interactionId,
        'message' => 'Interação registrada com sucesso'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
