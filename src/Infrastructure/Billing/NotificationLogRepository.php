<?php

namespace App\Infrastructure\Billing;

use PDO;

/**
 * NotificationLogRepository - encapsula acesso à tabela tenant_notification_log.
 */
class NotificationLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function exists(int $tenantId, string $type): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM tenant_notification_log WHERE tenant_id = ? AND tipo = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $type]);

        return (bool) $stmt->fetchColumn();
    }

    public function findLatest(int $tenantId, string $type): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, tipo, data_envio, status, created_at FROM tenant_notification_log WHERE tenant_id = ? AND tipo = ? ORDER BY data_envio DESC LIMIT 1'
        );
        $stmt->execute([$tenantId, $type]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    public function recordSend(int $tenantId, string $type, string $status = 'enviado'): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_notification_log (tenant_id, tipo, data_envio, status) VALUES (?, ?, NOW(), ?) '
                . 'ON DUPLICATE KEY UPDATE data_envio = NOW(), status = ?'
        );

        return (bool) $stmt->execute([$tenantId, $type, $status, $status]);
    }

    public function updateStatus(int $tenantId, string $type, string $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_notification_log SET status = ?, data_envio = NOW() WHERE tenant_id = ? AND tipo = ?'
        );

        return (bool) $stmt->execute([$status, $tenantId, $type]);
    }

    public function listByTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, tipo, data_envio, status, created_at '
                . 'FROM tenant_notification_log WHERE tenant_id = ? ORDER BY data_envio DESC LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
