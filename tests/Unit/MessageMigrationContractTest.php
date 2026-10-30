<?php

namespace Tests\Unit;

use App\Repository\MessageRepository;
use App\Service\MessageService;
use PHPUnit\Framework\TestCase;

class MessageMigrationContractTest extends TestCase
{
    public function testMessageServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(MessageService::class));
        $this->assertTrue(class_exists(MessageRepository::class));
        $this->assertTrue(method_exists(MessageService::class, 'listForUser'));
        $this->assertTrue(method_exists(MessageService::class, 'markAsRead'));
        $this->assertTrue(method_exists(MessageService::class, 'countUnreadForTenant'));
        $this->assertTrue(method_exists(MessageService::class, 'listUnreadForTenant'));
    }

    public function testMessageEndpointsUseServiceInsteadOfRawSql(): void
    {
        $getMessages = file_get_contents(__DIR__ . '/../../api/v1/messages/getMessages.php');
        $markMessageRead = file_get_contents(__DIR__ . '/../../api/v1/messages/markMessageRead.php');
        $markSingleMessageAsRead = file_get_contents(__DIR__ . '/../../api/v1/auth/markMessageAsRead.php');
        $markAllMessagesAsRead = file_get_contents(__DIR__ . '/../../api/v1/auth/markAllMessagesAsRead.php');
        $getUnreadEmails = file_get_contents(__DIR__ . '/../../api/v1/auth/getUnreadEmails.php');

        $this->assertTrue(
            str_contains($getMessages, 'new MessageService') || str_contains($getMessages, 'new \\App\\Service\\MessageService')
        );
        $this->assertTrue(
            str_contains($markMessageRead, 'new MessageService') || str_contains($markMessageRead, 'new \\App\\Service\\MessageService')
        );
        $this->assertTrue(
            str_contains($markSingleMessageAsRead, 'new MessageService') || str_contains($markSingleMessageAsRead, 'new \\App\\Service\\MessageService')
        );
        $this->assertTrue(
            str_contains($markAllMessagesAsRead, 'new MessageService') || str_contains($markAllMessagesAsRead, 'new \\App\\Service\\MessageService')
        );
        $this->assertTrue(
            str_contains($getUnreadEmails, 'new MessageService') || str_contains($getUnreadEmails, 'new \\App\\Service\\MessageService')
        );

        $this->assertStringNotContainsString('SELECT m.*, p.title as property_title', strtolower($getMessages));
        $this->assertStringNotContainsString('UPDATE messages', strtolower($markMessageRead));
        $this->assertStringNotContainsString('UPDATE messages', strtolower($markSingleMessageAsRead));
        $this->assertStringNotContainsString('UPDATE messages', strtolower($markAllMessagesAsRead));
        $this->assertStringNotContainsString('SELECT COUNT(*) as cnt FROM messages WHERE is_read = FALSE AND tenant_id = ?', strtolower($getUnreadEmails));
        $this->assertStringNotContainsString('SELECT id, from_name, from_email, subject, created_at FROM messages WHERE is_read = FALSE AND tenant_id = ?', strtolower($getUnreadEmails));
    }
}
