<?php

namespace App\Infrastructure\User;

use PDO;
use PDOException;

class UserAuditRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function hasRecentDuplicate(
        int $tenantId,
        int $actorUserId,
        string $actionType,
        int $resourceId,
        array $oldValues,
        array $newValues,
        $idempotencyKey = null,
        int $windowSeconds = 5
    ): bool {
        $legacyWindowSeconds = $windowSeconds;
        if (is_int($idempotencyKey) || (is_string($idempotencyKey) && preg_match('/^\d+$/', $idempotencyKey) === 1)) {
            $legacyWindowSeconds = (int) $idempotencyKey;
            $idempotencyKey = null;
        }

        $normalizedKey = $this->normalizeIdempotencyKey(is_string($idempotencyKey) ? $idempotencyKey : null);
        if ($normalizedKey === null || !$this->supportsIdempotencyKeyColumn()) {
            return false;
        }

        $sql = 'SELECT 1 FROM audit_logs WHERE tenant_id = :tenant_id AND user_id = :user_id AND action_type = :action_type AND resource_id = :resource_id AND idempotency_key = :idempotency_key LIMIT 1';
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $actorUserId,
            ':action_type' => $actionType,
            ':resource_id' => $resourceId,
            ':idempotency_key' => $normalizedKey,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function recordUserAudit(
        int $actorUserId,
        int $tenantId,
        string $actionType,
        string $resourceType,
        int $resourceId,
        string $description,
        array $oldValues = [],
        array $newValues = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
        string $status = 'success',
        ?string $idempotencyKey = null
    ): bool {
        $availableColumns = $this->getAuditLogColumns();

        $columns = ['user_id', 'tenant_id', 'action_type'];
        $placeholders = [':user_id', ':tenant_id', ':action_type'];
        $params = [
            ':user_id' => $actorUserId,
            ':tenant_id' => $tenantId,
            ':action_type' => $actionType,
        ];

        if (in_array('resource_type', $availableColumns, true)) {
            $columns[] = 'resource_type';
            $placeholders[] = ':resource_type';
            $params[':resource_type'] = $resourceType;
        }

        if (in_array('resource_id', $availableColumns, true)) {
            $columns[] = 'resource_id';
            $placeholders[] = ':resource_id';
            $params[':resource_id'] = $resourceId;
        }

        if (in_array('description', $availableColumns, true)) {
            $columns[] = 'description';
            $placeholders[] = ':description';
            $params[':description'] = $description;
        }

        if (in_array('old_values', $availableColumns, true)) {
            $columns[] = 'old_values';
            $placeholders[] = ':old_values';
            $params[':old_values'] = empty($oldValues) ? null : json_encode($oldValues, JSON_THROW_ON_ERROR);
        }

        if (in_array('new_values', $availableColumns, true)) {
            $columns[] = 'new_values';
            $placeholders[] = ':new_values';
            $params[':new_values'] = empty($newValues) ? null : json_encode($newValues, JSON_THROW_ON_ERROR);
        }

        if (in_array('ip_address', $availableColumns, true)) {
            $columns[] = 'ip_address';
            $placeholders[] = ':ip_address';
            $params[':ip_address'] = $ipAddress;
        }

        if (in_array('user_agent', $availableColumns, true)) {
            $columns[] = 'user_agent';
            $placeholders[] = ':user_agent';
            $params[':user_agent'] = $userAgent;
        }

        if (in_array('status', $availableColumns, true)) {
            $columns[] = 'status';
            $placeholders[] = ':status';
            $params[':status'] = $status;
        }

        $normalizedKey = $this->normalizeIdempotencyKey($idempotencyKey);
        if ($normalizedKey !== null && $this->supportsIdempotencyKeyColumn()) {
            $columns[] = 'idempotency_key';
            $placeholders[] = ':idempotency_key';
            $params[':idempotency_key'] = $normalizedKey;
        }

        $sql = sprintf(
            'INSERT INTO audit_logs (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException | \JsonException $exception) {
            error_log('Erro ao registrar auditoria de usuário: ' . $exception->getMessage());
            return false;
        }
    }

    private function getAuditLogColumns(): array
    {
        try {
            $stmt = $this->pdo->query('PRAGMA table_info(audit_logs)');
            if ($stmt !== false) {
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($columns !== []) {
                    return array_map(static fn(array $column) => (string) ($column['name'] ?? ''), $columns);
                }
            }
        } catch (\Throwable $exception) {
            // Fallback for MySQL-driven environments.
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM audit_logs");
            if ($stmt !== false) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($rows !== []) {
                    return array_map(static fn(array $row) => (string) ($row['Field'] ?? ''), $rows);
                }
            }
        } catch (\Throwable $exception) {
            // Column list is unavailable.
        }

        return [];
    }

    private function supportsIdempotencyKeyColumn(): bool
    {
        $columns = $this->getAuditLogColumns();
        return in_array('idempotency_key', $columns, true);
    }

    private function normalizeIdempotencyKey(?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $normalized = trim((string) $idempotencyKey);
        return $normalized === '' ? null : $normalized;
    }
}
