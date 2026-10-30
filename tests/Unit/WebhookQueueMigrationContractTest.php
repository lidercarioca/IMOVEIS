<?php

namespace Tests\Unit;

use App\Infrastructure\Webhook\WebhookRepository;
use App\Service\WebhookService;
use PHPUnit\Framework\TestCase;

class WebhookQueueMigrationContractTest extends TestCase
{
    public function testWebhookServiceAndRepositoryExposeQueueCleanupMethods(): void
    {
        $this->assertTrue(class_exists(WebhookService::class));
        $this->assertTrue(class_exists(WebhookRepository::class));
        $this->assertTrue(method_exists(WebhookService::class, 'processQueue'));
        $this->assertTrue(method_exists(WebhookService::class, 'cleanupOldLogs'));
        $this->assertTrue(method_exists(WebhookRepository::class, 'cleanupOldLogs'));
    }

    public function testProcessQueueEndpointUsesServiceAndNoInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/v1/webhooks/processQueue.php');

        $this->assertNotFalse($endpoint);
        $this->assertStringContainsString('new \\App\\Service\\WebhookService', $endpoint);
        $this->assertStringNotContainsString('DELETE FROM webhook_logs', $endpoint);
        $this->assertStringNotContainsString('DELETE FROM webhook_logs', $endpoint);
    }
}
