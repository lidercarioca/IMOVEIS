<?php

/**
 * API para obter estatísticas de follow-up
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/session_manager.php';
require_once __DIR__ . '/../../../../app/services/LeadFollowupService.php';

header('Content-Type: application/json');

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar autenticação
require_once __DIR__ . '/../../../../api/check_auth.php';

$tenantId = getRequestTenantId();

try {
    $followupService = new App\Services\LeadFollowupService($pdo, $tenantId);

    // Obter estatísticas
    $stats = $followupService->getFollowupStats();

    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
