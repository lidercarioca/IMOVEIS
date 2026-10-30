<?php

namespace App\Security;

use PDO;

/**
 * WebhookAuditLogger
 *
 * Serviço para registrar eventos críticos de webhooks sem armazenar dados sensíveis.
 */
class WebhookAuditLogger
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function logEvent(?int $tenantId, string $provider, string $eventType, string $status, ?string $ip = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO webhook_audit_logs (tenant_id, provider, event_type, status, ip_address, created_at) VALUES (:tenant_id, :provider, :event_type, :status, :ip_address, NOW())'
            );

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':provider' => substr($provider, 0, 64),
                ':event_type' => substr($eventType, 0, 64),
                ':status' => substr($status, 0, 64),
                ':ip_address' => $ip
            ]);
        } catch (\Exception $e) {
            // Não propagar erros de auditoria para não afetar processamento
            error_log('WebhookAuditLogger error: ' . $e->getMessage());
        }
    }
}
