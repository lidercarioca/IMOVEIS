<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/monitoring.php';
require_once __DIR__ . '/../../../app/utils/AsyncJobQueue.php';
require_once __DIR__ . '/../../../app/integrations/WebhookManager.php';
require_once __DIR__ . '/../../../src/Observability/RequestTracker.php';

use App\Integrations\WebhookManager;
use App\Observability\RequestTracker;

header('Content-Type: application/json; charset=utf-8');

$monitoringConfig = require __DIR__ . '/../../../config/monitoring.php';
$secret = $monitoringConfig['health_check_secret'] ?? '';
$provided = $_GET['secret'] ?? $_SERVER['HTTP_X_HEALTH_CHECK_SECRET'] ?? '';

if (($monitoringConfig['require_secret'] ?? true) && empty($secret)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Monitoring secret not configured']);
    exit;
}

if (($monitoringConfig['require_secret'] ?? true) && !hash_equals((string)$secret, (string)$provided)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

RequestTracker::start($_SERVER['HTTP_X_REQUEST_ID'] ?? null);

try {
    $queue = new AsyncJobQueue($pdo);
    $metrics = $queue->getMetrics($monitoringConfig['job_metrics_limit'] ?? 10);
    $webhookManager = new WebhookManager($pdo);
    $pendingWebhooks = $webhookManager->getPendingQueueSize();

    echo json_encode([
        'success' => true,
        'request_id' => RequestTracker::getId(),
        'queue_metrics' => $metrics,
        'webhook_pending_jobs' => $pendingWebhooks,
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Queue status error',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
