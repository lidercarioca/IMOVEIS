<?php

namespace App\Infrastructure\PortalIntegration;

use PDO;

/**
 * PortalIntegrationRepository
 * 
 * Responsável por todas as operações de persistência da tabela portal_integrations.
 * Concentra SQL e tenant isolation filtering.
 */
class PortalIntegrationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Garante que a tabela portal_integrations existe
     */
    public function ensureTableExists(): void
    {
        $createSql = <<<SQL
CREATE TABLE IF NOT EXISTS `portal_integrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT NOT NULL,
  `portal_slug` VARCHAR(100) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `endpoint` VARCHAR(512) DEFAULT NULL,
  `credentials` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `tenant_portal` (`tenant_id`, `portal_slug`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $this->pdo->exec($createSql);
    }

    /**
     * Obtém todas as integrações de um tenant
     * 
     * @param int $tenantId
     * @return array Array com chave = portal_slug, valor = dados da integração
     */
    public function findAllByTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT portal_slug, enabled, endpoint, credentials FROM portal_integrations 
             WHERE tenant_id = :tenant_id'
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $integrations = [];
        foreach ($rows as $row) {
            $integrations[$row['portal_slug']] = [
                'enabled' => (bool)$row['enabled'],
                'endpoint' => $row['endpoint'] ?? '',
                'credentials' => $this->decodeCredentials($row['credentials']),
            ];
        }
        return $integrations;
    }

    /**
     * Obtém uma integração específica
     * 
     * @param int $tenantId
     * @param string $portalSlug
     * @return array|null Array com portal_slug, enabled, endpoint, credentials; ou null se não encontrada
     */
    public function findByTenantAndSlug(int $tenantId, string $portalSlug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT portal_slug, enabled, endpoint, credentials FROM portal_integrations 
             WHERE tenant_id = :tenant_id AND portal_slug = :portal_slug 
             LIMIT 1'
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':portal_slug' => $portalSlug,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'portal_slug' => $row['portal_slug'],
            'enabled' => (bool)$row['enabled'],
            'endpoint' => $row['endpoint'] ?? '',
            'credentials' => $this->decodeCredentials($row['credentials']),
        ];
    }

    /**
     * Salva uma integração (create ou update)
     * 
     * @param int $tenantId
     * @param string $portalSlug
     * @param bool $enabled
     * @param string|null $endpoint
     * @param array $credentials
     * @return bool
     */
    public function save(
        int $tenantId,
        string $portalSlug,
        bool $enabled,
        ?string $endpoint,
        array $credentials
    ): bool {
        $credentialsJson = json_encode($credentials, JSON_UNESCAPED_UNICODE);
        $existing = $this->findByTenantAndSlug($tenantId, $portalSlug);

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE portal_integrations 
                 SET enabled = :enabled, endpoint = :endpoint, credentials = :credentials, updated_at = NOW() 
                 WHERE tenant_id = :tenant_id AND portal_slug = :portal_slug'
            );
            return $stmt->execute([
                ':enabled' => $enabled ? 1 : 0,
                ':endpoint' => $endpoint,
                ':credentials' => $credentialsJson,
                ':tenant_id' => $tenantId,
                ':portal_slug' => $portalSlug,
            ]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO portal_integrations (tenant_id, portal_slug, enabled, endpoint, credentials) 
             VALUES (:tenant_id, :portal_slug, :enabled, :endpoint, :credentials)'
        );
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':portal_slug' => $portalSlug,
            ':enabled' => $enabled ? 1 : 0,
            ':endpoint' => $endpoint,
            ':credentials' => $credentialsJson,
        ]);
    }

    /**
     * Decodifica credentials JSON em array
     * 
     * @param string|null $credentialsText
     * @return array
     */
    public function decodeCredentials(?string $credentialsText): array
    {
        if (empty($credentialsText)) {
            return [];
        }
        $decoded = json_decode($credentialsText, true);
        if (!is_array($decoded)) {
            return [];
        }
        return $decoded;
    }

    /**
     * Mascara credentials para resposta JSON (não expõe valores sensíveis)
     * 
     * @param array $credentials
     * @return array
     */
    public function maskCredentials(array $credentials): array
    {
        $masked = [];
        foreach ($credentials as $key => $value) {
            if ($value === null || $value === '') {
                $masked[$key] = '';
                continue;
            }
            $masked[$key] = str_repeat('*', min(6, max(1, strlen($value))));
        }
        return $masked;
    }
}
