<?php
/**
 * Helpers para URLs públicas de tenants
 */

require_once __DIR__ . '/../../config/load_env.php';

/**
 * Retorna o domínio base usado para os subdomínios dos tenants.
 * Exemplo: meudominio.com.br ou localhost.
 *
 * @return string
 */
function getTenantBaseDomain(): string {
    $siteUrl = getenv('SITE_URL') ?: getenv('NGROK_URL') ?: '';

    if (empty($siteUrl)) {
        return 'localhost';
    }

    $parsed = parse_url($siteUrl);
    $host = $parsed['host'] ?? $siteUrl;

    if (empty($host)) {
        return 'localhost';
    }

    // Remover www. se estiver presente para manter o domínio base limpo
    $host = preg_replace('/^www\./i', '', $host);

    return $host;
}

/**
 * Retorna o host/subdomínio final do tenant.
 *
 * @param array $tenant
 * @return string
 */
function getTenantSubdomain(array $tenant): string {
    if (!empty($tenant['company_subdomain'])) {
        return $tenant['company_subdomain'];
    }

    if (!empty($tenant['slug'])) {
        return $tenant['slug'];
    }

    return 'tenant';
}

/**
 * Retorna a URL pública completa do tenant.
 * Exemplo: https://nomedaempresa.meudominio.com.br/
 *
 * @param array $tenant
 * @param string $path
 * @return string
 */
function getTenantPublicUrl(array $tenant, string $path = '/'): string {
    $baseDomain = getTenantBaseDomain();
    $subdomain = getTenantSubdomain($tenant);
    $scheme = 'https';

    if (stripos($baseDomain, 'localhost') !== false || stripos($baseDomain, '127.0.0.1') !== false) {
        $scheme = 'http';
    }

    $path = '/' . ltrim($path, '/');
    return $scheme . '://' . $subdomain . '.' . $baseDomain . $path;
}
