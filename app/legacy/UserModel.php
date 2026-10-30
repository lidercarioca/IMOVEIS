<?php

/**
 * ============================================================================
 * DEPRECATED — LEGACY MODEL
 * ============================================================================
 *
 * Compatibilidade para chamadas antigas de Usuário/Tenant.
 * A lógica de negócio e persistência agora está centralizada em:
 * - App\Application\User\UserService
 * - App\Infrastructure\User\UserRepository
 * - App\Application\User\UserAuditService
 * - App\Infrastructure\Tenant\TenantRepository
 *
 * Este adaptador preserva a API legacy sem reintroduzir SQL direto no modelo.
 * ============================================================================
 */

namespace App\Models;

if (!class_exists('\\TenantContext', false) && file_exists(__DIR__ . '/../security/TenantContext.php')) {
    require_once __DIR__ . '/../security/TenantContext.php';
}

use App\Application\User\UserAuditService;
use App\Application\User\UserService;
use App\Infrastructure\Tenant\TenantRepository;
use App\Infrastructure\User\UserAuditRepository;
use App\Infrastructure\User\UserRepository;
use PDO;
use PDOException;
use RuntimeException;

class UserModel
{
    private PDO $pdo;
    private UserRepository $repository;
    private UserService $service;
    private UserAuditService $auditService;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo === null) {
            global $pdo;
            $pdo = $pdo ?? null;
        }

        $this->pdo = $pdo;
        $this->repository = new UserRepository($this->pdo);
        $this->auditService = new UserAuditService(new UserAuditRepository($this->pdo));
        $this->service = new UserService($this->repository, $this->auditService);
    }

    private function resolveTenantId(): int
    {
        if (class_exists('\\TenantContext')) {
            $tenantId = \TenantContext::getTenantId();
            if (is_int($tenantId) && $tenantId > 0) {
                return $tenantId;
            }
        }

        if (isset($_SESSION['tenant_id'])) {
            $tenantId = (int) $_SESSION['tenant_id'];
            if ($tenantId > 0) {
                return $tenantId;
            }
        }

        return 0;
    }

    private function resolveCurrentUserId(): ?int
    {
        if (class_exists('\\TenantContext')) {
            $userId = \TenantContext::getUserId();
            if (is_int($userId) && $userId > 0) {
                return $userId;
            }
        }

        if (isset($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
            if ($userId > 0) {
                return $userId;
            }
        }

        return null;
    }

    public function listUsers(array $filters = []): array
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId <= 0) {
            return [];
        }

        $result = $this->service->listUsers($tenantId, $filters, 1, 1000);
        return $result['data'] ?? [];
    }

    public function getUserById(int $id): ?array
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId <= 0) {
            return null;
        }

        try {
            return $this->service->getUserById($id, $tenantId);
        } catch (RuntimeException $e) {
            return null;
        }
    }

    public function getUserByUsername(string $username): ?array
    {
        return $this->repository->findByUsername($username);
    }

    public function getUserByEmail(string $email): ?array
    {
        return $this->repository->findByEmail($email);
    }

    public function createUser(array $data): int
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId <= 0) {
            throw new PDOException('Tenant inválido para criação do usuário');
        }

        $user = $this->service->createUser($tenantId, $data);
        return (int) ($user['id'] ?? 0);
    }

    public function updateUser(int $id, array $data): bool
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId <= 0) {
            throw new PDOException('Tenant inválido para atualização do usuário');
        }

        $this->service->updateUser($id, $tenantId, $data);
        return true;
    }

    public function deleteUser(int $id): bool
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId <= 0) {
            throw new PDOException('Tenant inválido para remoção do usuário');
        }

        return $this->service->deleteUser($id, $tenantId);
    }

    public function logUserChange(int $userId, array $oldData, array $newData, string $action): bool
    {
        $tenantId = $this->resolveTenantId();
        $actorUserId = $this->resolveCurrentUserId();

        if ($tenantId <= 0 || $actorUserId === null || $actorUserId <= 0) {
            return false;
        }

        return $this->auditService->logUserChange(
            $userId,
            $oldData,
            $newData,
            $action,
            $actorUserId,
            $tenantId,
            sprintf('Usuário %s', $action),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            'success',
            null
        );
    }
}
