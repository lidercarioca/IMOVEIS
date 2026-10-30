<?php

namespace Tests\Unit;

use App\Repository\UtmRepository;
use App\Service\UtmService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

class UtmMigrationContractTest extends TestCase
{
    public function testUtmServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(UtmService::class));
        $this->assertTrue(class_exists(UtmRepository::class));
    }

    public function testUtmServicePreservesCoreContracts(): void
    {
        $this->assertTrue(method_exists(UtmService::class, 'listCampaigns'));
        $this->assertTrue(method_exists(UtmService::class, 'createCampaign'));
        $this->assertTrue(method_exists(UtmService::class, 'updateCampaign'));
        $this->assertTrue(method_exists(UtmService::class, 'deleteCampaign'));
        $this->assertTrue(method_exists(UtmService::class, 'getAnalytics'));
        $this->assertTrue(method_exists(UtmService::class, 'trackClick'));
    }

    public function testEndpointAccessesServiceInsteadOfRawSql(): void
    {
        $getCampaigns = file_get_contents(__DIR__ . '/../../api/v1/utm/getCampaigns.php');
        $getAnalytics = file_get_contents(__DIR__ . '/../../api/v1/utm/getAnalytics.php');
        $trackClick = file_get_contents(__DIR__ . '/../../api/v1/utm/trackClick.php');

        $this->assertStringContainsString('new \\App\\Service\\UtmService', $getCampaigns);
        $this->assertStringContainsString('new \\App\\Service\\UtmService', $getAnalytics);
        $this->assertStringContainsString('new \\App\\Service\\UtmService', $trackClick);
        $this->assertStringNotContainsString('$tenantId = 1;', $trackClick);

        $this->assertStringNotContainsString('SELECT * FROM utm_campaigns', $getCampaigns);
        $this->assertStringNotContainsString('INSERT INTO utm_tracking', $trackClick);
        $this->assertStringNotContainsString('SELECT COUNT(*) as total_clicks', $getAnalytics);
    }

    public function testGetAnalyticsDoesNotFallBackToTenantOne(): void
    {
        $analytics = file_get_contents(__DIR__ . '/../../api/v1/utm/getAnalytics.php');

        $this->assertStringContainsString('TenantMiddleware::resolveTenantId', $analytics);
        $this->assertStringContainsString('new \\App\\Service\\UtmService', $analytics);
        $this->assertStringNotContainsString('$tenantId = 1;', $analytics);
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($analytics));
    }

    public function testTrackClickAllowsValidTenantAndPersistsTracking(): void
    {
        $repository = $this->getMockBuilder(UtmRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findCampaignIdByTenantAndName', 'insertTracking'])
            ->getMock();

        $repository->expects($this->once())
            ->method('findCampaignIdByTenantAndName')
            ->with(42, 'promo')
            ->willReturn(7);

        $repository->expects($this->once())
            ->method('insertTracking')
            ->with(
                42,
                7,
                $this->arrayHasKey('utm_campaign'),
                '127.0.0.1',
                'phpunit',
                'https://example.com/source'
            )
            ->willReturn(99);

        $service = new UtmService($this->createMock(PDO::class), $repository);

        $result = $service->trackClick(42, [
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'promo',
            'destination_url' => 'https://example.com/landing',
        ], '127.0.0.1', 'phpunit', 'https://example.com/source');

        $this->assertSame(99, $result['tracking_id']);
        $this->assertSame('promo', $result['campaign']);
    }

    public function testTrackClickRejectsInvalidOrMissingTenantWithoutFallback(): void
    {
        $repository = $this->getMockBuilder(UtmRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insertTracking'])
            ->getMock();

        $repository->expects($this->never())
            ->method('insertTracking');

        $service = new UtmService($this->createMock(PDO::class), $repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant inválido');

        $service->trackClick(0, [
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'promo',
            'destination_url' => 'https://example.com/landing',
        ], '127.0.0.1', 'phpunit', 'https://example.com/source');
    }
}
