<?php

namespace App\Infrastructure\Lead;

use PDO;

class LeadInteractionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function leadExistsForTenant(int $leadId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$leadId, $tenantId]);
        return (bool)$stmt->fetchColumn();
    }

    public function insertInteraction(int $leadId, int $tenantId, ?int $userId, string $type, string $title, ?string $description, ?string $oldValue, ?string $newValue, ?string $metadataJson): ?int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO lead_interactions
            (lead_id, tenant_id, user_id, interaction_type, title, description, old_value, new_value, metadata, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );

        $result = $stmt->execute([
            $leadId,
            $tenantId,
            $userId,
            $type,
            $title,
            $description,
            $oldValue,
            $newValue,
            $metadataJson
        ]);

        return $result ? (int)$this->pdo->lastInsertId() : null;
    }

    public function fetchInteractions(int $leadId, int $tenantId, ?int $limit = 50, ?int $offset = 0): array
    {
        $sql = "
            SELECT 
                li.id,
                li.lead_id,
                li.interaction_type,
                li.title,
                li.description,
                li.old_value,
                li.new_value,
                li.metadata,
                li.created_at,
                u.name as user_name,
                u.email as user_email
            FROM lead_interactions li
            LEFT JOIN users u ON li.user_id = u.id AND u.tenant_id = li.tenant_id
            WHERE li.lead_id = ? AND li.tenant_id = ?
            ORDER BY li.created_at DESC
        ";

        $params = [$leadId, $tenantId];
        if ($limit !== null) {
            $sql .= ' LIMIT ? OFFSET ?';
            $params[] = (int)$limit;
            $params[] = (int)($offset ?? 0);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countInteractions(int $leadId, int $tenantId, ?string $type = null): int
    {
        $sql = 'SELECT COUNT(*) as total FROM lead_interactions WHERE lead_id = ? AND tenant_id = ?';
        $params = [$leadId, $tenantId];

        if ($type !== null) {
            $sql .= ' AND interaction_type = ?';
            $params[] = $type;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['total']) ? (int)$row['total'] : 0;
    }

    public function getUserNameById(int $userId, int $tenantId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['name'] : null;
    }

    public function getActivityStats(int $leadId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT 
                interaction_type,
                COUNT(*) as count,
                MAX(created_at) as last_activity
            FROM lead_interactions
            WHERE lead_id = ? AND tenant_id = ?
            GROUP BY interaction_type"
        );
        $stmt->execute([$leadId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
