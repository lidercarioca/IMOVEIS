<?php

namespace App\Repository;

use PDO;

class SecurityMonitoringRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getOverview(int $tenantId): array
    {
        $data = [];

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count
            FROM security_anomalies
            WHERE tenant_id = :tenant_id AND detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND resolved_at IS NULL"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $data['anomalies_count'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT ip_address) as count
            FROM suspicious_ips
            WHERE tenant_id = :tenant_id AND last_attempt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $data['suspicious_ips_count'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count
            FROM blocked_ips
            WHERE tenant_id = :tenant_id AND (blocked_until IS NULL OR blocked_until > NOW())"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $data['blocked_ips_count'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count
            FROM alerts_history
            WHERE tenant_id = :tenant_id AND severity = 'CRITICAL'
            AND status != 'resolvido' AND status != 'descartado'
            AND deleted_at IS NULL"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $data['critical_alerts_count'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT id, anomaly_type, severity, username, ip_address, detected_at
            FROM security_anomalies
            WHERE tenant_id = :tenant_id AND resolved_at IS NULL
            ORDER BY detected_at DESC
            LIMIT 10"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $data['anomalies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            "SELECT si.ip_address,
                COUNT(*) as attempt_count,
                COUNT(DISTINCT si.user_id) as unique_users,
                GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') as attempted_users
            FROM suspicious_ips si
            LEFT JOIN users u ON si.user_id = u.id
            WHERE si.tenant_id = :tenant_id AND si.last_attempt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY si.ip_address
            ORDER BY attempt_count DESC
            LIMIT 10"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $data['suspicious_ips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            "SELECT ip_address, reason, blocked_at
            FROM blocked_ips
            WHERE tenant_id = :tenant_id AND (blocked_until IS NULL OR blocked_until > NOW())
            ORDER BY blocked_at DESC"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $data['blocked_ips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            "SELECT alert_type, message, severity, created_at as alert_time
            FROM alerts_history
            WHERE tenant_id = :tenant_id AND severity = 'CRITICAL'
            AND status != 'resolvido' AND status != 'descartado'
            AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT 5"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $data['alerts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    public function isBlockedForTenant(int $tenantId, string $ipAddress): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM blocked_ips WHERE tenant_id = :tenant_id AND ip_address = :ip_address AND (blocked_until IS NULL OR blocked_until > NOW()) LIMIT 1'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':ip_address' => $ipAddress,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function findBlockedForTenant(int $tenantId, string $ipAddress): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM blocked_ips WHERE tenant_id = :tenant_id AND ip_address = :ip_address ORDER BY blocked_at DESC LIMIT 1'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':ip_address' => $ipAddress,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function blockIp(int $tenantId, string $ipAddress, string $reason, ?int $blockedByUserId): array
    {
        $existing = $this->findBlockedForTenant($tenantId, $ipAddress);

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE blocked_ips SET tenant_id = :tenant_id, reason = :reason, blocked_at = NOW(), blocked_by_user_id = :blocked_by_user_id, blocked_until = NULL, permanent = 1 WHERE ip_address = :ip_address AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':reason' => $reason,
                ':blocked_by_user_id' => $blockedByUserId,
                ':ip_address' => $ipAddress,
            ]);

            return ['blocked_id' => (int) $existing['id']];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO blocked_ips (tenant_id, ip_address, reason, blocked_at, blocked_until, blocked_by_user_id, permanent) VALUES (:tenant_id, :ip_address, :reason, NOW(), NULL, :blocked_by_user_id, 1)'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':ip_address' => $ipAddress,
            ':reason' => $reason,
            ':blocked_by_user_id' => $blockedByUserId,
        ]);

        return ['blocked_id' => (int) $this->pdo->lastInsertId()];
    }

    public function unblockIp(int $tenantId, string $ipAddress): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE blocked_ips SET blocked_until = NOW(), permanent = 0 WHERE tenant_id = :tenant_id AND ip_address = :ip_address'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':ip_address' => $ipAddress,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function findOpenAnomalyByTenant(int $tenantId, int $anomalyId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, severity, anomaly_type FROM security_anomalies WHERE id = :id AND resolved = 0 AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([
            ':id' => $anomalyId,
            ':tenant_id' => $tenantId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function markAnomalyResolved(int $tenantId, int $anomalyId, int $resolvedByUserId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE security_anomalies SET resolved = 1, resolved_at = NOW(), resolved_by = :resolved_by WHERE id = :id AND tenant_id = :tenant_id AND resolved = 0'
        );
        $stmt->execute([
            ':resolved_by' => $resolvedByUserId,
            ':id' => $anomalyId,
            ':tenant_id' => $tenantId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
