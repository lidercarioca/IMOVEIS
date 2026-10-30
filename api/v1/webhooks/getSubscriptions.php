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
    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $tenantId = getCurrentTenantId();

    $repo = new WebhookRepository($pdo);
    $service = new WebhookService($repo, $pdo);

    $subscriptions = $service->listSubscriptions($userId, $tenantId);

    echo json_encode([
        'success' => true,
        'data' => $subscriptions,
        'count' => count($subscriptions)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
