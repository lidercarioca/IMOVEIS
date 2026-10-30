<?php

namespace Tests\Unit;

use App\Repository\ReportRepository;
use App\Service\ReportService;
use PHPUnit\Framework\TestCase;

class ReportDashboardChartsContractTest extends TestCase
{
    public function testGetDashboardChartsReturnsFrontendCompatiblePayload(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $repository = $this->createMock(ReportRepository::class);

        $repository->expects($this->once())
            ->method('getDashboardCharts')
            ->with($this->callback(function (array $filters) {
                $this->assertSame(42, (int)($filters['tenantId'] ?? 0));
                $this->assertSame('all', (string)($filters['type'] ?? ''));
                $this->assertNotEmpty($filters['dataInicio']);
                $this->assertNotEmpty($filters['dataFim']);
                return true;
            }))
            ->willReturn([
                'sales_trend' => [
                    ['data' => '2026-09-01', 'vendas' => 2, 'receita' => 1500.00],
                    ['data' => '2026-09-02', 'vendas' => 5, 'receita' => 2500.00],
                ],
                'lead_funnel' => [
                    ['stage' => 'Leads', 'quantidade' => 12],
                ],
                'category_performance' => [
                    ['categoria' => 'apartment', 'total_imoveis' => 3, 'visitas' => 7, 'vendas' => 2, 'receita' => 3000.00],
                ],
                'neighborhood_sales' => [
                    ['bairro' => 'Centro', 'vendas' => 4, 'receita' => 8000.00, 'ticket_medio' => 2000.00],
                ],
            ]);

        $service = new ReportService($pdo, $repository);

        $result = $service->getDashboardCharts([
            'tenantId' => 42,
            'period' => 30,
            'type' => 'all',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('charts', $result['data']);
        $this->assertArrayHasKey('sales_trend', $result['data']['charts']);
        $this->assertArrayHasKey('lead_funnel', $result['data']['charts']);
        $this->assertArrayHasKey('category_performance', $result['data']['charts']);
        $this->assertArrayHasKey('neighborhood_sales', $result['data']['charts']);
        $this->assertSame(2, count($result['data']['charts']['sales_trend']['data']));
    }

    public function testGetDashboardChartsRejectsInvalidTenant(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $repository = $this->createMock(ReportRepository::class);
        $service = new ReportService($pdo, $repository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant inválido');

        $service->getDashboardCharts([
            'tenantId' => 0,
            'period' => 30,
            'type' => 'all',
        ]);
    }
}
