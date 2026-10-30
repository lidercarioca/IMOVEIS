<?php

/**
 * Diagnóstico de Follow-ups
 * Ferramenta para entender o status dos follow-ups no sistema
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/session_manager.php';
require_once __DIR__ . '/../../../../app/services/LeadFollowupService.php';

session_start();
$tenantId = getRequestTenantId();

$diagnose = [];

try {
    $followupService = new App\Services\LeadFollowupService($pdo, $tenantId);
    $diagnose = $followupService->getDiagnoseReport();
} catch (Exception $e) {
    $diagnose['error'] = $e->getMessage();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($diagnose, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
