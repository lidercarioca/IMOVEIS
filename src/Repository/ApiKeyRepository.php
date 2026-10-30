<?php

namespace App\Repository;

use PDO;

class ApiKeyRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findActiveByKey(string $apiKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, tenant_id, name, active, expires_at
             FROM api_keys
             WHERE `key` = :key
               AND active = 1
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1'
        );

        $stmt->execute([':key' => $apiKey]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public function findByTenantAndUser(int $tenantId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, `key`, created_at, expires_at, active
             FROM api_keys
             WHERE user_id = :user_id AND tenant_id = :tenant_id
             ORDER BY created_at DESC'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':tenant_id' => $tenantId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createForTenant(int $tenantId, int $userId, string $name, ?string $expiresAt, string $apiKey): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO api_keys (tenant_id, user_id, name, `key`, created_at, expires_at, active)
             VALUES (:tenant_id, :user_id, :name, :key, NOW(), :expires_at, 1)'
        );

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':name' => $name,
            ':key' => $apiKey,
            ':expires_at' => $expiresAt,
        ]);

        $keyId = (int) $this->pdo->lastInsertId();

        return [
            'id' => $keyId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'name' => $name,
            'key' => $apiKey,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'active' => 1,
        ];
    }

    public function deleteByIdAndTenantAndUser(int $keyId, int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM api_keys WHERE id = :id AND tenant_id = :tenant_id AND user_id = :user_id'
        );

        return $stmt->execute([
            ':id' => $keyId,
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
        ]);
    }
}
