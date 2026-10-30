<?php

namespace App\Application\Integration;

use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;

/**
 * PortalIntegrationService
 * 
 * Serviço de aplicação para gerenciamento de integrações com portais.
 * Orquestra operações de persistência via Repository.
 * Concentra regras de negócio.
 */
class PortalIntegrationService
{
    private PortalIntegrationRepository $repository;

    public function __construct(PortalIntegrationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtém todas as integrações de um tenant
     * 
     * @param int $tenantId
     * @return array Array chaveado por portal_slug
     */
    public function getPortalIntegrationsForTenant(int $tenantId): array
    {
        return $this->repository->findAllByTenant($tenantId);
    }

    /**
     * Obtém uma integração específica
     * 
     * @param int $tenantId
     * @param string $portalSlug
     * @return array|null
     */
    public function getPortalIntegration(int $tenantId, string $portalSlug): ?array
    {
        return $this->repository->findByTenantAndSlug($tenantId, $portalSlug);
    }

    /**
     * Salva uma integração (create ou update)
     * 
     * @param int $tenantId
     * @param string $portalSlug
     * @param bool $enabled
     * @param string|null $endpoint
     * @param array $credentials
     * @return bool
     */
    public function savePortalIntegration(
        int $tenantId,
        string $portalSlug,
        bool $enabled,
        ?string $endpoint,
        array $credentials
    ): bool {
        return $this->repository->save($tenantId, $portalSlug, $enabled, $endpoint, $credentials);
    }

    /**
     * Decodifica credentials
     * 
     * @param string|null $credentialsText
     * @return array
     */
    public function decodePortalIntegrationCredentials(?string $credentialsText): array
    {
        return $this->repository->decodeCredentials($credentialsText);
    }

    /**
     * Mascara credentials para resposta segura
     * 
     * @param array $credentials
     * @return array
     */
    public function maskPortalIntegrationCredentials(array $credentials): array
    {
        return $this->repository->maskCredentials($credentials);
    }
}
