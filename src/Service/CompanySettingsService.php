<?php

namespace App\Service;

use PDO;
use App\Infrastructure\Company\CompanySettingsRepository;

/**
 * CompanySettingsService - Camada de serviço para configurações de empresa
 *
 * Responsável apenas por:
 * - orquestrar leitura e atualização de company_settings
 * - delegar acesso a dados para CompanySettingsRepository
 */
class CompanySettingsService
{
    private PDO $pdo;
    private CompanySettingsRepository $repository;

    public function __construct(PDO $pdo, ?CompanySettingsRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new CompanySettingsRepository($pdo);
    }

    /**
     * Obtém a configuração de contato pelo ID.
     */
    public function getContactInfo(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Obtém a configuração de contato para o tenant atual.
     */
    public function getContactInfoForTenant(int $tenantId): ?array
    {
        return $this->repository->findByTenant($tenantId);
    }

    /**
     * Obtém o caminho da logo do tenant.
     */
    public function getLogoPathByTenant(int $tenantId): ?string
    {
        $data = $this->repository->findByTenant($tenantId);

        return $data['company_logo'] ?? null;
    }

    /**
     * Cria ou atualiza a configuração do tenant.
     */
    public function saveForTenant(int $tenantId, array $data): bool
    {
        $existing = $this->repository->findByTenant($tenantId);

        if ($existing !== null) {
            return $this->repository->update($tenantId, $data);
        }

        $this->repository->create($tenantId, $data);

        return true;
    }

    /**
     * Cria ou atualiza o caminho da logo do tenant.
     */
    public function upsertLogoPath(int $tenantId, string $path): bool
    {
        $existing = $this->repository->findByTenant($tenantId);

        if ($existing !== null) {
            return $this->repository->update($tenantId, ['company_logo' => $path]);
        }

        return $this->repository->create($tenantId, ['company_logo' => $path]) > 0;
    }
}
