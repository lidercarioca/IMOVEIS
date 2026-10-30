<?php
/**
 * API: Obter Estatísticas de Atividade de um Lead
 * GET /api/v1/leads/getActivityStats.php?lead_id=X
 * 
 * Retorna estatísticas resumidas de atividades (visualizações, chamadas, emails, etc)
 * COM ISOLAMENTO DE TENANT
 */

header("Content-Type: application/json; charset=utf-8");

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/services/LeadInteractionService.php';

checkAuth();
initializeTenantMiddleware($GLOBALS['pdo']);

try {
    $tenantId = getCurrentTenantId();
    $leadId = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : null;
    
    if (!$leadId) {
        throw new Exception("lead_id é obrigatório");
    }
    
    // Verificar acesso ao lead
    canAccessResource('leads', $leadId);
    
    // Usar o serviço
    $service = new \App\Services\LeadInteractionService($GLOBALS['pdo'], $tenantId);
    
    $stats = $service->getActivityStats($leadId);
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
