<?php

namespace Tests\Unit;

use App\Service\ReportService;
use PDO;
use PHPUnit\Framework\TestCase;

class ReportDashboardMetricsContractTest extends TestCase
{
    public function testGetFilteredDashboardKPIs_preserves_contract_and_filters(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getFilteredDashboardKPIs')
            ->with(42, 7, 9, 'apartamento', 'Centro')
            ->willReturn([
                'success' => true,
                'filters' => [
                    'period' => 7,
                    'user_id' => 9,
                    'category' => 'apartamento',
                    'region' => 'Centro',
                ],
                'date_range' => ['start' => '2026-09-18', 'end' => '2026-09-25'],
                'kpis' => [
                    'total_leads' => 18,
                    'total_sales' => 5,
                    'conversion_rate' => 27.78,
                    'average_ticket' => 1250.0,
                    'total_revenue' => 6250.0,
                ],
                'top_brokers' => [
                    ['id' => 1, 'name' => 'Ana', 'vendas' => 3, 'receita' => 3500.0, 'leads' => 10, 'taxa_conversao' => 30.0],
                ],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getFilteredDashboardKPIs(42, 7, 9, 'apartamento', 'Centro');

        $this->assertTrue($result['success']);
        $this->assertSame(7, $result['filters']['period']);
        $this->assertSame(9, $result['filters']['user_id']);
        $this->assertSame('apartamento', $result['filters']['category']);
        $this->assertSame('Centro', $result['filters']['region']);
        $this->assertSame('2026-09-18', $result['date_range']['start']);
        $this->assertSame('2026-09-25', $result['date_range']['end']);
        $this->assertSame(18, $result['kpis']['total_leads']);
        $this->assertSame(5, $result['kpis']['total_sales']);
        $this->assertSame(27.78, $result['kpis']['conversion_rate']);
        $this->assertSame(1250.0, $result['kpis']['average_ticket']);
        $this->assertSame('Ana', $result['top_brokers'][0]['name']);
    }

    public function testDashboardEndpointUsesCanonicalAuthContextForActorIdentity(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/getDashboardKPIs.php');

        $this->assertStringContainsString('AuthContext::getInstance()', $endpoint);
        $this->assertStringNotContainsString('$_SESSION[\'user_id\']', $endpoint);
        $this->assertStringNotContainsString('$_SESSION["user_id"]', $endpoint);
    }

    public function testGetBrokerRanking_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getBrokerRanking')
            ->with(42)
            ->willReturn([
                ['label' => 'Ana', 'vendas' => 8, 'valor' => 12000.5],
                ['label' => 'Beto', 'vendas' => 5, 'valor' => 9500.0],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getBrokerRanking(42);

        $this->assertTrue($result['success']);
        $this->assertSame('Ana', $result['data'][0]['label']);
        $this->assertSame(8, $result['data'][0]['vendas']);
        $this->assertSame(12000.5, $result['data'][0]['valor']);
    }

    public function testGetBrokerConversionRate_preserves_contract_and_zero_safe_logic(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getBrokerConversionRate')
            ->with(42)
            ->willReturn([
                ['label' => 'Ana', 'taxa' => 60.0, 'leads' => 10, 'vendas' => 6],
                ['label' => 'Beto', 'taxa' => 0.0, 'leads' => 0, 'vendas' => 0],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getBrokerConversionRate(42);

        $this->assertTrue($result['success']);
        $this->assertSame('Ana', $result['data'][0]['label']);
        $this->assertSame(60.0, $result['data'][0]['taxa']);
        $this->assertSame(10, $result['data'][0]['leads']);
        $this->assertSame(6, $result['data'][0]['vendas']);
        $this->assertSame(0.0, $result['data'][1]['taxa']);
    }

    public function testGetAverageTicket_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getAverageTicket')
            ->with(42, 'mes', '2026-09-01', '2026-09-30')
            ->willReturn([
                ['label' => 'Ticket Médio', 'valor' => 1850.5, 'count' => 12],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getAverageTicket(42, 'mes', '2026-09-01', '2026-09-30');

        $this->assertTrue($result['success']);
        $this->assertSame('Ticket Médio', $result['data'][0]['label']);
        $this->assertSame(1850.5, $result['data'][0]['valor']);
        $this->assertSame(12, $result['data'][0]['count']);
    }
}
