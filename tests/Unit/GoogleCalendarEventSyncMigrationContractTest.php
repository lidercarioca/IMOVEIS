<?php

namespace Tests\Unit;

use App\Repository\GoogleCalendarSyncRepository;
use App\Service\GoogleCalendarSyncService;
use PHPUnit\Framework\TestCase;

class GoogleCalendarEventSyncMigrationContractTest extends TestCase
{
    public function testGoogleCalendarEventSyncServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(GoogleCalendarSyncService::class));
        $this->assertTrue(class_exists(GoogleCalendarSyncRepository::class));
        $this->assertTrue(method_exists(GoogleCalendarSyncService::class, 'validateUserBelongsToTenant'));
        $this->assertTrue(method_exists(GoogleCalendarSyncService::class, 'resolveOwnedAppointmentForUser'));
        $this->assertTrue(method_exists(GoogleCalendarSyncRepository::class, 'findSyncRecordForAppointment'));
        $this->assertTrue(method_exists(GoogleCalendarSyncRepository::class, 'countSyncedEventsForTenant'));
    }

    public function testGoogleCalendarEventSyncEndpointUsesServiceAndNoInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/v1/google_calendar.php');

        $this->assertNotFalse($endpoint);
        $this->assertStringContainsString('new \\App\\Service\\GoogleCalendarSyncService', $endpoint);
        $this->assertStringNotContainsString('SELECT endereco FROM properties WHERE id = ? AND tenant_id = ? LIMIT 1', $endpoint);
        $this->assertStringNotContainsString('SELECT google_event_id FROM agendamentos_google_sync WHERE agendamento_id = ? AND tenant_id = ? LIMIT 1', $endpoint);
        $this->assertStringNotContainsString('SELECT COUNT(*) as count FROM agendamentos_google_sync WHERE tenant_id = ?', $endpoint);
        $this->assertStringNotContainsString('SELECT a.* FROM agendamentos a WHERE a.id = ? AND a.tenant_id = ? AND a.user_id = ? LIMIT 1', $endpoint);
    }
}
