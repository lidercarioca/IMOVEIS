<?php

namespace App\Application\User;

use App\Auth\AuthContext;
use App\Infrastructure\User\UserAuditRepository;

class UserAuditService
{
    private UserAuditRepository $repository;

    public function __construct(UserAuditRepository $repository)
    {
        $this->repository = $repository;
    }

    public function logUserChange(
        int $targetUserId,
        array $oldValues,
        array $newValues,
        string $actionType,
        ?int $actorUserId = null,
        ?int $tenantId = null,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        string $status = 'success',
        ?string $idempotencyKey = null
    ): bool {
        $resolvedActor = $actorUserId ?? AuthContext::getInstance()->getUserId();
        $resolvedTenant = $tenantId ?? $this->resolveTenantId();

        if ($targetUserId <= 0 || $resolvedActor === null || $resolvedActor <= 0 || $resolvedTenant === null || $resolvedTenant <= 0) {
            error_log('[UserAuditService] Bloqueado: actor ou tenant inválido para auditoria de usuário.');
            return false;
        }

        $safeOldValues = $this->sanitizePayload($oldValues);
        $safeNewValues = $this->sanitizePayload($newValues);

        if ($this->repository->hasRecentDuplicate(
            $resolvedTenant,
            $resolvedActor,
            $actionType,
            $targetUserId,
            $safeOldValues,
            $safeNewValues,
            $idempotencyKey
        )) {
            return true;
        }

        try {
            return $this->repository->recordUserAudit(
                $resolvedActor,
                $resolvedTenant,
                $actionType,
                'user',
                $targetUserId,
                $description ?? sprintf('Usuário %s', $actionType),
                $safeOldValues,
                $safeNewValues,
                $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
                $status,
                $idempotencyKey
            );
        } catch (\Throwable $exception) {
            error_log('[UserAuditService] Falha ao registrar auditoria de usuário: ' . $exception->getMessage());
            return false;
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), ['password', 'password_hash', 'hash', 'secret', 'token', 'api_key', 'authorization', 'cookie'], true)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    private function resolveTenantId(): ?int
    {
        if (class_exists('\\TenantContext')) {
            $resolved = \TenantContext::getTenantId();
            if (is_int($resolved) && $resolved > 0) {
                return $resolved;
            }
        }

        if (isset($_SESSION['tenant_id']) && (int) $_SESSION['tenant_id'] > 0) {
            return (int) $_SESSION['tenant_id'];
        }

        return null;
    }
}
