<?php

header("Content-Type: application/json");
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

use App\Infrastructure\Webhook\WebhookRepository;
use App\Service\WebhookService;

// Verificar secret para cron (pode vir de header ou query param)
$cronSecret = $_SERVER['HTTP_X_CRON_SECRET'] ?? $_GET['secret'] ?? null;
$expectedSecret = getenv('CRON_SECRET') ?? 'dev-cron-secret-' . substr(md5('webhook'), 0, 16);

if ($cronSecret !== $expectedSecret) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $repo = new \App\Infrastructure\Webhook\WebhookRepository($pdo);
    $service = new \App\Service\WebhookService($repo, $pdo);

    $queueSummary = $service->processQueue();

    echo json_encode([
        'success' => true,
        'message' => 'Fila de webhooks processada',
        'results' => $queueSummary['results'],
        'cleaned_logs' => $queueSummary['cleaned_logs']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
