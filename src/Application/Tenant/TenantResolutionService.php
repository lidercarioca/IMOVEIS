<?php

namespace App\Application\Tenant;

use App\Infrastructure\Tenant\TenantRepository;
use App\Tenant\PublicTenantResolver;
use PDO;

class TenantResolutionService
{
    private TenantRepository $tenantRepository;

    private PublicTenantResolver $publicTenantResolver;

    public function __construct(PDO $pdo, ?TenantRepository $tenantRepository = null)
    {
        $this->tenantRepository = $tenantRepository ?? new TenantRepository($pdo);
        $this->publicTenantResolver = new PublicTenantResolver($pdo, [], 1, $this->tenantRepository);
    }

    public function resolveFromHost(?string $host): array
    {
        $result = $this->publicTenantResolver->resolve($host);

        return [
            'success' => $result['status'] === 'resolved',
            'tenant_id' => isset($result['tenant_id']) ? (int) $result['tenant_id'] : null,
            'subdomain' => $result['subdomain'] ?? null,
            'host' => $result['host'] ?? ($host ?? 'localhost'),
            'status' => $result['status'] ?? 'invalid_host',
            'message' => $result['message'] ?? 'Nenhum tenant encontrado para o subdomínio informado',
        ];
    }
}
