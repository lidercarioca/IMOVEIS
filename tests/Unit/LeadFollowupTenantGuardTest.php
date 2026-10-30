<?php

namespace Tests\Unit;

use App\Services\LeadFollowupService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LeadFollowupTenantGuardTest extends TestCase
{
    public function testLeadFollowupServiceRequiresExplicitTenant(): void
    {
        $method = new ReflectionMethod(LeadFollowupService::class, '__construct');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame('tenantId', $parameters[1]->getName());
        $this->assertFalse($parameters[1]->isDefaultValueAvailable());
        $this->assertSame('int', (string) $parameters[1]->getType());
    }

    public function testLeadFollowupEndpointsRejectTenantOneFallbacks(): void
    {
        $files = [
            __DIR__ . '/../../api/v1/leads/followup/schedule.php',
            __DIR__ . '/../../api/v1/alertas/getAlertConfigs.php',
            __DIR__ . '/../../api/v1/alertas/getInactivityAlerts.php',
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertIsString($content);

            $this->assertStringNotContainsString("\TenantContext::getTenantId() ?? 1", $content);
            $this->assertStringNotContainsString("\$_SESSION['tenant_id'] ?? 1", $content);
            $this->assertStringNotContainsString("\$tenantId = 1;", $content);
        }

        $schedule = file_get_contents(__DIR__ . '/../../api/v1/leads/followup/schedule.php');
        $this->assertStringContainsString('getRequestTenantId()', $schedule);

        $configs = file_get_contents(__DIR__ . '/../../api/v1/alertas/getAlertConfigs.php');
        $this->assertStringContainsString('getRequestTenantId()', $configs);

        $inactivity = file_get_contents(__DIR__ . '/../../api/v1/alertas/getInactivityAlerts.php');
        $this->assertStringContainsString('getRequestTenantId()', $inactivity);
    }
}
