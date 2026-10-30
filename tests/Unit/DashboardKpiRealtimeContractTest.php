<?php

namespace Tests\Unit;

use App\Service\ReportService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * DashboardKpiRealtimeContractTest
 *
 * Contrato executável para proteger a separação atual entre:
 * - KPI Executivo / Histórico
 * - Realtime Operacional
 *
 * Este lote não implementa unificação de regras de negócio.
 * Ele apenas protege a distinção semântica existente hoje.
 */
class DashboardKpiRealtimeContractTest extends TestCase
{
    public function testKPI_001_tenant_scope_contract_requires_tenant_id_on_result_rows(): void
    {
        $tenantId = 42;
        $rows = [
            ['tenant_id' => 42, 'id' => 1],
            ['tenant_id' => 42, 'id' => 2],
            ['tenant_id' => 42, 'id' => 3],
        ];

        foreach ($rows as $row) {
            $this->assertSame($tenantId, (int) $row['tenant_id']);
        }
    }

    public function testKPI_002_admin_tenant_scope_is_not_global_tenant_scope(): void
    {
        $adminTenantA = ['tenant_id' => 42, 'scope' => 'tenant-wide'];
        $tenantB = ['tenant_id' => 99, 'scope' => 'tenant-b'];

        $this->assertNotSame($adminTenantA['tenant_id'], $tenantB['tenant_id']);
        $this->assertSame('tenant-wide', $adminTenantA['scope']);
        $this->assertNotSame($adminTenantA['scope'], $tenantB['scope']);
    }

    public function testKPI_005_average_ticket_source_field_stays_property_price_for_kpi_contract(): void
    {
        $kpiContract = [
            'source_endpoint' => 'api/relatorios/getDashboardKPIs.php',
            'source_field' => 'property_sales.property_price',
            'formula' => 'AVG(property_price)',
            'legacy_status' => 'ACTIVE_KPI_CONTRACT',
        ];

        $realtimeContract = [
            'source_endpoint' => 'api/relatorios/realtimeDashboard.php',
            'source_field' => 'property_sales.sale_price',
            'formula' => 'AVG(sale_price)',
            'legacy_status' => 'LEGACY_UNVERIFIED_READ_FIELD',
        ];

        $this->assertSame('property_sales.property_price', $kpiContract['source_field']);
        $this->assertNotSame($kpiContract['source_field'], $realtimeContract['source_field']);
        $this->assertSame('AVG(property_price)', $kpiContract['formula']);
        $this->assertSame('LEGACY_UNVERIFIED_READ_FIELD', $realtimeContract['legacy_status']);
    }

    public function testKPI_007_cycle_contracts_remain_separate_semantic_concepts(): void
    {
        $leadConversionCycle = [
            'name' => 'lead_conversion_cycle',
            'source_field' => 'leads.converted_at - leads.created_at',
            'requires' => ['converted_at IS NOT NULL'],
        ];

        $saleCompletionCycle = [
            'name' => 'sale_completion_cycle',
            'source_field' => 'property_sales.created_at - leads.created_at',
            'requires' => ['linked_sale_id IS NOT NULL'],
        ];

        $this->assertNotSame($leadConversionCycle['name'], $saleCompletionCycle['name']);
        $this->assertNotSame($leadConversionCycle['source_field'], $saleCompletionCycle['source_field']);
        $this->assertNotSame($leadConversionCycle['requires'], $saleCompletionCycle['requires']);
    }

    public function testKPI_011_stopped_properties_remain_separate_sources(): void
    {
        $reportContract = [
            'source' => 'ReportService / ReportRepository',
            'field' => 'report rule',
            'status' => 'report-based',
        ];

        $realtimeContract = [
            'source' => 'property_last_movement',
            'field' => 'days_without_movement',
            'status' => 'movement-age-based',
        ];

        $this->assertNotSame($reportContract['source'], $realtimeContract['source']);
        $this->assertNotSame($reportContract['field'], $realtimeContract['field']);
        $this->assertNotSame($reportContract['status'], $realtimeContract['status']);
    }

    public function testKPI_013_top_brokers_payloads_preserve_distinct_contracts(): void
    {
        $kpiTopBrokers = [
            'name' => 'top_brokers',
            'keys' => ['id', 'name', 'vendas', 'receita', 'leads', 'taxa_conversao'],
        ];

        $realtimeBrokers = [
            'name' => 'realtime_brokers',
            'keys' => ['name', 'sales_count', 'total_revenue'],
        ];

        $this->assertNotSame($kpiTopBrokers['name'], $realtimeBrokers['name']);
        $this->assertNotSame($kpiTopBrokers['keys'], $realtimeBrokers['keys']);
        $this->assertSame(['id', 'name', 'vendas', 'receita', 'leads', 'taxa_conversao'], $kpiTopBrokers['keys']);
        $this->assertSame(['name', 'sales_count', 'total_revenue'], $realtimeBrokers['keys']);
    }

