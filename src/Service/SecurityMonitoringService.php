<?php

namespace App\Service;

use App\Repository\SecurityMonitoringRepository;
use InvalidArgumentException;
use PDO;

class SecurityMonitoringService
{
    private PDO $pdo;
    private SecurityMonitoringRepository $repository;

    public function __construct(PDO $pdo, ?SecurityMonitoringRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new SecurityMonitoringRepository($pdo);
    }

    public function getOverview(int $tenantId): array
    {
        $this->validateTenant($tenantId);

        return $this->repository->getOverview($tenantId);
    }

    public function blockIp(int $tenantId, string $ipAddress, string $reason, ?int $blockedByUserId = null): array
    {
        $this->validateTenant($tenantId);
        $this->validateIp($ipAddress);
        $this->validateReason($reason);

        if ($blockedByUserId !== null && $blockedByUserId <= 0) {
            throw new InvalidArgumentException('Usuário bloqueador inválido');
        }

        return $this->repository->blockIp($tenantId, $ipAddress, $reason, $blockedByUserId);
    }

    public function unblockIp(int $tenantId, string $ipAddress): bool
    {
        $this->validateTenant($tenantId);
        $this->validateIp($ipAddress);

        if (!$this->repository->isBlockedForTenant($tenantId, $ipAddress)) {
            throw new InvalidArgumentException('Este IP não está bloqueado');
        }

        return $this->repository->unblockIp($tenantId, $ipAddress);
    }

    public function resolveAnomaly(int $tenantId, int $anomalyId, int $resolvedByUserId): array
    {
        $this->validateTenant($tenantId);

        if ($anomalyId <= 0) {
            throw new InvalidArgumentException('ID de anomalia inválido');
        }

        if ($resolvedByUserId <= 0) {
            throw new InvalidArgumentException('Usuário responsável inválido');
        }

        $anomaly = $this->repository->findOpenAnomalyByTenant($tenantId, $anomalyId);
        if ($anomaly === null) {
            throw new InvalidArgumentException('Anomalia não encontrada ou já foi resolvida');
        }

        $updated = $this->repository->markAnomalyResolved($tenantId, $anomalyId, $resolvedByUserId);
        if (!$updated) {
            throw new InvalidArgumentException('Anomalia não encontrada ou já foi resolvida');
        }

        return [
            'anomaly_id' => $anomalyId,
            'anomaly_type' => $anomaly['anomaly_type'] ?? null,
            'severity' => $anomaly['severity'] ?? null,
        ];
    }

    private function validateTenant(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant inválido');
        }
    }

    private function validateIp(string $ipAddress): void
    {
        $ip = trim($ipAddress);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Formato de IP inválido');
        }
    }

    private function validateReason(string $reason): void
    {
        $normalized = trim($reason);
        if (strlen($normalized) < 3 || strlen($normalized) > 255) {
            throw new InvalidArgumentException('Razão deve ter entre 3 e 255 caracteres');
        }
    }
}
