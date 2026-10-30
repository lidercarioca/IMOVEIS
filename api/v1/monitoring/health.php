<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/monitoring.php';
require_once __DIR__ . '/../../../app/utils/AsyncJobQueue.php';
require_once __DIR__ . '/../../../app/integrations/WebhookManager.php';
require_once __DIR__ . '/../../../src/Monitoring/SystemHealth.php';
require_once __DIR__ . '/../../../src/Observability/RequestTracker.php';

use App\Monitoring\SystemHealth;
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
    $health = new SystemHealth($pdo, $monitoringConfig);
    $summary = $health->getSummary();
    $statusCode = $summary['overall_status'] === 'pass' ? 200 : 503;
    http_response_code($statusCode);
    echo json_encode([
        'success' => true,
        'status' => $summary['overall_status'],
        'request_id' => $summary['request_id'],
        'checks' => $summary['checks'],
        'timestamp' => $summary['timestamp'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal health check error',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
