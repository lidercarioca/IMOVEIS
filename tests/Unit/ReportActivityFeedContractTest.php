<?php

namespace Tests\Unit;

use App\Repository\ReportRepository;
use App\Service\ReportService;
use PDO;
use PHPUnit\Framework\TestCase;

class ReportActivityFeedContractTest extends TestCase
{
    public function testServiceAndRepositoryExposeActivityFeedContract(): void
    {
        $this->assertTrue(method_exists(ReportService::class, 'getActivityFeed'));
        $this->assertTrue(method_exists(ReportRepository::class, 'getActivityFeed'));
    }

    public function testServicePreservesActivityFeedResponseContract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(ReportRepository::class);

        $time = date('Y-m-d H:i:s');
        $repository->expects($this->once())
            ->method('getActivityFeed')
            ->with(42, 5, 0, false, 7)
            ->willReturn([
                'activities' => [[
                    'activity_type' => 'property_created',
                    'activity_id' => 9,
                    'description' => 'Casa Centro',
                    'user_name' => 'Ana',
                    'user_id' => 7,
                    'icon' => 'home',
                    'color' => '#3b82f6',
                    'timestamp' => $time,
                ]],
                'total' => 1,
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getActivityFeed(42, 5, 0, false, 7);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['pagination']['total']);
        $this->assertSame(5, $result['pagination']['limit']);
        $this->assertSame(0, $result['pagination']['offset']);
        $this->assertFalse($result['pagination']['has_more']);
        $this->assertSame('property_created', $result['data'][0]['type']);
        $this->assertSame(9, $result['data'][0]['id']);
        $this->assertSame('Casa Centro', $result['data'][0]['description']);
        $this->assertSame('Ana', $result['data'][0]['user_name']);
        $this->assertSame(7, $result['data'][0]['user_id']);
        $this->assertSame('home', $result['data'][0]['icon']);
        $this->assertSame('#3b82f6', $result['data'][0]['color']);
        $this->assertSame($time, $result['data'][0]['timestamp']);
        $this->assertSame('Agora mesmo', $result['data'][0]['time_ago']);
    }

    public function testEndpointUsesCanonicalAuthContextForActorIdentity(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/getActivityFeed.php');

        $this->assertStringContainsString('AuthContext::getInstance()', $endpoint);
        $this->assertStringNotContainsString('$_SESSION[\'user_id\']', $endpoint);
        $this->assertStringNotContainsString('$_SESSION["user_id"]', $endpoint);
    }

    public function testEndpointDoesNotContainExecutableSqlAndKeepsTenantGuardrails(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/getActivityFeed.php');

        $this->assertStringNotContainsString('UNION ALL', $endpoint);
        $this->assertStringNotContainsString('SELECT ', $endpoint);
        $this->assertStringContainsString('getActivityFeed(', $endpoint);
        $this->assertStringContainsString('TenantMiddleware::resolveTenantId()', $endpoint);
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($endpoint));
    }

    public function testRepositoryPreservesUnionSourcesPaginationAndContractShape(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/Repository/ReportRepository.php');

        $this->assertStringContainsString('FROM properties p', $source);
        $this->assertStringContainsString('FROM leads l', $source);
        $this->assertStringContainsString('FROM property_sales ps', $source);
        $this->assertStringContainsString('FROM agendamentos a', $source);
        $this->assertStringContainsString('UNION ALL', $source);
        $this->assertStringContainsString('ORDER BY timestamp DESC', $source);
        $this->assertStringContainsString('LIMIT ?', $source);
        $this->assertStringContainsString('OFFSET ?', $source);
        $this->assertStringContainsString('COUNT(*) as total', $source);
        $this->assertStringContainsString('tenant_id = ?', $source);
    }
}
