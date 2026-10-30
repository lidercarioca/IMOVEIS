<?php

/**
 * GET /api/v1/leads/scoring/stats.php
 *
 * Obter estatísticas de scoring de leads
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

    $scoringService = new \App\Services\LeadScoringService($pdo, $tenantId);
    $stats = $scoringService->getScoringStats();
    $distribution = $scoringService->getScoreDistribution();
    $topLeads = $scoringService->getTopLeadsByScore(10);

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'distribution' => $distribution,
        'top_leads' => $topLeads
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
