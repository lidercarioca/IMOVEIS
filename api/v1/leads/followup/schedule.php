<?php

/**
 * API para verificar e agendar follow-ups
 * Verifica condições de trigger e agenda follow-ups automaticamente
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/session_manager.php';
require_once __DIR__ . '/../../../../app/services/LeadFollowupService.php';

header('Content-Type: application/json');

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar autenticação
require_once __DIR__ . '/../../../../api/check_auth.php';

// Obter tenant_id do usuário autenticado via contexto autorizado do projeto
$tenantId = getRequestTenantId();
if ($tenantId <= 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Tenant não identificado',
        'code' => 'tenant_not_resolved'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Obter parâmetros
$leadId = $_POST['lead_id'] ?? null;

try {
    $followupService = new App\Services\LeadFollowupService($pdo, $tenantId);

    // Verificar e agendar follow-ups
    $result = $followupService->checkAndScheduleFollowups($leadId);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Follow-ups verificados e agendados',
            'data' => $result
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
