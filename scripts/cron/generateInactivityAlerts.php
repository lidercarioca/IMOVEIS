<?php

/**
 * Cron Job: scripts/cron/generateInactivityAlerts.php
 * 
 * Executar diariamente para gerar alertas de leads inativos
 */

date_default_timezone_set('America/Sao_Paulo');

require_once dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/database.php';
require_once dirname(dirname(dirname(__FILE__))) . '/app/helpers/tenant_isolation_helpers.php';

try {
    global $pdo;

    $logMessage = "[" . date('Y-m-d H:i:s') . "] Iniciando gera��o de alertas de inatividade";
    error_log($logMessage);
    echo $logMessage . "\n";

    $leadService = new \App\Services\LeadInactivityAlertService($pdo);
    $res = $leadService->generateInactivityAlertsForAllTenants(7, 500);

    $totalAlerts = $res['total_alerts_generated'] ?? 0;
    $totalLeads = $res['total_leads_checked'] ?? 0;

    foreach ($res['tenants'] as $tenantSummary) {
        $tenantId = $tenantSummary['tenant_id'];



        $tenantAlerts = $tenantSummary['alerts_generated'];
        $leadCount = $tenantSummary['leads_checked'];

        $logMessage = "  Tenant $tenantId: $tenantAlerts alertas criados ($leadCount leads verificados)";
        error_log($logMessage);
        echo $logMessage . "\n";
    }

    // Log do final
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Conclus�o: $totalAlerts alertas criados para $totalLeads leads verificados";
    error_log($logMessage);
    echo $logMessage . "\n";

    // Retornar status para cron log
    echo "OK: $totalAlerts alertas gerados\n";
    exit(0);
} catch (Exception $e) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage();
    error_log($logMessage);

    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
