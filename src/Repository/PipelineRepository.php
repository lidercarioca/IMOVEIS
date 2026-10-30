<?php

namespace App\Repository;

use PDO;

class PipelineRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findLeadPropertyIdByTenant(int $tenantId, int $leadId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT property_id FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$leadId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['property_id'])) {
            return null;
        }

        return (int) $row['property_id'];
    }

    public function findStageByTenant(int $tenantId, int $stageId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM pipeline_stages WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$stageId, $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createItem(int $tenantId, int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sales_pipeline (tenant_id, lead_id, property_id, user_id, pipeline_stage_id, value, expected_close_date, notes, position) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );

        $stmt->execute([
            $tenantId,
            $data['lead_id'] ?? null,
            $data['property_id'] ?? null,
            $userId,
            $data['pipeline_stage_id'],
            $data['value'] ?? 0,
            $data['expected_close_date'] ?? null,
            $data['notes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listItemsByTenant(int $tenantId, ?int $stageId = null, int $archived = 0, ?int $userId = null, bool $isAdmin = false): array
    {
        $query = "
            SELECT 
                sp.id as pipeline_id,
                sp.lead_id,
                sp.property_id,
                sp.user_id,
                sp.pipeline_stage_id,
                sp.value,
                sp.expected_close_date,
                sp.notes,
                sp.position,
                l.name as lead_name,
                l.phone as lead_phone,
                l.email as lead_email,
                l.status as lead_status,
                l.assigned_user_id as lead_assigned_user_id,
                p.title as property_title,
                p.price as property_price,
                p.type as property_type,
                p.location as property_location,
                p.assigned_user_id as property_assigned_user_id,
                ps.name as stage_name,
                ps.color as stage_color
            FROM sales_pipeline sp
            LEFT JOIN leads l ON sp.lead_id = l.id AND l.tenant_id = ?
            LEFT JOIN properties p ON sp.property_id = p.id AND p.tenant_id = ?
            LEFT JOIN pipeline_stages ps ON sp.pipeline_stage_id = ps.id AND ps.tenant_id = ?
            WHERE sp.deleted_at IS NULL
                AND sp.is_archived = ?
                AND sp.tenant_id = ?";

        $params = [$tenantId, $tenantId, $tenantId, $archived, $tenantId];

        if ($stageId !== null && $stageId > 0) {
            $query .= ' AND sp.pipeline_stage_id = ?';
            $params[] = $stageId;
        }

        if (!$isAdmin && $userId !== null && $userId > 0) {
            $query .= ' AND sp.user_id = ?';
            $params[] = $userId;
        }

        $query .= ' ORDER BY sp.pipeline_stage_id, sp.position ASC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $item): array {
            return [
                'id' => (int) $item['pipeline_id'],
                'lead_id' => $item['lead_id'] ? (int) $item['lead_id'] : null,
                'property_id' => $item['property_id'] ? (int) $item['property_id'] : null,
                'stage_id' => (int) $item['pipeline_stage_id'],
                'value' => (float) ($item['value'] ?? 0.0),
                'expected_close_date' => $item['expected_close_date'] ?? null,
                'notes' => $item['notes'] ?? null,
                'assigned_user_id' => (int) ($item['lead_assigned_user_id'] ?? $item['property_assigned_user_id'] ?? $item['user_id'] ?? 0),
                'title' => $item['lead_name'] ?? $item['property_title'] ?? 'Item sem título',
                'lead_email' => $item['lead_email'] ?? null,
                'lead_phone' => $item['lead_phone'] ?? null,
                'subtitle' => ($item['property_type'] ? 'Imóvel: ' . $item['property_type'] : '')
                    . (($item['property_type'] && ($item['lead_phone'] || $item['lead_email'])) ? ' | ' : '')
                    . (($item['lead_phone'] ? 'Tel: ' . $item['lead_phone'] : '') . ($item['lead_phone'] && $item['lead_email'] ? ' / ' : '') . ($item['lead_email'] ? 'Email: ' . $item['lead_email'] : '')),
                'type' => !empty($item['lead_id']) ? 'lead' : 'property',
                'pipeline_user_id' => (int) ($item['user_id'] ?? 0),
                'stage_name' => $item['stage_name'] ?? null,
                'stage_color' => $item['stage_color'] ?? null,
            ];
        }, $items);
    }

    public function listStagesByTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, description, position, color, is_active FROM pipeline_stages WHERE is_active = 1 AND tenant_id = ? ORDER BY position ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMetricsByTenant(int $tenantId, ?int $userId = null, bool $isAdmin = false): array
    {
        $query = "
            SELECT 
                sp.pipeline_stage_id,
                ps.name as stage_name,
                COUNT(sp.id) as stage_count,
                SUM(sp.value) as stage_value,
                COUNT(sp.id) as items
            FROM sales_pipeline sp
            LEFT JOIN pipeline_stages ps ON sp.pipeline_stage_id = ps.id AND ps.tenant_id = ?
            WHERE sp.deleted_at IS NULL AND sp.tenant_id = ?";

        $params = [$tenantId, $tenantId];
        if (!$isAdmin && $userId !== null && $userId > 0) {
            $query .= ' AND sp.user_id = ?';
            $params[] = $userId;
        }

        $query .= ' GROUP BY sp.pipeline_stage_id, ps.name ORDER BY ps.position ASC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalValue = 0.0;
        $totalItems = 0;
        $byStage = [];

        foreach ($stages as $stage) {
            $value = (float) ($stage['stage_value'] ?? 0);
            $count = (int) ($stage['stage_count'] ?? 0);

            $totalValue += $value;
            $totalItems += $count;

            $byStage[(int) ($stage['pipeline_stage_id'] ?? 0)] = [
                'name' => $stage['stage_name'],
                'count' => $count,
                'value' => $value,
            ];
        }

        $averageValue = $totalItems > 0 ? ($totalValue / $totalItems) : 0.0;

        return [
            'total_value' => round($totalValue, 2),
            'total_items' => $totalItems,
            'average_value' => round($averageValue, 2),
            'by_stage' => $byStage,
        ];
    }

    public function findItemByTenant(int $tenantId, int $pipelineId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.id, sp.user_id, sp.notes, sp.value, sp.pipeline_stage_id, sp.expected_close_date
             FROM sales_pipeline sp
             WHERE sp.id = ? AND sp.deleted_at IS NULL AND sp.tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$pipelineId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateItemByIdAndTenant(int $tenantId, int $pipelineId, array $fields): bool
    {
        if ($fields === []) {
            return false;
        }

        $setClauses = [];
        $params = [];
        foreach ($fields as $field => $value) {
            $setClauses[] = $field . ' = ?';
            $params[] = $value;
        }

        $params[] = $pipelineId;
        $params[] = $tenantId;

        $sql = 'UPDATE sales_pipeline SET ' . implode(', ', $setClauses) . ' WHERE id = ? AND deleted_at IS NULL AND tenant_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);

        return $result && $stmt->rowCount() > 0;
    }

    public function moveItemByIdAndTenant(int $tenantId, int $pipelineId, int $toStageId, array $fields = []): bool
    {
        $payload = ['pipeline_stage_id' => $toStageId];
        foreach ($fields as $field => $value) {
            $payload[$field] = $value;
        }

        return $this->updateItemByIdAndTenant($tenantId, $pipelineId, $payload);
    }

    public function deleteItemByIdAndTenant(int $tenantId, int $pipelineId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE sales_pipeline SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$pipelineId, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function findAvailableLeads(int $tenantId, string $search = '', int $limit = 100): array
    {
        $query = "SELECT l.id, l.name, l.email, l.phone, p.price
                  FROM leads l
                  LEFT JOIN properties p ON l.property_id = p.id AND p.tenant_id = ?
                  WHERE l.status != 'closed' AND l.status != 'cancelled' AND l.tenant_id = ?";

        $params = [$tenantId, $tenantId];

        if ($search !== '') {
            $query .= ' AND (l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)';
            $likeSearch = '%' . $search . '%';
            $params[] = $likeSearch;
            $params[] = $likeSearch;
            $params[] = $likeSearch;
        }

        $query .= ' ORDER BY l.name ASC LIMIT ?';
        $params[] = max(1, (int) $limit);

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $item): array {
            return [
                'id' => (int) $item['id'],
                'text' => ($item['name'] ?? '') . ' (' . ($item['phone'] ?? $item['email'] ?? 'S/contato') . ')',
                'value' => isset($item['price']) ? (float) $item['price'] : 0.0,
            ];
        }, $items);
    }

    public function findAvailableProperties(int $tenantId, string $search = '', int $limit = 100): array
    {
        $query = "SELECT id, title, neighborhood, type, price FROM properties WHERE status IN ('active', 'available') AND tenant_id = ?";
        $params = [$tenantId];

        if ($search !== '') {
            $query .= ' AND (title LIKE ? OR neighborhood LIKE ? OR type LIKE ?)';
            $likeSearch = '%' . $search . '%';
            $params[] = $likeSearch;
            $params[] = $likeSearch;
            $params[] = $likeSearch;
        }

        $query .= ' ORDER BY title ASC LIMIT ?';
        $params[] = max(1, (int) $limit);

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $item): array {
            return [
                'id' => (int) $item['id'],
                'text' => ($item['title'] ?? '') . ' - ' . ($item['neighborhood'] ?? '') . ' (' . ($item['type'] ?? '') . ')',
                'value' => isset($item['price']) ? (float) $item['price'] : 0.0,
            ];
        }, $items);
    }
}
