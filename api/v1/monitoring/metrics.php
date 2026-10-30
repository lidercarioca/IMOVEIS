<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/monitoring.php';
require_once __DIR__ . '/../../../src/Monitoring/MetricsCollector.php';

use App\Monitoring\MetricsCollector;

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

try {
    $collector = new MetricsCollector($pdo);
    $metrics = $collector->collectAllMetrics();

    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Metrics collection error',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
