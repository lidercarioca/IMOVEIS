<?php

namespace App\Infrastructure\Alert;

use PDO;

class AlertRepository
{
    private PDO $pdo;

    private const SEVERITY_LABELS = [
        'LOW' => 'Baixa',
        'MEDIUM' => 'Média',
        'HIGH' => 'Alta',
        'CRITICAL' => 'Crítica',
    ];

    private const SEVERITY_DB_MAP = [
        'Baixa' => 'LOW',
        'Média' => 'MEDIUM',
        'Alta' => 'HIGH',
        'Crítica' => 'CRITICAL',
        'low' => 'LOW',
        'medium' => 'MEDIUM',
        'high' => 'HIGH',
        'critical' => 'CRITICAL',
        'info' => 'LOW',
        'warning' => 'HIGH',
        'success' => 'LOW'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function existsAlert(int $tenantId, ?int $userId, string $alertType, $relatedEntityId): bool
    {
        $sql = "SELECT 1 FROM alerts_history
                WHERE tenant_id = :tenant_id
                  AND alert_type = :alert_type
                  AND related_entity_id <=> :related_entity_id
                  AND deleted_at IS NULL";

        if ($userId !== null) {
            $sql .= " AND user_id <=> :user_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':alert_type', $alertType);
        $stmt->bindValue(':related_entity_id', $relatedEntityId);

        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (bool)$stmt->fetchColumn();
    }

    public function createGeneratedAlert(int $tenantId, ?int $userId, array $alert): bool
    {
        $alertType = $alert['type'] ?? $alert['alert_type'] ?? null;
        if (!$alertType) {
            return false;
        }

        $alertUserId = isset($alert['user_id']) ? (int)$alert['user_id'] : $userId;
        $relatedEntityId = $alert['entity_id'] ?? $alert['related_entity_id'] ?? null;
        if ($this->existsAlert($tenantId, $alertUserId, $alertType, $relatedEntityId)) {
            return false;
        }

        $severity = $this->normalizeSeverity($alert['severity'] ?? 'LOW');

        $sql = "INSERT INTO alerts_history
                (tenant_id, user_id, alert_type, severity, title, message, related_entity_type, related_entity_id, status, created_at, updated_at)
                VALUES (:tenant_id, :user_id, :alert_type, :severity, :title, :message, :related_entity_type, :related_entity_id, 'novo', NOW(), NOW())";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $alertUserId,
            ':alert_type' => $alertType,
            ':severity' => $severity,
            ':title' => $alert['title'] ?? 'Alerta',
            ':message' => $alert['description'] ?? $alert['message'] ?? null,
            ':related_entity_type' => $alert['related_entity_type'] ?? $alert['entity_type'] ?? 'unknown',
            ':related_entity_id' => $relatedEntityId,
        ]);
    }

