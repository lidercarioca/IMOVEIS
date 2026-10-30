<?php

/**
 * GET /api/v1/leads/scoring/calculate.php
 * POST /api/v1/leads/scoring/calculate.php
 *
 * Calcular scores de leads
 *
 * Query params:
 * - lead_id: calcular score para lead específico
 * - recalculate_all: true para recalcular todos os leads
 *
 * POST body:
 * - lead_ids: array de IDs para recalcular
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/auth.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/services/LeadScoringService.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();

try {
    global $pdo;

    // Initialize tenant isolation
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $userId = $_SESSION['user_id'] ?? null;

    $scoringService = new \App\Services\LeadScoringService($pdo, $tenantId);

    // Se especificou lead_id, calcular apenas esse
    if (isset($_GET['lead_id']) && is_numeric($_GET['lead_id'])) {
        $result = $scoringService->calculateLeadScore((int)$_GET['lead_id']);
    }
    // Se pediu recalcular todos
    elseif (isset($_GET['recalculate_all']) && $_GET['recalculate_all'] === 'true') {
        $result = $scoringService->recalculateAllScores();
    }
    // Se enviou lead_ids via POST
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lead_ids'])) {
        $leadIds = json_decode($_POST['lead_ids'], true);
        if (!is_array($leadIds)) {
            throw new Exception('lead_ids deve ser um array');
        }

        $results = [];
        foreach ($leadIds as $leadId) {
            $results[] = $scoringService->calculateLeadScore((int)$leadId);
        }

        $result = [
            'success' => true,
            'total_processed' => count($leadIds),
            'results' => $results
        ];
    }
    else {
        // Retornar estatísticas
        $stats = $scoringService->getScoringStats();
        $result = [
            'success' => true,
            'stats' => $stats
        ];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}