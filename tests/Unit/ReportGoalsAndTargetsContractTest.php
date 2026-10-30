<?php

namespace Tests\Unit;

use App\Repository\GoalConfigRepository;
use App\Repository\ReportRepository;
use App\Service\GoalConfigService;
use App\Service\ReportService;
use PDO;
use PHPUnit\Framework\TestCase;

class ReportGoalsAndTargetsContractTest extends TestCase
{
    public function testServiceAndRepositoryExposeGoalsAndTargetsContract(): void
    {
        $this->assertTrue(method_exists(ReportService::class, 'getGoalsAndTargets'));
        $this->assertTrue(method_exists(ReportRepository::class, 'getGoalsAndTargets'));
    }

    public function testServicePreservesGoalsAndTargetsResponseContract(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = $this->createMock(ReportRepository::class);

        $repository->method('getGoalsAndTargets')->willReturn([
            'goalsConfig' => [
                'leads' => ['goal_id' => 'leads', 'goal_name' => 'Leads Gerados', 'target_value' => 50, 'period_days' => 30],
                'sales' => ['goal_id' => 'sales', 'goal_name' => 'Vendas/Aluguéis', 'target_value' => 5, 'period_days' => 30],
                'revenue' => ['goal_id' => 'revenue', 'goal_name' => 'Receita Total', 'target_value' => 500000, 'period_days' => 30],
                'conversion' => ['goal_id' => 'conversion', 'goal_name' => 'Taxa de Conversão', 'target_value' => 20, 'period_days' => 30],
                'properties' => ['goal_id' => 'properties', 'goal_name' => 'Imóveis Listados', 'target_value' => 20, 'period_days' => 30],
            ],
            'data' => [
                'leads' => ['real' => 10, 'target' => 50, 'percent' => 20, 'status' => 'at_risk'],
                'sales' => ['real' => 3, 'target' => 5, 'percent' => 60, 'status' => 'at_risk'],
                'revenue' => ['real' => 100000, 'target' => 500000, 'percent' => 20, 'status' => 'at_risk', 'real_formatted' => 'R$ 100.000', 'target_formatted' => 'R$ 500.000'],
                'conversion' => ['real' => 30.0, 'target' => 20, 'percent' => 100, 'status' => 'completed'],
                'properties' => ['real' => 15, 'target' => 20, 'percent' => 75, 'status' => 'on_track'],
            ],
            'date_range' => ['start' => '2026-08-26', 'end' => '2026-09-25'],
        ]);

        $service = new ReportService($pdo, $repository);
        $result = $service->getGoalsAndTargets(42, 30, false, 7);

        $this->assertTrue($result['success']);
        $this->assertSame(30, $result['period']);
        $this->assertSame('2026-08-26', $result['date_range']['start']);
        $this->assertSame('2026-09-25', $result['date_range']['end']);
        $this->assertCount(5, $result['goals']);
        $this->assertSame('leads', $result['goals'][0]['id']);
        $this->assertSame('sales', $result['goals'][1]['id']);
        $this->assertSame('revenue', $result['goals'][2]['id']);
        $this->assertSame('conversion', $result['goals'][3]['id']);
        $this->assertSame('properties', $result['goals'][4]['id']);
        $this->assertSame(5, $result['overall']['completed_goals'] + $result['overall']['on_track_goals'] + $result['overall']['at_risk_goals']);
        $this->assertSame('at_risk', $result['overall']['status']);
    }

    public function testEndpointDoesNotContainExecutableSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/getGoalsAndTargets.php');

        $this->assertStringContainsString('AuthMiddleware', $endpoint);
        $this->assertStringContainsString('AuthMiddleware::handle', $endpoint);
        $this->assertStringContainsString('TenantMiddleware', $endpoint);
        $this->assertStringContainsString('resolveTenantId', $endpoint);
        $this->assertStringContainsString('reportService', $endpoint);
        $this->assertStringNotContainsString('SELECT ', $endpoint);
        $this->assertStringNotContainsString('UNION ALL', $endpoint);
        $this->assertStringNotContainsString('tenant_id = 1', strtolower($endpoint));
    }

    public function testGoalConfigServiceAndRepositoryExposeApiContract(): void
    {
        $this->assertTrue(method_exists(GoalConfigService::class, 'listGoals'));
        $this->assertTrue(method_exists(GoalConfigService::class, 'saveGoal'));
        $this->assertTrue(method_exists(GoalConfigRepository::class, 'listGoals'));
        $this->assertTrue(method_exists(GoalConfigRepository::class, 'upsertGoal'));
    }

    public function testGoalConfigEndpointUsesServiceLayerAndNotInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/v1/goals/getGoalsConfig.php');

        $this->assertStringContainsString('GoalConfigService', $endpoint);
        $this->assertStringContainsString('new GoalConfigService', $endpoint);
        $this->assertStringContainsString('listGoals(', $endpoint);
        $this->assertStringContainsString('saveGoal(', $endpoint);
        $this->assertStringNotContainsString('SELECT id, goal_id, goal_name, target_value, period_days, is_active', $endpoint);
        $this->assertStringNotContainsString('INSERT INTO goals_config', $endpoint);
    }

    public function testRepositoryPreservesGoalQueriesAndTenantFilters(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/Repository/ReportRepository.php');

        $this->assertStringContainsString('goals_config', $source);
        $this->assertStringContainsString('FROM leads', $source);
        $this->assertStringContainsString('FROM property_sales', $source);
        $this->assertStringContainsString('FROM properties', $source);
        $this->assertStringContainsString('COUNT(*) as total', $source);
        $this->assertStringContainsString('COUNT(DISTINCT l.id)', $source);
        $this->assertStringContainsString('COUNT(DISTINCT ps.id)', $source);
        $this->assertStringContainsString('tenant_id = ?', $source);
        $this->assertStringContainsString('assigned_user_id = ?', $source);
        $this->assertStringContainsString('user_id = ?', $source);
    }
}
