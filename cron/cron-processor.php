<?php
/**
 * Cron Job Processor
 * Executa jobs agendados e manutenção do sistema
 *
 * Uso:
 * - Via cron: *\/5 * * * * php \/path\/to\/cron-processor.php
 * - Manual: php cron-processor.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jobs.php';
require_once __DIR__ . '/../config/monitoring.php';
require_once __DIR__ . '/../src/Jobs/JobScheduler.php';
require_once __DIR__ . '/../app/utils/AsyncJobQueue.php';
require_once __DIR__ . '/../app/integrations/WebhookManager.php';
require_once __DIR__ . '/../src/Monitoring/AlertManager.php';

use App\Jobs\JobScheduler;
use App\Monitoring\AlertManager;

set_time_limit(300); // 5 minutos máximo

$jobsConfig = require __DIR__ . '/../config/jobs.php';
$monitoringConfig = require __DIR__ . '/../config/monitoring.php';

try {
    // Processar jobs agendados
    $scheduler = new JobScheduler($pdo, $jobsConfig);
    $scheduledResults = $scheduler->processDueJobs();

    // Processar fila de jobs assíncronos
    $queue = new \AsyncJobQueue($pdo);
    $asyncResults = $queue->processPending($jobsConfig['process_batch_size'] ?? 20);

    // Processar fila de webhooks
    $webhookManager = new \App\Integrations\WebhookManager($pdo);
    $webhookResults = $webhookManager->processQueue();

    // Verificar alertas do sistema
    $alertManager = new AlertManager($pdo, $monitoringConfig);
    $alertsTriggered = $alertManager->checkAndAlert();

    // Log dos resultados
    $summary = [
        'timestamp' => date('c'),
        'scheduled_jobs' => $scheduledResults,
        'async_jobs' => $asyncResults,
        'webhooks' => $webhookResults,
        'alerts_triggered' => $alertsTriggered,
    ];

    error_log("[CronProcessor] Execução concluída: " . json_encode($summary));

    // Saída para monitoramento
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Cron jobs processed successfully',
        'results' => $summary,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("[CronProcessor] Erro: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
