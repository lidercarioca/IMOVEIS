<?php

namespace Tests\Unit;

use App\Repository\SiteVisitRepository;
use App\Service\SiteVisitService;
use PHPUnit\Framework\TestCase;

class SiteVisitMigrationContractTest extends TestCase
{
    public function testSiteVisitServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(SiteVisitService::class));
        $this->assertTrue(class_exists(SiteVisitRepository::class));
        $this->assertTrue(method_exists(SiteVisitService::class, 'trackVisit'));
        $this->assertTrue(method_exists(SiteVisitRepository::class, 'hasRecentVisitForSession'));
        $this->assertTrue(method_exists(SiteVisitRepository::class, 'insertVisit'));
    }

    public function testSiteVisitEndpointUsesServiceAndNoInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/v1/auth/trackVisit.php');

        $this->assertStringContainsString('new \\App\\Service\\SiteVisitService', $endpoint);
        $this->assertStringNotContainsString('SELECT id FROM site_visits', $endpoint);
        $this->assertStringNotContainsString('INSERT INTO site_visits', $endpoint);
    }

    public function testPublicIndexVisitTrackingDoesNotUseInlineSqlOrTenantFallback(): void
    {
        $index = file_get_contents(__DIR__ . '/../../index.php');

        $this->assertStringNotContainsString('SELECT id FROM site_visits', $index);
        $this->assertStringNotContainsString('INSERT INTO site_visits', $index);
        $this->assertStringNotContainsString('tenant_id_for_injection) : (int)($_SESSION[\'tenant_id\'] ?? 1)', $index);
        $this->assertStringContainsString('$trackingTenantId', $index);
        $this->assertStringContainsString('if ($tenantIdForVisit > 0)', $index);
    }
}
