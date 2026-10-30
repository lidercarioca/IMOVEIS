<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/monitoring.php';
require_once __DIR__ . '/../../../src/Monitoring/AlertManager.php';

use App\Monitoring\AlertManager;

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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $alertManager = new AlertManager($pdo, $monitoringConfig);

    switch ($method) {
        case 'GET':
            // Obter alertas ativos
            $alerts = $alertManager->getActiveAlerts();
            echo json_encode([
                'success' => true,
                'alerts' => $alerts,
                'count' => count($alerts),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'POST':
            // Verificar e disparar alertas
            $alertsTriggered = $alertManager->checkAndAlert();
            echo json_encode([
                'success' => true,
                'alerts_triggered' => $alertsTriggered,
                'count' => count($alertsTriggered),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'PUT':
            // Resolver alerta
            $input = json_decode(file_get_contents('php://input'), true);
            $alertId = $input['alert_id'] ?? null;
            $userId = $input['user_id'] ?? null;

            if (!$alertId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'alert_id is required']);
                exit;
            }

            $resolved = $alertManager->resolveAlert((int)$alertId, $userId ? (int)$userId : null);
            echo json_encode([
                'success' => $resolved,
                'message' => $resolved ? 'Alert resolved' : 'Failed to resolve alert',
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Alerts API error',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
