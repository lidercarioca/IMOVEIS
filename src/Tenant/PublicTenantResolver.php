<?php

namespace App\Tenant;

use App\Infrastructure\Tenant\TenantRepository;
use PDO;

class PublicTenantResolver
{
    private ?TenantRepository $tenantRepository;

    private array $baseHosts;

    private int $localDefaultTenantId;

    private array $localHosts = ['localhost'];

    public function __construct(?PDO $pdo = null, array $baseHosts = [], ?int $localDefaultTenantId = 1, ?TenantRepository $tenantRepository = null)
    {
        $configuredBaseHosts = $baseHosts;
        if (empty($configuredBaseHosts)) {
            $configuredBaseHosts = $this->readConfiguredHostsFromEnvironment();
        }

        $this->baseHosts = $this->normalizeConfiguredHosts($configuredBaseHosts);
        $this->localDefaultTenantId = $localDefaultTenantId ?? 1;
        $this->tenantRepository = $tenantRepository ?? ($pdo instanceof PDO ? new TenantRepository($pdo) : null);
    }

    public function resolve(?string $host): array
    {
        $hostname = $this->normalizeHost($host);

        if ($hostname === null || $hostname === '') {
            return [
                'status' => 'invalid_host',
                'tenant_id' => null,
                'subdomain' => null,
                'host' => $host ?? null,
                'tenant_name' => null,
                'message' => 'Host público inválido ou ausente.',
            ];
        }

        if ($this->isLocalhostHost($hostname)) {
            if ($this->allowLocalhostDefault($hostname)) {
                $tenant = $this->findTenantById((int) $this->localDefaultTenantId);
                if ($tenant === null) {
                    return [
                        'status' => 'tenant_not_found',
                        'tenant_id' => null,
                        'subdomain' => 'default',
                        'host' => 'localhost',
                        'tenant_name' => null,
                        'message' => 'Host localhost autorizado, mas o tenant local configurado não existe.',
                    ];
                }

                if (($tenant['status'] ?? 'inactive') !== 'active') {
                    return [
                        'status' => 'tenant_inactive',
                        'tenant_id' => (int) $tenant['id'],
                        'subdomain' => (string) ($tenant['company_subdomain'] ?? $tenant['slug'] ?? 'default'),
                        'host' => 'localhost',
                        'tenant_name' => (string) ($tenant['name'] ?? 'Tenant'),
                        'message' => 'Tenant local configurado existe, mas está inativo.',
                    ];
                }

                return [
                    'status' => 'resolved',
                    'tenant_id' => (int) $this->localDefaultTenantId,
                    'subdomain' => 'default',
                    'host' => 'localhost',
                    'tenant_name' => $tenant['name'] ?? 'Local',
                    'message' => 'Host localhost resolvido para o tenant local configurado.',
                ];
            }

            return [
                'status' => 'invalid_host',
                'tenant_id' => null,
                'subdomain' => null,
                'host' => $hostname,
                'tenant_name' => null,
                'message' => 'Localhost exato não autorizado como host público.',
            ];
        }

        if ($this->isLocalhostSubdomain($hostname)) {
            $subdomain = $this->extractLocalhostSubdomain($hostname);
            if ($subdomain === null || $subdomain === '') {
                return [
                    'status' => 'invalid_host',
                    'tenant_id' => null,
                    'subdomain' => null,
                    'host' => $hostname,
                    'tenant_name' => null,
                    'message' => 'Subdomínio localhost inválido para resolução pública.',
                ];
            }

            $tenant = $this->findTenantByPublicAlias($subdomain);
            if ($tenant === null) {
                return [
                    'status' => 'tenant_not_found',
                    'tenant_id' => null,
                    'subdomain' => $subdomain,
                    'host' => $hostname,
                    'tenant_name' => null,
                    'message' => 'Nenhum tenant público cadastrado corresponde ao subdomínio localhost informado.',
                ];
            }

            if (($tenant['status'] ?? 'inactive') !== 'active') {
                return [
                    'status' => 'tenant_inactive',
                    'tenant_id' => (int) $tenant['id'],
                    'subdomain' => $subdomain,
                    'host' => $hostname,
                    'tenant_name' => (string) ($tenant['name'] ?? 'Tenant'),
                    'message' => 'Tenant localhost encontrado, mas está inativo.',
                ];
            }

            return [
                'status' => 'resolved',
                'tenant_id' => (int) $tenant['id'],
                'subdomain' => $subdomain,
                'host' => $hostname,
                'tenant_name' => (string) ($tenant['name'] ?? 'Tenant'),
                'message' => 'Subdomínio localhost resolvido com sucesso.',
            ];
        }

        $matchedBases = $this->findMatchingBaseHosts($hostname);
        if (count($matchedBases) > 1) {
            return [
                'status' => 'config_error',
                'tenant_id' => null,
                'subdomain' => null,
                'host' => $hostname,
                'tenant_name' => null,
                'message' => 'Configuração de base pública ambígua.',
            ];
        }

        if (empty($matchedBases)) {
            return [
                'status' => 'invalid_host',
                'tenant_id' => null,
                'subdomain' => null,
                'host' => $hostname,
                'tenant_name' => null,
                'message' => 'Domínio não autorizado para resolução pública.',
            ];
        }

        $baseHost = $matchedBases[0];
        if ($this->isBaseHost($hostname, $baseHost)) {
            $defaultTenant = $this->findDefaultTenant();
            if ($defaultTenant === null) {
                return [
                    'status' => 'tenant_not_found',
                    'tenant_id' => null,
                    'subdomain' => 'default',
                    'host' => $hostname,
                    'tenant_name' => null,
                    'message' => 'Host base autorizado, mas nenhum tenant padrão configurado foi encontrado.',
                ];
            }

            if (($defaultTenant['status'] ?? 'inactive') !== 'active') {
                return [
                    'status' => 'tenant_inactive',
                    'tenant_id' => (int) $defaultTenant['id'],
                    'subdomain' => (string) ($defaultTenant['company_subdomain'] ?? $defaultTenant['slug'] ?? 'default'),
                    'host' => $hostname,
                    'tenant_name' => (string) ($defaultTenant['name'] ?? 'Tenant'),
                    'message' => 'Tenant padrão configurado, mas está inativo.',
                ];
            }

            return [
                'status' => 'resolved',
                'tenant_id' => (int) $defaultTenant['id'],
                'subdomain' => (string) ($defaultTenant['company_subdomain'] ?? $defaultTenant['slug'] ?? 'default'),
                'host' => $hostname,
                'tenant_name' => (string) ($defaultTenant['name'] ?? 'Tenant'),
                'message' => 'Host base resolvido com sucesso.',
            ];
        }

        $subdomain = $this->extractSubdomain($hostname, $baseHost);
        if ($subdomain === null || $subdomain === '') {
            return [
                'status' => 'invalid_host',
                'tenant_id' => null,
                'subdomain' => null,
                'host' => $hostname,
                'tenant_name' => null,
                'message' => 'Subdomínio não identificado para o host público.',
            ];
        }

        $tenant = $this->findTenantByPublicAlias($subdomain);
        if ($tenant === null) {
            return [
                'status' => 'tenant_not_found',
                'tenant_id' => null,
                'subdomain' => $subdomain,
                'host' => $hostname,
                'tenant_name' => null,
                'message' => 'Nenhum tenant ativo ou conhecido corresponde ao subdomínio informado.',
            ];
        }

        if (($tenant['status'] ?? 'inactive') !== 'active') {
            return [
                'status' => 'tenant_inactive',
                'tenant_id' => (int) $tenant['id'],
                'subdomain' => $subdomain,
                'host' => $hostname,
                'tenant_name' => (string) ($tenant['name'] ?? 'Tenant'),
                'message' => 'Tenant encontrado, mas está inativo.',
            ];
        }

        return [
            'status' => 'resolved',
            'tenant_id' => (int) $tenant['id'],
            'subdomain' => $subdomain,
            'host' => $hostname,
            'tenant_name' => (string) ($tenant['name'] ?? 'Tenant'),
            'message' => 'Tenant público resolvido com sucesso.',
        ];
    }

