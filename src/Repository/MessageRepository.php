<?php

namespace App\Repository;

use PDO;

class MessageRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listForUser(int $userId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, p.title as property_title '
                . 'FROM messages m '
                . 'LEFT JOIN properties p ON m.property_id = p.id '
                . 'WHERE m.user_id = ? AND m.tenant_id = ? '
                . 'ORDER BY m.is_read ASC, m.created_at DESC '
                . 'LIMIT 50'
        );
        $stmt->execute([$userId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnreadForUser(int $userId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM messages WHERE is_read = FALSE AND user_id = ? AND tenant_id = ?'
        );
        $stmt->execute([$userId, $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    public function listUnreadForTenant(int $tenantId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, from_name, from_email, subject, created_at '
                . 'FROM messages '
                . 'WHERE is_read = FALSE AND tenant_id = ? '
                . 'ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$tenantId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnreadForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM messages WHERE is_read = FALSE AND tenant_id = ?'
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    public function markAsRead(int $userId, int $tenantId, ?int $messageId = null): bool
    {
        if ($messageId !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE messages SET is_read = TRUE WHERE id = ? AND user_id = ? AND tenant_id = ?'
            );
            $stmt->execute([$messageId, $userId, $tenantId]);

            return $stmt->rowCount() >= 0;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE messages SET is_read = TRUE WHERE user_id = ? AND tenant_id = ?'
        );
        $stmt->execute([$userId, $tenantId]);

        return $stmt->rowCount() >= 0;
    }
}
