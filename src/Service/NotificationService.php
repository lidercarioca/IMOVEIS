<?php

namespace App\Service;

use PDO;
use App\Infrastructure\Notification\NotificationRepository;

/**
 * NotificationService - service layer for notification read/list/delete operations
 */
class NotificationService
{
    private PDO $pdo;
    private NotificationRepository $repository;

    public function __construct(PDO $pdo, ?NotificationRepository $repository = null)
    {
        $this->pdo = $pdo;
        $this->repository = $repository ?? new NotificationRepository($pdo);
    }

    public function listNotificationsForUser(int $userId, int $tenantId, int $limit = 50): array
    {
        return $this->repository->listByUser($userId, $tenantId, $limit);
    }

    public function emailNotificationExists(int $userId, string $emailId): bool
    {
        return $this->repository->emailNotificationExists($userId, $emailId);
    }

    public function gmailNotificationExists(int $userId, string $gmailId): bool
    {
        return $this->repository->gmailNotificationExists($userId, $gmailId);
    }

    public function createEmailNotification(int $userId, string $emailId, string $fromName, string $fromEmail): bool
    {
        if ($this->repository->emailNotificationExists($userId, $emailId)) {
            return false;
        }

        $title = 'Novo Email de ' . $fromName;
        $message = 'Você recebeu um novo email de ' . $fromEmail;

        return $this->repository->createNotification(
            $userId,
            'email',
            $title,
            $message,
            'painel.php?tab=messages'
        );
    }

    public function createGmailNotification(int $userId, string $gmailId, string $from, string $subject, string $snippet = ''): bool
    {
        if ($this->repository->gmailNotificationExists($userId, $gmailId)) {
            return false;
        }

        $title = 'Novo Email: ' . substr($subject, 0, 50);
        $message = 'Você recebeu um novo email de ' . $from . ': ' . substr($snippet, 0, 100);

        return $this->repository->createNotification(
            $userId,
            'gmail',
            $title,
            $message,
            'painel.php?tab=emails&gmail_id=' . urlencode($gmailId)
        );
    }

    public function countUnreadNotificationsForUser(int $userId, int $tenantId): int
    {
        return $this->repository->countUnreadByUser($userId, $tenantId);
    }

    public function listReadNotificationsForUser(int $userId, int $tenantId, int $limit = 20, int $offset = 0): array
    {
        return $this->repository->listReadByUser($userId, $tenantId, $limit, $offset);
    }

    public function countReadNotificationsForUser(int $userId, int $tenantId): int
    {
        return $this->repository->countReadByUser($userId, $tenantId);
    }

    public function markNotificationRead(int $notificationId, int $userId, int $tenantId): bool
    {
        return $this->repository->markReadById($notificationId, $userId, $tenantId);
    }

    public function markAllNotificationsRead(int $userId, int $tenantId): bool
    {
        return $this->repository->markAllReadByUser($userId, $tenantId);
    }

    public function deleteNotification(int $notificationId, int $userId, int $tenantId): bool
    {
        return $this->repository->deleteById($notificationId, $userId, $tenantId);
    }
}
