<?php

namespace Tests\Unit;

use App\Service\ReportService;
use PDO;
use PHPUnit\Framework\TestCase;

class ReportLeadsSalesContractTest extends TestCase
{
    public function testGetLeadsByStatus_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getLeadsByStatus')
            ->with(42, 'new', 'mes')
            ->willReturn([
                ['status' => 'Novo', 'count' => 8, 'quantidade' => 8],
                ['status' => 'Fechado', 'count' => 3, 'quantidade' => 3],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getLeadsByStatus(42, 'new', 'mes');

        $this->assertTrue($result['success']);
        $this->assertSame('Novo', $result['data'][0]['status']);
        $this->assertSame(8, $result['data'][0]['count']);
        $this->assertSame(8, $result['data'][0]['quantidade']);
    }

    public function testGetLeadsByOrigin_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getLeadsByOrigin')
            ->with(42, '')
            ->willReturn([
                ['source' => 'Site', 'count' => 10, 'quantidade' => 10],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getLeadsByOrigin(42, '');

        $this->assertTrue($result['success']);
        $this->assertSame('Site', $result['data'][0]['source']);
        $this->assertSame(10, $result['data'][0]['count']);
        $this->assertSame(10, $result['data'][0]['quantidade']);
    }

    public function testGetLeadsByBroker_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getLeadsByBroker')
            ->with(42)
            ->willReturn([
                ['label' => 'Ana', 'count' => 5],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getLeadsByBroker(42);

        $this->assertTrue($result['success']);
        $this->assertSame('Ana', $result['data'][0]['label']);
        $this->assertSame(5, $result['data'][0]['count']);
    }

    public function testGetLeadsAverageTime_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getLeadsAverageTime')
            ->with(42)
            ->willReturn([
                ['label' => 'Tempo Médio', 'dias' => 12],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getLeadsAverageTime(42);

        $this->assertTrue($result['success']);
        $this->assertSame('Tempo Médio', $result['data'][0]['label']);
        $this->assertSame(12, $result['data'][0]['dias']);
    }

    public function testGetLeadsConversionRate_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getLeadsConversionRate')
            ->with(42)
            ->willReturn([
                ['label' => 'Corretor 1', 'taxa' => 25.0, 'total' => 10, 'vendas' => 2.5],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getLeadsConversionRate(42);

        $this->assertTrue($result['success']);
        $this->assertSame('Corretor 1', $result['data'][0]['label']);
        $this->assertSame(25.0, $result['data'][0]['taxa']);
    }

    public function testGetLeadsFunnel_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $expected = [
            'success' => true,
            'funnel' => [
                ['status' => 'new', 'label' => 'Novo', 'total' => 10],
            ],
            'summary' => [
                'total_leads' => 10,
                'conversion_rate' => 20.0,
            ],
            'temporal' => [],
            'cycle_time' => [],
            'sources' => [],
        ];

        $repository->expects($this->once())
            ->method('getLeadsFunnel')
            ->with(42, 7, null, false)
            ->willReturn($expected);

        $service = new ReportService($pdo, $repository);
        $result = $service->getLeadsFunnel(42, 7, null, false);

        $this->assertTrue($result['success']);
        $this->assertSame('Novo', $result['funnel'][0]['label']);
        $this->assertSame(10, $result['summary']['total_leads']);
    }

    public function testGetLeadsLost_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getLeadsLost')
            ->with(42)
            ->willReturn([
                ['label' => 'Leads Perdidos', 'count' => 3],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getLeadsLost(42);

        $this->assertTrue($result['success']);
        $this->assertSame('Leads Perdidos', $result['data'][0]['label']);
        $this->assertSame(3, $result['data'][0]['count']);
    }

    public function testGetSalesByBroker_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getSalesByBroker')
            ->with(42)
            ->willReturn([
                ['label' => 'Ana', 'count' => 2, 'valor' => 2000.0],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getSalesByBroker(42);

        $this->assertTrue($result['success']);
        $this->assertSame('Ana', $result['data'][0]['label']);
        $this->assertSame(2, $result['data'][0]['count']);
    }

    public function testGetSalesByPeriod_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getSalesByPeriod')
            ->with(42, 'mes', '2026-09-01', '2026-09-30')
            ->willReturn([
                ['label' => '2026-09', 'count' => 5, 'valor' => 5100.0],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getSalesByPeriod(42, 'mes', '2026-09-01', '2026-09-30');

        $this->assertTrue($result['success']);
        $this->assertSame('2026-09', $result['data'][0]['label']);
        $this->assertSame(5, $result['data'][0]['count']);
    }

    public function testGetSalesByType_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getSalesByType')
            ->with(42, 'mes', '2026-09-01', '2026-09-30')
            ->willReturn([
                ['label' => 'Apartamento', 'count' => 4, 'valor' => 4050.0],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getSalesByType(42, 'mes', '2026-09-01', '2026-09-30');

        $this->assertTrue($result['success']);
        $this->assertSame('Apartamento', $result['data'][0]['label']);
        $this->assertSame(4, $result['data'][0]['count']);
    }

    public function testGetSalesCycle_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getSalesCycle')
            ->with(42, 'mes', '2026-09-01', '2026-09-30')
            ->willReturn([
                ['label' => 'Ciclo Médio', 'dias' => 18],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getSalesCycle(42, 'mes', '2026-09-01', '2026-09-30');

        $this->assertTrue($result['success']);
        $this->assertSame('Ciclo Médio', $result['data'][0]['label']);
        $this->assertSame(18, $result['data'][0]['dias']);
    }

    public function testGetSalesPipeline_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $expected = [
            'success' => true,
            'data' => [
                'items' => [],
                'by_stage' => [],
                'totals' => ['total_value' => 0, 'total_items' => 0, 'stages' => []],
                'generated_at' => '2026-09-25 00:00:00',
            ],
        ];

        $repository->expects($this->once())
            ->method('getSalesPipeline')
            ->with(42)
            ->willReturn($expected['data']);

        $service = new ReportService($pdo, $repository);
        $result = $service->getSalesPipeline(42);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data']['totals']['total_items']);
        $this->assertSame('2026-09-25 00:00:00', $result['data']['generated_at']);
    }

    public function testGetRevenueAndCommissions_preserves_contract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->expects($this->once())
            ->method('getRevenueAndCommissions')
            ->with(42, 'mes', '2026-09-01', '2026-09-30')
            ->willReturn([
                ['label' => 'Receita Total', 'valor' => 5100.0, 'vendas' => 5],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getRevenueAndCommissions(42, 'mes', '2026-09-01', '2026-09-30');

        $this->assertTrue($result['success']);
        $this->assertSame('Receita Total', $result['data'][0]['label']);
        $this->assertSame(5100.0, $result['data'][0]['valor']);
        $this->assertSame(5, $result['data'][0]['vendas']);
    }
}
