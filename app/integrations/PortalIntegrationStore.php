<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;

function ensurePortalIntegrationsTable(PDO $pdo): void
{
    $repository = new PortalIntegrationRepository($pdo);
    $repository->ensureTableExists();
}

function getPortalIntegrationsForTenant(PDO $pdo, int $tenantId): array
{
    $repository = new PortalIntegrationRepository($pdo);
    return $repository->findAllByTenant($tenantId);
}

function getPortalIntegration(PDO $pdo, int $tenantId, string $portalSlug): ?array
{
    $repository = new PortalIntegrationRepository($pdo);
    return $repository->findByTenantAndSlug($tenantId, $portalSlug);
}

function savePortalIntegration(PDO $pdo, int $tenantId, string $portalSlug, bool $enabled, ?string $endpoint, array $credentials): bool
{
    $repository = new PortalIntegrationRepository($pdo);
    return $repository->save($tenantId, $portalSlug, $enabled, $endpoint, $credentials);
}

function decodePortalIntegrationCredentials(?string $credentialsText): array
{
    if (empty($credentialsText)) {
        return [];
    }

    $decoded = json_decode($credentialsText, true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function maskPortalIntegrationCredentials(array $credentials): array
{
    $masked = [];
    foreach ($credentials as $key => $value) {
        if ($value === null || $value === '') {
            $masked[$key] = '';
            continue;
        }

        $masked[$key] = str_repeat('*', min(6, max(1, strlen((string) $value))));
    }

    return $masked;
}
