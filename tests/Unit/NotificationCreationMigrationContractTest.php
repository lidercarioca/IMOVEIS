<?php

namespace Tests\Unit;

use App\Infrastructure\Notification\NotificationRepository;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;

class NotificationCreationMigrationContractTest extends TestCase
{
    public function testNotificationServiceAndRepositoryExposeCreationMethods(): void
    {
        $this->assertTrue(class_exists(NotificationService::class));
        $this->assertTrue(class_exists(NotificationRepository::class));
        $this->assertTrue(method_exists(NotificationService::class, 'createEmailNotification'));
        $this->assertTrue(method_exists(NotificationService::class, 'createGmailNotification'));
        $this->assertTrue(method_exists(NotificationRepository::class, 'emailNotificationExists'));
        $this->assertTrue(method_exists(NotificationRepository::class, 'createNotification'));
    }

    public function testNotificationCreationEndpointsUseServiceAndNoInlineSql(): void
    {
        $emailEndpoint = file_get_contents(__DIR__ . '/../../api/v1/auth/createEmailNotification.php');
        $gmailEndpoint = file_get_contents(__DIR__ . '/../../api/v1/auth/createGmailNotification.php');

        $this->assertNotFalse($emailEndpoint);
        $this->assertNotFalse($gmailEndpoint);

        $this->assertStringContainsString('new \\App\\Service\\NotificationService', $emailEndpoint);
        $this->assertStringContainsString('new \\App\\Service\\NotificationService', $gmailEndpoint);
        $this->assertStringNotContainsString("SELECT COUNT(*) as count\n        FROM notifications \n        WHERE type = 'email'", $emailEndpoint);
        $this->assertStringNotContainsString("SELECT COUNT(*) as count\n        FROM notifications \n        WHERE type = 'gmail'", $gmailEndpoint);
        $this->assertStringNotContainsString('INSERT INTO notifications (type, title, message, link, is_read, user_id, created_at)', $emailEndpoint);
        $this->assertStringNotContainsString('INSERT INTO notifications (type, title, message, link, is_read, user_id, created_at)', $gmailEndpoint);
    }

    public function testNotificationManagerDelegatesToRepositoryInsteadOfInlineSql(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/utils/NotificationManager.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString('new NotificationRepository', $source);
        $this->assertStringContainsString('createForUserAndTenant', $source);
        $this->assertStringContainsString('createMessageForTenant', $source);
        $this->assertStringNotContainsString('SELECT * FROM company_settings WHERE tenant_id = ?', $source);
        $this->assertStringNotContainsString('INSERT INTO notifications (type, title, message, link, user_id, tenant_id)', $source);
        $this->assertStringNotContainsString('INSERT INTO messages (from_name, from_email, subject, message, property_id, user_id, tenant_id)', $source);
    }

    public function testEmailNotificationServiceUsesCompanySettingsServiceAndNotInlineSql(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/utils/EmailNotificationService.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString('CompanySettingsService', $source);
        $this->assertStringContainsString('getContactInfoForTenant', $source);
        $this->assertStringNotContainsString('SELECT * FROM company_settings WHERE id = 1', $source);
    }
}
