<?php

namespace Tests\Unit;

use App\Repository\ReportRepository;
use App\Service\ReportService;
use PHPUnit\Framework\TestCase;

class PortfolioStatsMigrationContractTest extends TestCase
{
    public function testServiceAndRepositoryExposePortfolioStatsContract(): void
    {
        $this->assertTrue(method_exists(ReportService::class, 'getPortfolioStats'));
        $this->assertTrue(method_exists(ReportRepository::class, 'getPortfolioStats'));
    }

    public function testPortfolioEndpointUsesServiceLayerAndNotInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/getPortfolioStats.php');

        $this->assertStringContainsString('ReportService', $endpoint);
        $this->assertStringContainsString('new \\App\\Service\\ReportService', $endpoint);
        $this->assertStringContainsString('getPortfolioStats(', $endpoint);
        $this->assertStringNotContainsString('SELECT ', $endpoint);
        $this->assertStringNotContainsString('GROUP BY neighborhood', strtolower($endpoint));
    }
}
