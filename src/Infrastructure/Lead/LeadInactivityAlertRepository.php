<?php

namespace App\Infrastructure\Lead;

use PDO;

class LeadInactivityAlertRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findInactiveLeads(int $tenantId, int $daysThreshold, int $maxAlerts): array
    {
        $sql = "
            SELECT 
                l.id,
                l.name,
                l.email,
                l.assigned_user_id,
                u.email as user_email,
                u.name as user_name,
                sub.last_interaction,
                DATEDIFF(NOW(), sub.last_interaction) as days_inactive,
                sub.interaction_count
            FROM leads l
            LEFT JOIN users u ON l.assigned_user_id = u.id
            LEFT JOIN (
                SELECT 
                    li.lead_id,
                    MAX(li.created_at) as last_interaction,
                    COUNT(li.id) as interaction_count
                FROM lead_interactions li
                GROUP BY li.lead_id
            ) sub ON l.id = sub.lead_id
            WHERE l.tenant_id = :tenant_id
            AND l.status NOT IN ('converted', 'lost', 'archived')
            AND (
                sub.last_interaction < DATE_SUB(NOW(), INTERVAL :days DAY)
                OR sub.last_interaction IS NULL
            )
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindParam(':days', $daysThreshold, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $maxAlerts, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveTenantIds(): array
    {
        $sql = "SELECT id FROM tenants WHERE status = 'active' OR status = 'ativo'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'id');
    }

    public function getDefaultAlertUserId(int $tenantId): int
    {
        $sql = "SELECT id FROM users WHERE tenant_id = :tenant_id AND (role = 'admin' OR role = 'superadmin') LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($user['id'] ?? 1);
    }

    public function hasRecentAlert(int $tenantId, int $userId, int $leadId): bool
    {
        $checkSql = "
            SELECT id, status FROM alerts_history 
            WHERE tenant_id = :tenant_id 
            AND user_id = :user_id
            AND alert_type = 'inactivity_lead'
            AND related_entity_id = :lead_id
            AND deleted_at IS NULL
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
            LIMIT 1
        ";

        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':lead_id' => $leadId,
        ]);

        return $checkStmt->rowCount() > 0;
    }

    public function createAlert(int $tenantId, int $userId, string $severity, string $title, string $message, int $leadId): bool
    {
        $insertSql = "
            INSERT INTO alerts_history 
            (tenant_id, user_id, alert_type, severity, title, message, related_entity_type, related_entity_id, status, created_at, updated_at)
            VALUES 
            (:tenant_id, :user_id, 'inactivity_lead', :severity, :title, :message, 'lead', :lead_id, 'novo', NOW(), NOW())
        ";

        $insertStmt = $this->pdo->prepare($insertSql);
        return $insertStmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':severity' => $severity,
            ':title' => $title,
            ':message' => $message,
            ':lead_id' => $leadId,
        ]);
    }

    public function createNotification(int $tenantId, int $userId, string $title, string $message, string $link): bool
    {
        $sql = "
            INSERT INTO notifications 
            (tenant_id, user_id, type, title, message, link, created_at)
            VALUES 
            (:tenant_id, :user_id, 'lead_inactive', :title, :message, :link, NOW())
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':title' => $title,
            ':message' => $message,
            ':link' => $link,
        ]);
    }

    public function createLeadInteraction(int $tenantId, int $leadId, ?int $userId, string $interactionType, string $title, string $description): bool
    {
        $sql = "
            INSERT INTO lead_interactions
            (lead_id, tenant_id, user_id, interaction_type, title, description, created_at)
            VALUES
            (:lead_id, :tenant_id, :user_id, :interaction_type, :title, :description, NOW())
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':lead_id' => $leadId,
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':interaction_type' => $interactionType,
            ':title' => $title,
            ':description' => $description,
        ]);
    }

    public function softDeleteAlert(int $tenantId, int $alertId): bool
    {
        $sql = "UPDATE alerts_history SET deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $alertId, ':tenant_id' => $tenantId]);
    }

    /**
     * Helper used by the PSR-4 service for the cron generator.
     */
    public function generateInactivityAlertsForTenant(int $tenantId, int $daysThreshold, int $maxAlerts, int $fallbackUserId = 1): array
    {
        $generated = 0;
        $inactiveLeads = $this->findInactiveLeads($tenantId, $daysThreshold, $maxAlerts);

        foreach ($inactiveLeads as $lead) {
            $severity = 'low';
            $daysInactive = (int)($lead['days_inactive'] ?? 0);
            if ($daysInactive < 7) $severity = 'low';
            elseif ($daysInactive < 14) $severity = 'medium';
            elseif ($daysInactive < 30) $severity = 'high';
            else $severity = 'critical';

            $userId = (int)($lead['assigned_user_id'] ?? 0) ?: $fallbackUserId;

            if ($this->hasRecentAlert($tenantId, $userId, (int)$lead['id'])) {
                continue;
            }

            $created = $this->createAlert(
                $tenantId,
                $userId,
                $severity,
                "Lead Inativo: {$lead['name']}",
                "O lead '{$lead['name']}' não recebe contato há {$lead['days_inactive']} dias",
                (int)$lead['id']
            );

            if ($created) $generated++;
        }

        return ['success' => true, 'alerts_generated' => $generated, 'leads_checked' => count($inactiveLeads)];
    }

    public function getActiveAlerts(int $tenantId, ?int $userId, int $limit): array
    {
        $sql = "
            SELECT 
                ah.id,
                ah.alert_type,
                ah.severity,
                ah.title,
                ah.message,
                ah.related_entity_id,
                ah.status,
                ah.created_at,
                l.name as lead_name,
                l.email as lead_email,
                DATEDIFF(NOW(), ah.created_at) as alert_age_days
            FROM alerts_history ah
            LEFT JOIN leads l ON ah.related_entity_id = l.id AND ah.alert_type = 'inactivity_lead'
            WHERE ah.tenant_id = :tenant_id
            AND ah.alert_type = 'inactivity_lead'
            AND (ah.deleted_at IS NULL OR ah.deleted_at = '')
            AND ah.status IN ('novo', 'acknowledged', 'reconhecido')
        ";

        if ($userId !== null) {
            $sql .= ' AND ah.user_id = :user_id';
        }

        $sql .= "
            ORDER BY 
                FIELD(ah.severity, 'critical', 'high', 'medium', 'low') ASC,
                ah.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

        if ($userId !== null) {
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function acknowledgeAlert(int $tenantId, int $alertId): bool
    {
        $sql = "UPDATE alerts_history SET status = 'acknowledged', updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $alertId, ':tenant_id' => $tenantId]);
    }

    public function resolveAlert(int $tenantId, int $alertId): bool
    {
        $sql = "UPDATE alerts_history SET status = 'resolved', updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $alertId, ':tenant_id' => $tenantId]);
    }
}
