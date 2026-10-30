<?php

namespace App\Service;

use App\Repository\MessageRepository;
use InvalidArgumentException;
use PDO;

class MessageService
{
    private PDO $pdo;
    private MessageRepository $repository;

    public function __construct(PDO $pdo, ?MessageRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new MessageRepository($pdo);
    }

    public function listForUser(int $userId, int $tenantId): array
    {
        $this->validateUserAndTenant($userId, $tenantId);

        return $this->repository->listForUser($userId, $tenantId);
    }

    public function countUnreadForUser(int $userId, int $tenantId): int
    {
        $this->validateUserAndTenant($userId, $tenantId);

        return $this->repository->countUnreadForUser($userId, $tenantId);
    }

    public function countUnreadForTenant(int $tenantId): int
    {
        $this->validateTenant($tenantId);

        return $this->repository->countUnreadForTenant($tenantId);
    }

    public function listUnreadForTenant(int $tenantId, int $limit = 10): array
    {
        $this->validateTenant($tenantId);

        if ($limit <= 0) {
            throw new InvalidArgumentException('Limite inválido');
        }

        return $this->repository->listUnreadForTenant($tenantId, $limit);
    }

    public function markAsRead(int $userId, int $tenantId, ?int $messageId = null): bool
    {
        $this->validateUserAndTenant($userId, $tenantId);

        if ($messageId !== null && $messageId <= 0) {
            throw new InvalidArgumentException('Mensagem inválida');
        }

        return $this->repository->markAsRead($userId, $tenantId, $messageId);
    }

    private function validateUserAndTenant(int $userId, int $tenantId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido');
        }

        $this->validateTenant($tenantId);
    }

    private function validateTenant(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }
    }
}
