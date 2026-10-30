<?php

namespace App\Infrastructure\Billing;

use PDO;
use RuntimeException;

/**
 * StripeEventRepository
 *
 * Repositório para persistência de eventos de webhook do Stripe.
 * Observação: se a tabela não existir, os métodos lançam RuntimeException
 * indicando dependência de migration/DDL.
 */
class StripeEventRepository
{
    private PDO $pdo;
    private string $table = 'stripe_webhook_events';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    private function ensureTableExistsOrThrow(): void
    {
        // Ensure we have a valid PDO and that the production table exists.
        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('StripeEventRepository requires a valid PDO instance');
        }

        try {
            $this->pdo->query("SELECT 1 FROM {$this->table} LIMIT 1");
        } catch (\Throwable $e) {
            // Do not attempt to create schema automatically in production code.
            throw new RuntimeException("Table '{$this->table}' missing. Create migration to add it. SQL: CREATE TABLE {$this->table} (id INT AUTO_INCREMENT PRIMARY KEY, stripe_event_id VARCHAR(255) UNIQUE, event_id VARCHAR(255), payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(payload)), status ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending', tenant_id INT DEFAULT NULL, retry_count INT NOT NULL DEFAULT 0, error_message TEXT DEFAULT NULL, processed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT NULL); Original error: " . $e->getMessage());
        }
    }

    private function findRowIdByEventId(string $eventId): ?int
    {
        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('StripeEventRepository not properly constructed with PDO');
        }

        $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE stripe_event_id = ? LIMIT 1");
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return (int)$row['id'];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE event_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (int)$row['id'] : null;
    }

    public function findByEventId(string $eventId): ?array
    {
        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('StripeEventRepository not properly constructed with PDO');
        }

        $this->ensureTableExistsOrThrow();

        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE stripe_event_id = ? LIMIT 1");
        $stmt->execute([$eventId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data !== false) {
            return $data;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE event_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$eventId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public function createPendingEvent(string $eventId, string $payload, ?int $tenantId = null): int
    {
        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('StripeEventRepository not properly constructed with PDO');
        }

        if ($eventId === '') {
            throw new \InvalidArgumentException('eventId is required');
        }

        if ($payload === '') {
            throw new \InvalidArgumentException('payload is required');
        }

        $transactionStarted = false;

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $transactionStarted = true;
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->table}
                    (stripe_event_id, event_id, payload, status, tenant_id, retry_count, created_at, updated_at)
                 VALUES (?, ?, ?, 'pending', ?, 0, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE id = id"
            );
            $stmt->execute([$eventId, $eventId, $payload, $tenantId]);

            $insertId = (int)$this->pdo->lastInsertId();
            if ($transactionStarted) {
                $this->pdo->commit();
            }

            if ($insertId > 0) {
                return $insertId;
            }

            $existing = $this->findByEventId($eventId);
            if ($existing !== null) {
                return (int)$existing['id'];
            }

            throw new RuntimeException('Failed to create or resolve existing stripe webhook event');
        } catch (\Throwable $e) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function markProcessed(string $eventId): bool
    {
        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('StripeEventRepository not properly constructed with PDO');
        }

        $this->ensureTableExistsOrThrow();
        $id = $this->findRowIdByEventId($eventId);
        if ($id === null) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'processed', processed_at = NOW(), updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function markPending(string $eventId): bool
    {
        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('StripeEventRepository not properly constructed with PDO');
        }

        $this->ensureTableExistsOrThrow();
        $id = $this->findRowIdByEventId($eventId);
        if ($id === null) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'pending', updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function markFailed(string $eventId, ?string $lastError = null): bool
    {
        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('StripeEventRepository not properly constructed with PDO');
        }

        $this->ensureTableExistsOrThrow();
        $id = $this->findRowIdByEventId($eventId);
        if ($id === null) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$lastError, $id]);
    }

    public function incrementRetryCount(string $eventId): bool
    {
        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('StripeEventRepository not properly constructed with PDO');
        }

        $this->ensureTableExistsOrThrow();
        $id = $this->findRowIdByEventId($eventId);
        if ($id === null) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET retry_count = retry_count + 1, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function findByStripeEntity(string $stripeEntity, ?string $entityId = null): array
    {
        throw new RuntimeException('Legacy stripe_entity lookup is not supported by the current stripe_webhook_events schema.');
    }
}
