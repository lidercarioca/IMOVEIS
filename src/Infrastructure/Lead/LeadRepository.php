<?php

namespace App\Infrastructure\Lead;

use PDO;
use App\Domain\Lead\Lead;

class LeadRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countLeads(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) as total FROM leads l';
        [$sql, $params] = $this->buildFilters($sql, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($result['total']) ? (int)$result['total'] : 0;
    }

    public function listLeads(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $sql = 'SELECT l.*, u.name AS assigned_user_name FROM leads l '
            . 'LEFT JOIN users u ON l.assigned_user_id = u.id AND u.tenant_id = l.tenant_id';

        [$sql, $params] = $this->buildFilters($sql, $filters);
        $sql .= ' ORDER BY l.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLeadByIdAndTenant(int $leadId, int $tenantId): ?Lead
    {
        $stmt = $this->pdo->prepare('SELECT * FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$leadId, $tenantId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new Lead($data) : null;
    }

    public function findExistingLeadForContact(int $tenantId, string $email, string $phone): ?Lead
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM leads WHERE tenant_id = ? AND (email = ? OR phone = ?) ORDER BY updated_at DESC LIMIT 1'
        );
        $stmt->execute([$tenantId, $email, $phone]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new Lead($data) : null;
    }

    public function createLead(Lead $lead, ?int $utmTrackingId = null): int
    {
        $sql = 'INSERT INTO leads (tenant_id, name, email, phone, property_id, message, status, created_at, updated_at, source, utm_tracking_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $lead->tenantId,
            $lead->name,
            $lead->email,
            $lead->phone,
            $lead->propertyId,
            $lead->message,
            $lead->status,
            $lead->createdAt,
            $lead->updatedAt,
            $lead->source,
            $utmTrackingId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getTenantIdForProperty(int $propertyId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT tenant_id FROM properties WHERE id = ? LIMIT 1');
        $stmt->execute([$propertyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['tenant_id'] : null;
    }

    public function getPropertyTitleAndAssignedUser(int $propertyId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT title, assigned_user_id FROM properties WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$propertyId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getAdminsByTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND role = 'admin'");
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function leadExistsForTenant(int $tenantId, int $leadId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$leadId, $tenantId]);

        return (bool)$stmt->fetchColumn();
    }

    public function getLeadState(int $tenantId, int $leadId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT status, assigned_user_id, proposal_sent FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$leadId, $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function canUserAccessLead(int $tenantId, int $leadId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as count FROM leads WHERE id = ? AND tenant_id = ? AND (property_id IS NULL OR property_id IN (SELECT id FROM properties WHERE assigned_user_id = ? AND tenant_id = ?))');
        $stmt->execute([$leadId, $tenantId, $userId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($row['count']) && (int)$row['count'] > 0;
    }

    public function countAccessibleLeadsForUser(int $tenantId, int $userId, array $leadIds): int
    {
        if (empty($leadIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $sql = "SELECT COUNT(*) as count FROM leads WHERE id IN ($placeholders) AND tenant_id = ? AND (property_id IS NULL OR property_id IN (SELECT id FROM properties WHERE assigned_user_id = ? AND tenant_id = ?))";
        $params = array_merge($leadIds, [$tenantId, $userId, $tenantId]);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($row['count']) ? (int)$row['count'] : 0;
    }

    public function getPropertyTitle(int $propertyId, int $tenantId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT title FROM properties WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$propertyId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['title'] : null;
    }

    public function touchLead(int $leadId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE leads SET updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$leadId, $tenantId]);
    }

    public function updateLead(int $leadId, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, ['name', 'email', 'phone', 'message', 'status', 'property_id', 'assigned_user_id', 'source', 'utm_tracking_id', 'notes', 'proposal_sent', 'proposal_sent_at'], true)) {
                $fields[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $leadId;
        $params[] = $tenantId;

        $sql = 'UPDATE leads SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function deleteLead(int $leadId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM leads WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$leadId, $tenantId]);
    }

    public function deleteLeads(array $leadIds, int $tenantId): bool
    {
        if (empty($leadIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $sql = "DELETE FROM leads WHERE id IN ($placeholders) AND tenant_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $params = array_merge($leadIds, [$tenantId]);

        return $stmt->execute($params);
    }

    public function assignLeadToUser(int $leadId, int $tenantId, ?int $assignedUserId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE leads SET assigned_user_id = ? WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$assignedUserId, $leadId, $tenantId]);
    }

    public function getAssignedUserId(int $leadId, int $tenantId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT assigned_user_id FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$leadId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? ($row['assigned_user_id'] !== null ? (int)$row['assigned_user_id'] : null) : null;
    }

    public function getInactiveLeads(int $tenantId, int $days = 7, int $limit = 50, ?int $filterUserId = null, ?int $currentUserId = null, bool $isAdmin = false): array
    {
        $sql = "
            SELECT 
                l.id,
                l.name,
                l.email,
                l.phone,
                l.status,
                l.created_at as lead_created_at,
                l.assigned_user_id,
                u.name as assigned_user_name,
                sub.last_interaction_at,
                DATEDIFF(NOW(), sub.last_interaction_at) as days_since_interaction,
                sub.total_interactions,
                sub.recent_interactions
            FROM leads l
            LEFT JOIN users u ON l.assigned_user_id = u.id
            LEFT JOIN (
                SELECT 
                    li.lead_id,
                    MAX(li.created_at) as last_interaction_at,
                    COUNT(li.id) as total_interactions,
                    GROUP_CONCAT(DISTINCT li.interaction_type ORDER BY li.created_at DESC LIMIT 3) as recent_interactions
                FROM lead_interactions li
                GROUP BY li.lead_id
            ) sub ON l.id = sub.lead_id
            WHERE l.tenant_id = ?
            AND l.status NOT IN ('converted', 'lost', 'archived')
            AND (
                sub.last_interaction_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                OR sub.last_interaction_at IS NULL
            )
        ";

        $params = [$tenantId, $days];

        if ($filterUserId !== null && $filterUserId > 0) {
            $sql .= " AND l.assigned_user_id = ?";
            $params[] = $filterUserId;
        }

        if (!$isAdmin && $currentUserId !== null && $currentUserId > 0) {
            $sql .= " AND (l.assigned_user_id = ? OR l.property_id IN (SELECT id FROM properties WHERE assigned_user_id = ? AND tenant_id = ?))";
            $params[] = $currentUserId;
            $params[] = $currentUserId;
            $params[] = $tenantId;
        }

        $sql .= " ORDER BY days_since_interaction DESC, sub.last_interaction_at ASC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildFilters(string $sql, array $filters): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['tenant_id'])) {
            $conditions[] = 'l.tenant_id = ?';
            $params[] = $filters['tenant_id'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = 'l.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['assigned_user_id'])) {
            $conditions[] = 'l.assigned_user_id = ?';
            $params[] = $filters['assigned_user_id'];
        }

        if (!empty($filters['assigned_user_id_or_property_owner'])) {
            $conditions[] = '(l.assigned_user_id = ? OR l.property_id IN (SELECT id FROM properties WHERE assigned_user_id = ? AND tenant_id = ?))';
            $params[] = $filters['assigned_user_id_or_property_owner'];
            $params[] = $filters['assigned_user_id_or_property_owner'];
            $params[] = $filters['tenant_id'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.message LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['created_after'])) {
            $conditions[] = 'l.created_at >= ?';
            $params[] = $filters['created_after'];
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        return [$sql, $params];
    }
}