    public function findResolvedAlertKeys(int $tenantId, int $userId, int $days = 90): array
    {
        $sql = "SELECT alert_type, related_entity_id FROM alerts_history
                WHERE user_id = :user_id
                  AND tenant_id = :tenant_id
                  AND status IN ('resolvido', 'descartado')
                  AND resolved_at IS NOT NULL
                  AND resolved_at > DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':tenant_id' => $tenantId,
            ':days' => $days,
        ]);

        $keys = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $keys[$row['alert_type'] . '::' . ($row['related_entity_id'] ?? 'null')] = true;
        }

        return $keys;
    }

    public function findAlerts(int $tenantId, ?int $userId, array $filters = [], int $page = 1, int $limit = 20): array
    {
        $statusFilter = $filters['status'] ?? 'todos';
        $severityFilter = $filters['severity'] ?? null;

        $where = ["tenant_id = :tenant_id", "deleted_at IS NULL"];
        $params = [':tenant_id' => $tenantId];

        if ($userId !== null) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if ($statusFilter !== 'todos' && $statusFilter !== '') {
            $mappedStatus = $this->normalizeStatus($statusFilter);
            if ($mappedStatus !== null) {
                $where[] = 'status = :status';
                $params[':status'] = $mappedStatus;
            }
        } else {
            $where[] = "status IN ('novo', 'reconhecido')";
        }

        if (!empty($severityFilter)) {
            $mappedSeverity = $this->normalizeSeverity($severityFilter);
            $where[] = 'severity = :severity';
            $params[':severity'] = $mappedSeverity;
        }

        if (!empty($filters['alert_type'])) {
            $where[] = 'alert_type = :alert_type';
            $params[':alert_type'] = $filters['alert_type'];
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM alerts_history WHERE $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $sql = "SELECT id, alert_type, severity, title, message, related_entity_type, related_entity_id, status, created_at, updated_at
                FROM alerts_history
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $data = [];
        while ($alert = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = [
                'id' => $alert['id'],
                'alert_type' => $alert['alert_type'],
                'type' => $alert['alert_type'],
                'severity' => $this->mapSeverityToLabel($alert['severity']),
                'title' => $alert['title'],
                'message' => $alert['message'],
                'description' => $alert['message'],
                'status' => $alert['status'],
                'timestamp' => $alert['created_at'],
                'sent_at' => $alert['created_at'],
                'created_at' => $alert['created_at'],
                'related_entity_type' => $alert['related_entity_type'],
                'related_entity_id' => $alert['related_entity_id'],
                'entity_id' => $alert['related_entity_id'],
                'color' => null,
                'icon' => null,
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    public function getStats(int $tenantId, ?int $userId = null): array
    {
        $where = ['tenant_id = :tenant_id', "status NOT IN ('resolvido', 'descartado')", 'deleted_at IS NULL'];
        $params = [':tenant_id' => $tenantId];

        if ($userId !== null) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(IF(status = 'novo', 1, 0)) as pending,
                    SUM(IF(status = 'reconhecido', 1, 0)) as acknowledged,
                    SUM(IF(alert_type = 'inactive_lead', 1, 0)) as inactive_leads,
                    SUM(IF(alert_type = 'inactive_property', 1, 0)) as inactive_properties,
                    SUM(IF(severity = 'CRITICAL', 1, 0)) as critical,
                    SUM(IF(severity = 'HIGH', 1, 0)) as high,
                    SUM(IF(severity = 'MEDIUM', 1, 0)) as medium,
                    SUM(IF(severity = 'LOW', 1, 0)) as low
                FROM alerts_history
                WHERE $whereClause";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $resolvedSql = "SELECT COUNT(*) as resolved FROM alerts_history
                        WHERE tenant_id = :tenant_id
                          AND status = 'resolvido'
                          AND resolved_at IS NOT NULL
                          AND deleted_at IS NULL";
        if ($userId !== null) {
            $resolvedSql .= ' AND user_id = :user_id';
        }

        $resolvedStmt = $this->pdo->prepare($resolvedSql);
        $resolvedStmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($userId !== null) {
            $resolvedStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $resolvedStmt->execute();
        $resolved = $resolvedStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'data' => [
                'total' => (int)($stats['total'] ?? 0),
                'pending' => (int)($stats['pending'] ?? 0),
                'acknowledged' => (int)($stats['acknowledged'] ?? 0),
                'resolved' => (int)($resolved['resolved'] ?? 0),
                'inactive_leads' => (int)($stats['inactive_leads'] ?? 0),
                'inactive_properties' => (int)($stats['inactive_properties'] ?? 0),
                'critical' => (int)($stats['critical'] ?? 0),
                'high' => (int)($stats['high'] ?? 0),
                'medium' => (int)($stats['medium'] ?? 0),
                'low' => (int)($stats['low'] ?? 0),
            ],
        ];
    }

    public function acknowledgeAlert(int $tenantId, int $alertId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE alerts_history
                                     SET status = 'reconhecido', acknowledged_at = NOW(), updated_at = NOW()
                                     WHERE id = :id AND tenant_id = :tenant_id");

        return $stmt->execute([':id' => $alertId, ':tenant_id' => $tenantId]);
    }

    public function dismissAlert(int $tenantId, int $alertId, ?int $userId = null): bool
    {
        $sql = "UPDATE alerts_history
                SET status = 'descartado', updated_at = NOW()
                WHERE id = :id AND tenant_id = :tenant_id";
        $params = [':id' => $alertId, ':tenant_id' => $tenantId];

        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateAlert(int $tenantId, int $alertId, array $data, ?int $resolvedBy = null): bool
    {
        $allowed = ['title', 'message', 'severity', 'status'];
        $updates = [];
        $params = [':id' => $alertId, ':tenant_id' => $tenantId];

        if (isset($data['title'])) {
            $updates[] = 'title = :title';
            $params[':title'] = $data['title'];
        }
        if (isset($data['message'])) {
            $updates[] = 'message = :message';
            $params[':message'] = $data['message'];
        }
        if (isset($data['severity'])) {
            $updates[] = 'severity = :severity';
            $params[':severity'] = $this->normalizeSeverity($data['severity']);
        }
        if (isset($data['status'])) {
            $normalizedStatus = $this->normalizeStatus($data['status']);
            if ($normalizedStatus !== null) {
                $updates[] = 'status = :status';
                $params[':status'] = $normalizedStatus;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = 'updated_at = NOW()';

        if (isset($data['status']) && $this->normalizeStatus($data['status']) === 'resolvido') {
            $updates[] = 'resolved_at = NOW()';
            if ($resolvedBy !== null) {
                $updates[] = 'resolved_by = :resolved_by';
                $params[':resolved_by'] = $resolvedBy;
            }
        }

        $sql = 'UPDATE alerts_history SET ' . implode(', ', $updates) . ' WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function deleteAlert(int $tenantId, int $alertId, ?int $userId = null): bool
    {
        $sql = "UPDATE alerts_history
                SET deleted_at = NOW(), updated_at = NOW()
                WHERE id = :id AND tenant_id = :tenant_id";
        $params = [':id' => $alertId, ':tenant_id' => $tenantId];

        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteAlertByTypeAndEntity(int $tenantId, string $alertType, $relatedEntityId, ?int $userId = null): bool
    {
        $sql = "UPDATE alerts_history
                SET deleted_at = NOW(), updated_at = NOW()
                WHERE tenant_id = :tenant_id
                  AND alert_type = :alert_type
                  AND related_entity_id <=> :related_entity_id
                  AND deleted_at IS NULL";
        $params = [
            ':tenant_id' => $tenantId,
            ':alert_type' => $alertType,
            ':related_entity_id' => $relatedEntityId,
        ];

        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function cleanupResolvedAlerts(int $tenantId): int
    {
        $stmt = $this->pdo->prepare("UPDATE alerts_history
                                     SET deleted_at = NOW(), updated_at = NOW()
                                     WHERE tenant_id = :tenant_id
                                       AND status = 'resolvido'
                                       AND resolved_at IS NOT NULL
                                       AND deleted_at IS NULL");

        $stmt->execute([':tenant_id' => $tenantId]);
        return $stmt->rowCount();
    }

    private function normalizeSeverity(string $severity): string
    {
        $normalized = trim((string)$severity);
        if (isset(self::SEVERITY_DB_MAP[$normalized])) {
            return self::SEVERITY_DB_MAP[$normalized];
        }

        return strtoupper($normalized) === $normalized ? $normalized : strtoupper($normalized);
    }

    private function mapSeverityToLabel(string $severity): string
    {
        return self::SEVERITY_LABELS[$severity] ?? $severity;
    }

    private function normalizeStatus(string $status): ?string
    {
        $status = strtolower(trim($status));

        if (in_array($status, ['pending', 'novo', 'new'], true)) {
            return 'novo';
        }

        if (in_array($status, ['acknowledged', 'reconhecido', 'read'], true)) {
            return 'reconhecido';
        }

        if (in_array($status, ['resolved', 'resolvido'], true)) {
            return 'resolvido';
        }

        if (in_array($status, ['discarded', 'descartado'], true)) {
            return 'descartado';
        }

        return null;
    }
}