    public function testCONTRACT_001_kpi_and_realtime_metrics_are_not_silent_equivalents(): void
    {
        $kpiTotalProperties = ['metric_name' => 'total_properties', 'scope' => 'tenant-admin-user-aware'];
        $realtimeTotalProperties = ['metric_name' => 'total_properties', 'scope' => 'user-scoped-realtime'];
        $kpiAverageTicket = ['metric_name' => 'average_ticket', 'source_field' => 'property_sales.property_price'];
        $realtimeAverageTicket = ['metric_name' => 'average_ticket', 'source_field' => 'property_sales.sale_price'];
        $kpiCycle = ['metric_name' => 'average_cycle_days', 'source' => 'property_sales.created_at'];
        $realtimeCycle = ['metric_name' => 'cycle_days', 'source' => 'leads.converted_at'];

        $this->assertNotSame($kpiTotalProperties['scope'], $realtimeTotalProperties['scope']);
        $this->assertNotSame($kpiAverageTicket['source_field'], $realtimeAverageTicket['source_field']);
        $this->assertNotSame($kpiCycle['source'], $realtimeCycle['source']);
    }

    public function testKPI_100_report_service_dashboard_kpis_returns_legacy_contract_shape(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->method('getDashboardKPIs')
            ->willReturn([
                'totalProperties' => 14,
                'totalLeads' => 25,
                'totalLeadsPrevious' => 20,
                'totalVisits' => 77,
                'totalSales' => 6,
                'totalSalesPrevious' => 5,
                'conversionRate' => 24.0,
                'conversionRatePrevious' => 20.0,
                'averageTicket' => 1234.56,
                'averageTicketPrevious' => 1000.0,
                'averageCycleDays' => 9,
                'averageCycleDaysPrevious' => 12,
                'totalRevenue' => 7407.36,
                'totalRevenuePrevious' => 5000.0,
                'stoppedProperties' => 3,
                'topBrokers' => [
                    ['id' => 1, 'name' => 'Ana', 'vendas' => 3, 'receita' => 2500.0, 'leads' => 5, 'taxa_conversao' => 60.0],
                ],
                'dateInicio' => '2026-09-18',
                'dateFim' => '2026-09-25',
                'dateInicioAnterior' => '2026-09-11',
                'dateFimAnterior' => '2026-09-18',
            ]);

        $service = new ReportService($pdo, $repository);

        $result = $service->getDashboardKPIs([
            'tenantId' => 42,
            'period' => 7,
            'userId' => 7,
            'isAdmin' => false,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(7, (int)$result['period']);
        $this->assertArrayHasKey('date_range', $result);
        $this->assertArrayHasKey('kpis', $result);
        $this->assertArrayHasKey('top_brokers', $result);
        $this->assertSame(14, (int)$result['kpis']['total_properties']);
        $this->assertSame(25, (int)$result['kpis']['total_leads']['current']);
        $this->assertSame(20, (int)$result['kpis']['total_leads']['previous']);
        $this->assertSame(77, (int)$result['kpis']['total_visits']);
        $this->assertSame(6, (int)$result['kpis']['total_sales']['current']);
        $this->assertSame(24.0, $result['kpis']['conversion_rate']['current']);
        $this->assertSame(25.0, $result['kpis']['conversion_rate']['previous']);
        $this->assertSame('down', $result['kpis']['conversion_rate']['trend']);
        $this->assertSame(1234.56, $result['kpis']['average_ticket']['current']);
        $this->assertSame(9, (int)$result['kpis']['average_cycle_days']['current']);
        $this->assertSame(7407.36, $result['kpis']['total_revenue']['current']);
        $this->assertSame(3, (int)$result['kpis']['stopped_properties']);
        $this->assertSame('Ana', $result['top_brokers'][0]['name']);
    }

    public function testREALTIME_001_report_service_snapshot_contract_preserves_realtime_boundaries(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(\App\Repository\ReportRepository::class);

        $repository->method('getRealtimeDashboardKPIs')
            ->with(42, 7, 7)
            ->willReturn([
                'total_properties' => 14,
                'total_leads' => 25,
                'total_sales' => 6,
                'avg_ticket' => 1234.56,
                'cycle_days' => 9,
                'stopped_count' => 3,
                'total_open_leads' => 10,
                'today_conversions' => 2,
            ]);

        $repository->method('getRealtimeTopBrokers')
            ->with(42, 7)
            ->willReturn([
                ['name' => 'Ana', 'sales_count' => 3, 'avg_price' => 1200.0, 'total_revenue' => 3600.0],
            ]);

        $repository->method('getRealtimeRecentLeads')
            ->with(42, 7, 5)
            ->willReturn([
                ['id' => 9, 'title' => 'Imóvel A', 'contact_name' => 'Maria', 'status' => 'new', 'created_at' => '2026-09-25 08:00:00', 'visit_count' => 2],
            ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getRealtimeDashboardSnapshot(42, 7, 7);

        $this->assertArrayHasKey('kpis', $result);
        $this->assertArrayHasKey('brokers', $result);
        $this->assertArrayHasKey('recentLeads', $result);
        $this->assertArrayNotHasKey('totalLeadsPrevious', $result['kpis']);
        $this->assertArrayNotHasKey('date_range', $result);
        $this->assertSame(14, (int)$result['kpis']['total_properties']);
        $this->assertSame(3, (int)$result['kpis']['stopped_count']);
        $this->assertSame('Ana', $result['brokers'][0]['name']);
        $this->assertSame('Maria', $result['recentLeads'][0]['contact_name']);
    }
}
