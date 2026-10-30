<?php

namespace Tests\Webhooks;

use PHPUnit\Framework\TestCase;

/**
 * ReplayProtectionTest converted to PHPUnit TestCase.
 *
 * By default these are marked as skipped to avoid requiring a configured
 * database or external environment. Set the env var RUN_INTEGRATION_TESTS=1
 * to run the original integration checks.
 */
class ReplayProtectionTest extends TestCase
{
    public function testReplayProtectionIntegration()
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Integration test — set RUN_INTEGRATION_TESTS=1 to enable');
            return;
        }

        require_once __DIR__ . '/../../config/database.php';
        require_once __DIR__ . '/../../vendor/autoload.php';
        require_once __DIR__ . '/../../src/Application/Integration/OLXWebhookService.php';

        $payloadRaw = json_encode([
            'property_id' => 1,
            'name' => 'Replay Protection Test',
            'email' => 'replay-test@example.com',
            'phone' => '11999999977',
            'message' => 'Replay protection validation payload ' . uniqid('', true),
        ]);

        $eventHash = hash('sha256', $payloadRaw);

        $cleanupStmt = $GLOBALS['pdo']->prepare(
            'DELETE FROM webhook_events WHERE event_type = :event_type AND entity_type = :entity_type AND event_hash = :event_hash'
        );
        $cleanupStmt->execute([
            ':event_type' => 'lead_received',
            ':entity_type' => 'lead',
            ':event_hash' => $eventHash,
        ]);

        $service = new \App\Application\Integration\OLXWebhookService($GLOBALS['pdo']);
        $serviceReflection = new \ReflectionClass($service);

        $checkReplay = $serviceReflection->getMethod('checkReplay');
        $checkReplay->setAccessible(true);

        $recordEvent = $serviceReflection->getMethod('recordEvent');
        $recordEvent->setAccessible(true);

        // First invocation should not detect replay
        $checkReplay->invoke($service, $payloadRaw, 1);

        // Record the event
        $recordEvent->invoke($service, $payloadRaw, 1, 0);

        $verifyStmt = $GLOBALS['pdo']->prepare(
            'SELECT event_hash, data FROM webhook_events WHERE event_type = :event_type AND entity_type = :entity_type AND event_hash = :event_hash LIMIT 1'
        );
        $verifyStmt->execute([
            ':event_type' => 'lead_received',
            ':entity_type' => 'lead',
            ':event_hash' => $eventHash,
        ]);
        $row = $verifyStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row, 'Registro de evento não encontrado após recordEvent()');
        $this->assertEquals($eventHash, $row['event_hash']);
        $this->assertStringContainsString('event_hash', $row['data']);
        $this->assertStringContainsString($eventHash, $row['data']);

        // Now check that replay is detected
        $this->expectException(\Exception::class);
        $checkReplay->invoke($service, $payloadRaw, 1);

        // cleanup
        $cleanupStmt->execute([
            ':event_type' => 'lead_received',
            ':entity_type' => 'lead',
            ':event_hash' => $eventHash,
        ]);
    }
}
