<?php

namespace Tests\Unit;

use App\Application\Tenant\TenantRegistrationService;
use App\Application\Tenant\TenantResolutionService;
use App\Infrastructure\Tenant\TenantRepository;
use App\Services\TenantSubdomainManager;
use PHPUnit\Framework\TestCase;

class TenantMigrationContractTest extends TestCase
{
    public function testTenantServicesAndRepositoryExposeMigrationBoundaries(): void
    {
        $this->assertTrue(class_exists(TenantRegistrationService::class));
        $this->assertTrue(class_exists(TenantResolutionService::class));
        $this->assertTrue(class_exists(TenantRepository::class));
        $this->assertTrue(class_exists(TenantSubdomainManager::class));
        $this->assertTrue(method_exists(TenantRegistrationService::class, 'createTenant'));
        $this->assertTrue(method_exists(TenantResolutionService::class, 'resolveFromHost'));
        $this->assertTrue(method_exists(TenantRepository::class, 'findActiveTenantSubdomainMap'));
        $this->assertTrue(method_exists(TenantSubdomainManager::class, 'subdomainExists'));
    }

    public function testTenantEndpointsUseServicesAndNoInlineSql(): void
    {
        $createEndpoint = file_get_contents(__DIR__ . '/../../api/v1/tenants/create.php');
        $resolveEndpoint = file_get_contents(__DIR__ . '/../../api/v1/tenant/getTenantIdFromSubdomain.php');
        $subdomainManager = file_get_contents(__DIR__ . '/../../app/services/TenantSubdomainManager.php');

        $this->assertNotFalse($createEndpoint);
        $this->assertNotFalse($resolveEndpoint);
        $this->assertNotFalse($subdomainManager);
        $this->assertStringContainsString('TenantRegistrationService', $createEndpoint);
        $this->assertStringNotContainsString('INSERT INTO tenants', $createEndpoint);
        $this->assertStringContainsString('TenantResolutionService', $resolveEndpoint);
        $this->assertStringNotContainsString('SELECT id, slug FROM tenants', $resolveEndpoint);
        $this->assertStringContainsString('new TenantRepository', $subdomainManager);
        $this->assertStringNotContainsString('SELECT COUNT(*) as count FROM tenants WHERE company_subdomain = ?', $subdomainManager);
        $this->assertStringNotContainsString('UPDATE tenants SET company_subdomain = ?', $subdomainManager);
    }
}
