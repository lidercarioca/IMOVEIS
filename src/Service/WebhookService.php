<?php

namespace App\Service;

use App\Infrastructure\Webhook\WebhookRepository;
use PDO;

class WebhookService
{
    private WebhookRepository $repo;
    private PDO $pdo;

    public function __construct(WebhookRepository $repo, PDO $pdo)
    {
        $this->repo = $repo;
        $this->pdo = $pdo;
    }

    public function listSubscriptions(int $userId, int $tenantId): array
    {
        return $this->repo->listSubscriptions($userId, $tenantId);
    }

    public function createSubscription(int $userId, int $tenantId, string $eventType, string $url, ?string $secret, int $active): int
    {
        // Basic validation can be applied here if needed
        return $this->repo->createSubscription($userId, $tenantId, $eventType, $url, $secret, $active);
    }

    public function deleteSubscription(int $id, int $userId, int $tenantId): int
    {
        return $this->repo->deleteSubscription($id, $userId, $tenantId);
    }

    public function processQueue(): array
    {
        $manager = new \App\Integrations\WebhookManager($this->pdo);
        $results = $manager->processQueue();
        $cleanedLogs = $this->repo->cleanupOldLogs();

        return [
            'results' => $results,
            'cleaned_logs' => $cleanedLogs,
        ];
    }

    public function cleanupOldLogs(int $days = 30): int
    {
        return $this->repo->cleanupOldLogs($days);
    }

    public function getActiveUrlsForEvent(string $eventType): array
    {
        return $this->repo->findActiveUrlsByEvent($eventType);
    }

    public function testSubscription(int $subscriptionId, int $userId, int $tenantId, string $eventType): array
    {
        $sub = $this->repo->findById($subscriptionId, $userId, $tenantId);
        if (!$sub) {
            return ['success' => false, 'message' => 'Subscription não encontrada'];
        }

        // Build payload similar to WebhookManager.dispatchEvent enriched payload
        $eventId = 'test_' . bin2hex(random_bytes(8));
        $timestamp = date('Y-m-d H:i:s');
        $payload = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'timestamp' => $timestamp,
            'data' => [],
            'metadata' => [
                'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        ];

        // Enfileirar job no mesmo formato usado por WebhookManager
        $queueDir = dirname(__DIR__, 3) . '/queue/webhooks';
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0755, true);
        }

        $jobFile = $queueDir . '/' . uniqid('webhook_') . '.json';
        $jobData = [
            'subscription_id' => (int)$sub['id'],
            'url' => $sub['url'],
            'payload' => $payload,
            'secret' => $sub['secret'] ?? null,
            'retry_count' => 0,
            'delay' => 0,
            'created_at' => time(),
            'execute_at' => time()
        ];

        file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT));
        @chmod($jobFile, 0644);

        // Create log entry
        $this->repo->createLog((int)$sub['id'], $userId, $tenantId, $eventId, 'pending', 0, null);

        return ['success' => true, 'message' => 'Webhook de teste enfileirado', 'job' => basename($jobFile)];
    }
}
