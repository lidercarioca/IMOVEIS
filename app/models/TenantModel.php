<?php

namespace App\Models;

use App\Infrastructure\Tenant\TenantRepository;
use PDO;

class TenantModel
{
    private PDO $pdo;
    private TenantRepository $repository;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo === null) {
            global $pdo;
            $pdo = $pdo ?? null;
        }

        $this->pdo = $pdo;
        $this->repository = new TenantRepository($this->pdo);
    }

    public function listActiveTenants(): array
    {
        return $this->repository->listActiveTenants();
    }

    public function getTenantById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function getTenantBySlug(string $slug): ?array
    {
        return $this->repository->findBySlug($slug);
    }

    public function getTenantStats(int $tenantId): array
    {
        return $this->repository->getTenantStats($tenantId);
    }

    public function getTenantUsers(int $tenantId, array $filters = []): array
    {
        return $this->repository->getTenantUsers($tenantId, $filters);
    }

    public function userBelongsToTenant(int $userId, int $tenantId): bool
    {
        return $this->repository->userBelongsToTenant($userId, $tenantId);
    }

    public function createTenant(array $data): int
    {
        return $this->repository->createTenant($data);
    }

    public function updateTenant(int $id, array $data): bool
    {
        return $this->repository->updateTenant($id, $data);
    }
}
