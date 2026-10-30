<?php
/**
 * API: Obter Histórico de Interações de um Lead
 * GET /api/v1/leads/getInteractions.php?lead_id=X
 * 
 * Retorna todas as interações (visualizações, chamadas, emails, status changes, etc)
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
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;
    $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
    
    if (!$leadId) {
        throw new Exception("lead_id é obrigatório");
    }
    
    // Verificar acesso ao lead
    canAccessResource('leads', $leadId);
    
    // Usar o serviço
    $service = new \App\Services\LeadInteractionService($GLOBALS['pdo'], $tenantId);
    
    $interactions = $service->getLeadInteractions($leadId, $limit, $offset);
    $totalCount = $service->countInteractions($leadId);
    
    echo json_encode([
        'success' => true,
        'data' => $interactions,
        'stats' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => ceil($totalCount / $limit)
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
