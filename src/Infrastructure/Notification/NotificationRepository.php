<?php

namespace App\Infrastructure\Notification;

use PDO;

/**
 * NotificationRepository - exclusive owner of SQL access to notifications table
 */
class NotificationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByUser(int $userId, int $tenantId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, title, message, is_read, created_at, link
             FROM notifications
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $tenantId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function emailNotificationExists(int $userId, string $emailId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE type = :type
               AND title LIKE CONCAT(\'%\', :email_id, \'%\')
               AND is_read = 0
               AND user_id = :user_id'
        );
        $stmt->execute([
            ':type' => 'email',
            ':email_id' => $emailId,
            ':user_id' => $userId,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function gmailNotificationExists(int $userId, string $gmailId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE type = :type
               AND link LIKE CONCAT(\'%\', :gmail_id, \'%\')
               AND is_read = 0
               AND user_id = :user_id'
        );
        $stmt->execute([
            ':type' => 'gmail',
            ':gmail_id' => $gmailId,
            ':user_id' => $userId,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function createNotification(int $userId, string $type, string $title, string $message, string $link, bool $isRead = false): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (type, title, message, link, is_read, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );

        return $stmt->execute([
            $type,
            $title,
            $message,
            $link,
            $isRead ? 1 : 0,
            $userId,
        ]);
    }

    public function createForUserAndTenant(int $userId, int $tenantId, string $type, string $title, string $message, string $link, bool $isRead = false): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (type, title, message, link, is_read, user_id, tenant_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        return $stmt->execute([
            $type,
            $title,
            $message,
            $link,
            $isRead ? 1 : 0,
            $userId,
            $tenantId,
        ]);
    }

    public function createMessageForTenant(int $tenantId, string $fromName, string $fromEmail, string $subject, string $message, ?int $propertyId = null, ?int $userId = null): int|false
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (from_name, from_email, subject, message, property_id, user_id, tenant_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $ok = $stmt->execute([
            $fromName,
            $fromEmail,
            $subject,
            $message,
            $propertyId,
            $userId,
            $tenantId,
        ]);

        if (!$ok) {
            return false;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function countUnreadByUser(int $userId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS cnt
             FROM notifications
             WHERE user_id = ? AND tenant_id = ? AND is_read = 0'
        );
        $stmt->execute([$userId, $tenantId]);

        return (int)$stmt->fetchColumn();
    }

    public function listReadByUser(int $userId, int $tenantId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, title, message, is_read, created_at, link
             FROM notifications
             WHERE user_id = ? AND tenant_id = ? AND is_read = 1
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $tenantId, $limit, $offset]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countReadByUser(int $userId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS cnt
             FROM notifications
             WHERE user_id = ? AND tenant_id = ? AND is_read = 1'
        );
        $stmt->execute([$userId, $tenantId]);

        return (int)$stmt->fetchColumn();
    }

    public function markReadById(int $notificationId, int $userId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = ? AND user_id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$notificationId, $userId, $tenantId]);
    }

    public function markAllReadByUser(int $userId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = ? AND tenant_id = ? AND is_read = 0'
        );

        return $stmt->execute([$userId, $tenantId]);
    }

    public function deleteById(int $notificationId, int $userId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM notifications
             WHERE id = ? AND user_id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$notificationId, $userId, $tenantId]);
    }
}