    private function allowLocalhostDefault(string $hostname): bool
    {
        return $hostname === 'localhost';
    }

    private function normalizeConfiguredHosts(array $baseHosts): array
    {
        $hosts = [];
        foreach ($baseHosts as $host) {
            $normalized = $this->normalizeHost((string) $host);
            if ($normalized !== null && $normalized !== '') {
                $hosts[] = strtolower(rtrim($normalized, '.'));
            }
        }

        return array_values(array_unique(array_filter($hosts, static fn($value) => $value !== '')));
    }

    private function readConfiguredHostsFromEnvironment(): array
    {
        $hosts = [];

        $configuredValue = getenv('TENANT_SUBDOMAIN_BASE_HOSTS') ?: ($_ENV['TENANT_SUBDOMAIN_BASE_HOSTS'] ?? null);
        if (is_string($configuredValue) && trim($configuredValue) !== '') {
            foreach (preg_split('/[;,\s]+/', trim((string) $configuredValue), -1, PREG_SPLIT_NO_EMPTY) as $candidate) {
                $normalized = $this->normalizeHost((string) $candidate);
                if ($normalized !== null && $normalized !== '') {
                    $hosts[] = strtolower(rtrim($normalized, '.'));
                }
            }
        }

        return array_values(array_unique(array_filter($hosts, static fn($value) => $value !== '')));
    }

