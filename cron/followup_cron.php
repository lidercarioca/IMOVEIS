<?php

/**
 * Script CRON para executar follow-ups automáticos
 * Execute via:
 * php cron/followup_cron.php
 */

// Carregar AUTOLOAD PRIMEIRO
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/notification.php';
require_once __DIR__ . '/../app/services/LeadFollowupService.php';

// ========================
// CONFIG
// ========================
$LOG_PATH = __DIR__ . '/../logs';
$LOG_FILE = $LOG_PATH . '/followup_cron.log';
$LOCK_FILE = __DIR__ . '/followup.lock';

// Criar pasta de log se não existir
if (!is_dir($LOG_PATH)) {
    mkdir($LOG_PATH, 0777, true);
}

// Configurar logging
ini_set('log_errors', 1);
ini_set('error_log', $LOG_FILE);

// ========================
// FUNÇÃO DE LOG
// ========================
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] $message");
}

// ========================
// EVITAR EXECUÇÃO DUPLA
// ========================
if (file_exists($LOCK_FILE)) {
    logMessage("CRON já está em execução. Abortando.");
    exit;
}

file_put_contents($LOCK_FILE, time());

try {

    logMessage("Iniciando execução do CRON de follow-ups");

    // Validar conexão
    if (!isset($pdo)) {
        throw new Exception("PDO não está definido. Verifique database.php");
    }

    // ========================
    // CONFIG DE EXECUÇÃO
    // ========================
    $forceExecute = true; // 🔥 deixe true para testes

    // Buscar tenants
    $stmt = $pdo->query("SELECT DISTINCT tenant_id FROM leads WHERE tenant_id IS NOT NULL");
    $tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tenants)) {
        $tenants = [1];
    }

    $totalExecuted = 0;
    $totalFailed = 0;
    $totalScheduled = 0;

    foreach ($tenants as $tenantId) {

        logMessage("Processando tenant: $tenantId");

        $followupService = new App\Services\LeadFollowupService($pdo, $tenantId);

        // ========================
        // EXECUTAR FOLLOWUPS
        // ========================
        $result = $followupService->executePendingFollowups($forceExecute);

        if ($result['success']) {
            $executed = $result['executed'] ?? 0;
            $failed = $result['failed'] ?? 0;

            $totalExecuted += $executed;
            $totalFailed += $failed;

            logMessage("Tenant $tenantId: $executed executados, $failed falharam");
        } else {
            logMessage("Erro no tenant $tenantId: " . ($result['error'] ?? 'Erro desconhecido'));
        }

        // ========================
        // AGENDAR NOVOS
        // ========================
        $scheduleResult = $followupService->checkAndScheduleFollowups();

        if ($scheduleResult['success']) {
            $scheduled = $scheduleResult['followups_scheduled'] ?? 0;
            $totalScheduled += $scheduled;

            logMessage("Tenant $tenantId: $scheduled follow-ups agendados");
        } else {
            logMessage("Erro ao agendar follow-ups no tenant $tenantId: " . ($scheduleResult['error'] ?? 'Erro desconhecido'));
        }
    }

    logMessage("CRON concluído. Executados: $totalExecuted | Falhas: $totalFailed | Agendados: $totalScheduled");

} catch (Exception $e) {

    logMessage("Erro crítico no CRON: " . $e->getMessage());

} finally {

    // 🔥 Remove lock sempre
    if (file_exists($LOCK_FILE)) {
        unlink($LOCK_FILE);
    }
}

echo "CRON executado com sucesso\n";