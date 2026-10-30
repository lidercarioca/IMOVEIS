<?php

header("Content-Type: application/json");
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Infrastructure\Webhook\WebhookRepository;
use App\Service\WebhookService;

checkAuth();
initializeTenantMiddleware($pdo);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payload inválido']);
        exit;
    }

    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $eventType = $input['event_type'] ?? null;
    $url = $input['url'] ?? null;
    $secret = $input['secret'] ?? null;
    $active = $input['active'] ?? 1;

    if (!$eventType || !$url) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'event_type e url são obrigatórios']);
        exit;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'URL inválida']);
        exit;
    }

    $validEvents = ['lead.created', 'lead.updated', 'lead.converted', 'property.created', 'property.sold', 'commission.created', 'commission.paid', 'alert.triggered'];
    if (!in_array($eventType, $validEvents)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipo de evento inválido']);
        exit;
    }

    $tenantId = getCurrentTenantId();

    $repo = new WebhookRepository($pdo);
    $service = new WebhookService($repo, $pdo);

    $subscriptionId = $service->createSubscription($userId, $tenantId, $eventType, $url, $secret, (int)$active);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Inscrição criada com sucesso',
        'subscription_id' => $subscriptionId,
        'data' => [
            'id' => $subscriptionId,
            'event_type' => $eventType,
            'url' => $url,
            'active' => (bool) $active,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
