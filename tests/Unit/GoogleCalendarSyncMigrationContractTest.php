<?php

namespace Tests\Unit;

use App\Repository\GoogleCalendarSyncRepository;
use App\Service\GoogleCalendarSyncService;
use PHPUnit\Framework\TestCase;

class GoogleCalendarSyncMigrationContractTest extends TestCase
{
    public function testGoogleCalendarSyncServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(GoogleCalendarSyncService::class));
        $this->assertTrue(class_exists(GoogleCalendarSyncRepository::class));
        $this->assertTrue(method_exists(GoogleCalendarSyncService::class, 'validateUserBelongsToTenant'));
        $this->assertTrue(method_exists(GoogleCalendarSyncRepository::class, 'userExistsInTenant'));
    }

    public function testGoogleCalendarSyncEndpointUsesServiceAndNoInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/v1/google/syncCalendar.php');

        $this->assertNotFalse($endpoint);
        $this->assertStringContainsString('new \\App\\Service\\GoogleCalendarSyncService', $endpoint);
        $this->assertStringNotContainsString('SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1', $endpoint);
        $this->assertStringNotContainsString('SELECT id FROM users WHERE id = ? AND tenant_id = ?', $endpoint);
    }
}
