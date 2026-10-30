<?php

namespace App\Application\Tenant;

use App\Infrastructure\Tenant\TenantRepository;
use PDO;

class TenantResolutionService
{
    private TenantRepository $tenantRepository;

    public function __construct(PDO $pdo, ?TenantRepository $tenantRepository = null)
    {
        $this->tenantRepository = $tenantRepository ?? new TenantRepository($pdo);
    }

    public function resolveFromHost(?string $host): array
    {
        $hostname = $host ? explode(':', $host)[0] : 'localhost';
        $parts = explode('.', strtolower($hostname));
        $tenantId = null;
        $subdomain = null;

        if (count($parts) > 1) {
            $subdomain = strtolower((string)$parts[0]);
            $tenantMap = $this->tenantRepository->findActiveTenantSubdomainMap();
            $tenantId = $tenantMap[$subdomain] ?? null;
        }

        $success = $tenantId !== null;

        return [
            'success' => $success,
            'tenant_id' => $tenantId !== null ? (int)$tenantId : null,
            'subdomain' => $subdomain,
            'host' => $host ?? 'localhost',
            'message' => $success
                ? 'Tenant ID detectado com sucesso'
                : 'Nenhum tenant encontrado para o subdomínio informado',
        ];
    }
}
