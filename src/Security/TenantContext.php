<?php

namespace App\Security;

/**
 * Compatibility adapter for legacy TenantContext.
 *
 * This class is PSR-4 autoloadable and delegates all work to the
 * legacy global TenantContext implementation in app/security/TenantContext.php.
 */
class TenantContext
{
    private static bool $legacyLoaded = false;

    private static function ensureLegacyLoaded(): void
    {
        if (self::$legacyLoaded) {
            return;
        }

        $legacyPath = dirname(__DIR__, 2) . '/app/security/TenantContext.php';
        if (!file_exists($legacyPath)) {
            throw new \RuntimeException('Legacy TenantContext not found at ' . $legacyPath);
        }

        require_once $legacyPath;
        self::$legacyLoaded = true;
    }

    public static function initialize(): void
    {
        self::ensureLegacyLoaded();
        \TenantContext::initialize();
    }

    public static function getTenantId(): ?int
    {
        self::initialize();
        return \TenantContext::getTenantId();
    }

    public static function getSelectedTenantId(): ?int
    {
        self::initialize();
        return \TenantContext::getSelectedTenantId();
    }

    public static function getRequestTenantId(): ?int
    {
        self::initialize();
        return \TenantContext::getRequestTenantId();
    }

    public static function isSuperAdmin(): bool
    {
        self::initialize();
        return \TenantContext::isSuperAdmin();
    }

    public static function getResolutionSource(): string
    {
        self::initialize();
        return \TenantContext::getResolutionSource();
    }

    public static function selectTenant(int $tenantId): bool
    {
        self::ensureLegacyLoaded();
        return \TenantContext::selectTenant($tenantId);
    }
}
