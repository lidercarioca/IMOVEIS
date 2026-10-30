<?php

namespace Tests\Unit;

use App\Repository\SecurityMonitoringRepository;
use App\Service\SecurityMonitoringService;
use PHPUnit\Framework\TestCase;

class SecurityMonitoringMigrationContractTest extends TestCase
{
    public function testSecurityMonitoringServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(SecurityMonitoringService::class));
        $this->assertTrue(class_exists(SecurityMonitoringRepository::class));
        $this->assertTrue(method_exists(SecurityMonitoringService::class, 'getOverview'));
        $this->assertTrue(method_exists(SecurityMonitoringService::class, 'blockIp'));
        $this->assertTrue(method_exists(SecurityMonitoringService::class, 'unblockIp'));
        $this->assertTrue(method_exists(SecurityMonitoringService::class, 'resolveAnomaly'));
    }

    public function testSecurityMonitoringEndpointsUseServiceInsteadOfRawSql(): void
    {
        $monitoring = file_get_contents(__DIR__ . '/../../api/v1/debug/getSecurityMonitoring.php');
        $blockIp = file_get_contents(__DIR__ . '/../../api/v1/debug/blockIP.php');
        $unblockIp = file_get_contents(__DIR__ . '/../../api/v1/debug/unblockIP.php');
        $resolveAnomaly = file_get_contents(__DIR__ . '/../../api/v1/debug/resolveAnomaly.php');

        $this->assertTrue(
            str_contains($monitoring, 'new SecurityMonitoringService') || str_contains($monitoring, 'new \\App\\Service\\SecurityMonitoringService')
        );
        $this->assertTrue(
            str_contains($blockIp, 'new SecurityMonitoringService') || str_contains($blockIp, 'new \\App\\Service\\SecurityMonitoringService')
        );
        $this->assertTrue(
            str_contains($unblockIp, 'new SecurityMonitoringService') || str_contains($unblockIp, 'new \\App\\Service\\SecurityMonitoringService')
        );
        $this->assertTrue(
            str_contains($resolveAnomaly, 'new SecurityMonitoringService') || str_contains($resolveAnomaly, 'new \\App\\Service\\SecurityMonitoringService')
        );

        $this->assertStringNotContainsString('SELECT COUNT(*) as count FROM security_anomalies', strtolower($monitoring));
        $this->assertStringNotContainsString('SELECT COUNT(*) as count FROM blocked_ips', strtolower($monitoring));
        $this->assertStringNotContainsString('SELECT id FROM blocked_ips WHERE ip_address = ?', strtolower($blockIp));
        $this->assertStringNotContainsString('UPDATE blocked_ips SET blocked_until = NOW(), permanent = 0 WHERE ip_address = ?', strtolower($unblockIp));
        $this->assertStringNotContainsString('SELECT id, severity, anomaly_type FROM security_anomalies', strtolower($resolveAnomaly));
        $this->assertStringNotContainsString('UPDATE security_anomalies SET resolved = 1', strtolower($resolveAnomaly));
    }
}
