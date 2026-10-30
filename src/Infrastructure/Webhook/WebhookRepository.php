<?php

namespace App\Infrastructure\Webhook;

use PDO;

class WebhookRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listSubscriptions(int $userId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, event_type, url, active, last_retry_at, retry_count, created_at, updated_at
            FROM webhook_subscriptions
            WHERE user_id = :user_id AND tenant_id = :tenant_id
            ORDER BY created_at DESC
        ");
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $userId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webhook_subscriptions WHERE id = :id AND user_id = :user_id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute([':id' => $id, ':user_id' => $userId, ':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createSubscription(int $userId, int $tenantId, string $eventType, string $url, ?string $secret, int $active): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO webhook_subscriptions (user_id, tenant_id, event_type, url, secret, active, created_at) VALUES (:user_id, :tenant_id, :event_type, :url, :secret, :active, NOW())");
        $stmt->execute([
            ':user_id' => $userId,
            ':tenant_id' => $tenantId,
            ':event_type' => $eventType,
            ':url' => $url,
            ':secret' => $secret,
            ':active' => $active
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteSubscription(int $id, int $userId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM webhook_subscriptions WHERE id = :id AND user_id = :user_id AND tenant_id = :tenant_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId, ':tenant_id' => $tenantId]);
        return $stmt->rowCount();
    }

    public function createLog(int $subscriptionId, int $userId, int $tenantId, string $eventId, string $status, float $delay = 0, ?string $error = null): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO webhook_logs (subscription_id, event_id, status, delay_seconds, error_message, created_at)
            SELECT :subscription_id, :event_id, :status, :delay, :error, NOW()
            FROM webhook_subscriptions
            WHERE id = :subscription_id AND user_id = :user_id AND tenant_id = :tenant_id"
        );
        return $stmt->execute([
            ':subscription_id' => $subscriptionId,
            ':user_id' => $userId,
            ':tenant_id' => $tenantId,
            ':event_id' => $eventId,
            ':status' => $status,
            ':delay' => $delay,
            ':error' => $error
        ]);
    }

    public function cleanupOldLogs(int $days = 30): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM webhook_logs
             WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );

        $stmt->execute([':days' => $days]);

        return $stmt->rowCount();
    }

    public function findActiveUrlsByEvent(string $eventType): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT url FROM webhooks WHERE FIND_IN_SET(:event_type, eventos) > 0 AND active = 1'
        );
        $stmt->execute([':event_type' => $eventType]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Additional methods for pending logs and updates can be added later
}
