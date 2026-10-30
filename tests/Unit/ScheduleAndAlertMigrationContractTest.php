<?php

namespace Tests\Unit;

use App\Service\AgendamentoService;
use App\Services\AlertConfigService;
use PHPUnit\Framework\TestCase;

class ScheduleAndAlertMigrationContractTest extends TestCase
{
    public function testAgendamentoServiceExposesTodayScheduleContract(): void
    {
        $this->assertTrue(method_exists(AgendamentoService::class, 'getTodayScheduleSummary'));
    }

    public function testTodayScheduleEndpointUsesServiceLayerAndNotInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/getTodaySchedule.php');

        $this->assertStringContainsString('AgendamentoService', $endpoint);
        $this->assertStringContainsString('new \\App\\Service\\AgendamentoService', $endpoint);
        $this->assertStringContainsString('getTodayScheduleSummary(', $endpoint);
        $this->assertStringNotContainsString('SELECT ', $endpoint);
    }

    public function testAlertConfigEndpointUsesServiceLayerAndNotInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/configureAlerts.php');

        $this->assertStringContainsString('AlertConfigService', $endpoint);
        $this->assertStringContainsString('new \\App\\Services\\AlertConfigService', $endpoint);
        $this->assertStringContainsString('saveConfig(', $endpoint);
        $this->assertStringNotContainsString('SELECT id FROM alerts_config', $endpoint);
        $this->assertStringNotContainsString('INSERT INTO alerts_config', $endpoint);
        $this->assertStringNotContainsString('UPDATE alerts_config', $endpoint);
    }

    public function testTodayScheduleQueryUsesDistinctNamedParameters(): void
    {
        $repository = file_get_contents(__DIR__ . '/../../src/Infrastructure/Agendamento/AgendamentoRepository.php');
        $endpoint = file_get_contents(__DIR__ . '/../../api/relatorios/getTodaySchedule.php');

        $this->assertStringContainsString('checkAuth();', $endpoint);
        $this->assertStringContainsString('checkApiAuth();', $endpoint);
        $this->assertStringContainsString('p.tenant_id = :tenant_id_property', $repository);
        $this->assertStringContainsString('u.tenant_id = :tenant_id_user', $repository);
        $this->assertStringContainsString('a.tenant_id = :tenant_id_agendamento', $repository);
        $this->assertStringNotContainsString(':tenant_id = :tenant_id', $repository);
    }
}