    private function normalizeHost(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        $value = trim((string) $host);
        if ($value === '') {
            return null;
        }

        $value = strtolower($value);
        $value = preg_replace('/\s+/', '', $value);
        $value = rtrim($value, '.');

        if ($value === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value) === 1 || str_contains($value, '/')) {
            $parsed = parse_url($value);
            if (isset($parsed['host']) && is_string($parsed['host']) && $parsed['host'] !== '') {
                $value = (string) $parsed['host'];
            } else {
                $value = preg_replace('#^https?:\/\/#i', '', $value);
                $value = preg_replace('#\/.*$#', '', $value);
            }
        }

        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $inner = substr($value, 1, -1);
            if (filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                return strtolower($inner);
            }
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return strtolower($value);
        }

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return strtolower($value);
        }

        if (str_contains($value, '::')) {
            if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                return null;
            }
            return strtolower($value);
        }

        if (str_contains($value, ':')) {
            if (preg_match('/^([a-z0-9.-]+):([0-9]{1,5})$/i', $value, $matches) !== 1) {
                return null;
            }

            $hostPart = $matches[1];
            $port = (int) $matches[2];
            if ($port < 1 || $port > 65535) {
                return null;
            }

            if (!$this->isValidDnsHostname($hostPart) && filter_var($hostPart, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                return null;
            }

            return strtolower($hostPart);
        }

        if (!$this->isValidDnsHostname($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        return strtolower($value);
    }

    private function isLocalhostHost(string $hostname): bool
    {
        return $hostname === 'localhost';
    }

    private function isLocalhostSubdomain(string $hostname): bool
    {
        return str_ends_with($hostname, '.localhost');
    }

    private function extractLocalhostSubdomain(string $hostname): ?string
    {
        if (!str_ends_with($hostname, '.localhost')) {
            return null;
        }

        $candidate = substr($hostname, 0, -strlen('.localhost'));
        if ($candidate === '' || str_contains($candidate, '.')) {
            return null;
        }

        if (!preg_match('/^[a-z0-9-]+$/i', $candidate)) {
            return null;
        }

        return strtolower($candidate);
    }

    private function findMatchingBaseHosts(string $hostname): array
    {
        $matches = [];

        foreach ($this->baseHosts as $baseHost) {
            if ($baseHost === '') {
                continue;
            }

            if ($hostname === $baseHost) {
                $matches[] = $baseHost;
                continue;
            }

            if ($this->isValidDnsHostname($hostname) && $this->isValidDnsHostname($baseHost) && str_ends_with($hostname, '.' . $baseHost)) {
                $hostnameLabels = preg_split('/\./', $hostname, -1, PREG_SPLIT_NO_EMPTY);
                $baseLabels = preg_split('/\./', $baseHost, -1, PREG_SPLIT_NO_EMPTY);

                $labelDelta = count($hostnameLabels) - count($baseLabels);
                $isTldLikeBase = strlen($baseHost) <= 3;

                if ($labelDelta === 1 || ($isTldLikeBase && $labelDelta > 1)) {
                    $matches[] = $baseHost;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    private function isBaseHost(string $hostname, string $baseHost): bool
    {
        return $hostname === $baseHost;
    }

    private function extractSubdomain(string $hostname, string $baseHost): ?string
    {
        if ($hostname === $baseHost) {
            return null;
        }

        if (!$this->isValidDnsHostname($hostname) || !$this->isValidDnsHostname($baseHost)) {
            return null;
        }

        $suffix = '.' . $baseHost;
        if (!str_ends_with($hostname, $suffix)) {
            return null;
        }

        $candidate = substr($hostname, 0, -strlen($suffix));
        if ($candidate === '' || str_contains($candidate, '.')) {
            return null;
        }

        if (!preg_match('/^[a-z0-9-]+$/i', $candidate)) {
            return null;
        }

        return strtolower($candidate);
    }

    private function findDefaultTenant(): ?array
    {
        if ($this->tenantRepository === null) {
            return null;
        }

        $defaultBySlug = $this->tenantRepository->findBySlug('default');
        if ($defaultBySlug !== null) {
            return $defaultBySlug;
        }

        $defaultByCompanySubdomain = $this->tenantRepository->findByCompanySubdomain('default');
        if ($defaultByCompanySubdomain !== null) {
            return $defaultByCompanySubdomain;
        }

        $knownTenants = $this->tenantRepository->listKnownTenants();
        foreach ($knownTenants as $tenant) {
            $slug = strtolower((string) ($tenant['slug'] ?? ''));
            $companySubdomain = strtolower((string) ($tenant['company_subdomain'] ?? ''));
            if ($slug === 'default' || $companySubdomain === 'default') {
                return $tenant;
            }
        }

        return null;
    }

    private function findTenantByPublicAlias(string $subdomain): ?array
    {
        if ($this->tenantRepository === null) {
            return null;
        }

        $activeTenants = $this->tenantRepository->listActiveTenants();
        foreach ($activeTenants as $tenant) {
            $slug = strtolower((string) ($tenant['slug'] ?? ''));
            $companySubdomain = strtolower((string) ($tenant['company_subdomain'] ?? ''));
            if ($slug === $subdomain || $companySubdomain === $subdomain) {
                return $tenant;
            }
        }

        $knownTenants = $this->tenantRepository->listKnownTenants();
        foreach ($knownTenants as $tenant) {
            $slug = strtolower((string) ($tenant['slug'] ?? ''));
            $companySubdomain = strtolower((string) ($tenant['company_subdomain'] ?? ''));
            if ($slug === $subdomain || $companySubdomain === $subdomain) {
                return $tenant;
            }
        }

        return null;
    }

    private function findTenantById(int $tenantId): ?array
    {
        if ($this->tenantRepository === null) {
            return null;
        }

        return $this->tenantRepository->findById($tenantId);
    }

    private function isValidDnsHostname(string $hostname): bool
    {
        if ($hostname === '' || strlen($hostname) > 253) {
            return false;
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        if (preg_match('/[^a-z0-9.-]/i', $hostname) === 1) {
            return false;
        }

        if (str_starts_with($hostname, '.') || str_ends_with($hostname, '.')) {
            return false;
        }

        $labels = preg_split('/\./', $hostname, -1, PREG_SPLIT_NO_EMPTY);
        if ($labels === false || $labels === []) {
            return false;
        }

        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63) {
                return false;
            }

            if (preg_match('/^-|-$/', $label) === 1) {
                return false;
            }
        }

        return true;
    }
}
