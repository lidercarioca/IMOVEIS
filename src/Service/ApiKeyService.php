<?php

namespace App\Service;

use App\Repository\ApiKeyRepository;
use PDO;

class ApiKeyService
{
    private PDO $pdo;
    private ApiKeyRepository $repository;

    public function __construct(PDO $pdo, ?ApiKeyRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new ApiKeyRepository($pdo);
    }

    public function validateKey(string $apiKey): ?array
    {
        $normalizedKey = trim((string) $apiKey);
        if ($normalizedKey === '') {
            return null;
        }

        $record = $this->repository->findActiveByKey($normalizedKey);
        if ($record === null) {
            return null;
        }

        $tenantId = isset($record['tenant_id']) ? (int) $record['tenant_id'] : 0;
        if ($tenantId <= 0) {
            return null;
        }

        return $record;
    }

    public function listKeysForUser(int $tenantId, int $userId): array
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return [];
        }

        $rows = $this->repository->findByTenantAndUser($tenantId, $userId);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'key' => (string) ($row['key'] ?? ''),
                'created_at' => $row['created_at'] ?? null,
                'expires_at' => $row['expires_at'] ?? null,
                'is_active' => (bool) ($row['active'] ?? false),
            ];
        }, $rows);
    }

    public function createKeyForTenant(int $tenantId, int $userId, string $name, ?string $expiresAt): array
    {
        $validatedName = trim($name);
        if ($tenantId <= 0 || $userId <= 0 || $validatedName === '') {
            throw new \InvalidArgumentException('Dados inválidos para criação da chave de API.');
        }

        $apiKey = bin2hex(random_bytes(16));
        $record = $this->repository->createForTenant($tenantId, $userId, $validatedName, $expiresAt, $apiKey);

        return [
            'id' => (int) ($record['id'] ?? 0),
            'key' => (string) ($record['key'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'expires_at' => $record['expires_at'] ?? null,
        ];
    }

    public function revokeKeyForUser(int $tenantId, int $userId, int $keyId): bool
    {
        if ($tenantId <= 0 || $userId <= 0 || $keyId <= 0) {
            return false;
        }

        return $this->repository->deleteByIdAndTenantAndUser($keyId, $tenantId, $userId);
    }
}
