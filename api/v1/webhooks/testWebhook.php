<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Infrastructure\Webhook\WebhookRepository;
use App\Service\WebhookService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $subscriptionId = isset($input['subscription_id']) ? (int)$input['subscription_id'] : 0;
    $eventType = $input['event_type'] ?? 'test.webhook';

    if ($subscriptionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'subscription_id inválido']);
        exit;
    }

    // Require login for testing
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    initializeTenantMiddleware($pdo);
    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $tenantId = (int)getCurrentTenantId();
    $repo = new WebhookRepository($pdo);
    $service = new WebhookService($repo, $pdo);

    $result = $service->testSubscription($subscriptionId, $userId, $tenantId, $eventType);
    if (!empty($result['success'])) {
        echo json_encode(['success' => true, 'message' => 'Webhook de teste enfileirado!']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Erro ao enfileirar teste']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
