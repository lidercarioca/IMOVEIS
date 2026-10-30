<?php
/**
 * audit_logger.php
 * 
 * Helper para registrar ações críticas em auditoria
 * Registra em auth_logs:
 * - Criação, edição, exclusão de usuários
 * - Seleção e troca de tenant
 * - Alterações em roles/permissões
 * - Acessos negados/tentativas de breach
 */

require_once __DIR__ . '/../security/TenantContext.php';

class AuditLogger {

    /**
     * Registrar ação de usuário
     */
    public static function logUserAction(
        string $action,
        int $resourceId,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $description = ''
    ): bool {
        global $pdo;

        if (!isLoggedIn()) {
            return false;
        }

        try {
            $query = "INSERT INTO audit_logs 
                      (user_id, tenant_id, action_type, resource_type, resource_id, description, old_values, new_values, ip_address, user_agent, status)
                      VALUES 
                      (:user_id, :tenant_id, :action_type, :resource_type, :resource_id, :description, :old_values, :new_values, :ip_address, :user_agent, :status)";
            
            $stmt = $pdo->prepare($query);
            return $stmt->execute([
                ':user_id' => \TenantContext::getUserId(),
                ':tenant_id' => \TenantContext::getTenantId(),
                ':action_type' => $action,
                ':resource_type' => 'user',
                ':resource_id' => $resourceId,
                ':description' => $description,
                ':old_values' => $oldValues ? json_encode($oldValues) : null,
                ':new_values' => $newValues ? json_encode($newValues) : null,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':status' => 'success'
            ]);
        } catch (\PDOException $e) {
            error_log("Erro ao registrar auditoria de usuário: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar ação de tenant
     */
    public static function logTenantAction(
        string $action,
        int $tenantId,
        string $description = ''
    ): bool {
        global $pdo;

        if (!isLoggedIn()) {
            return false;
        }

        try {
            $query = "INSERT INTO audit_logs 
                      (user_id, tenant_id, action_type, resource_type, resource_id, description, ip_address, user_agent, status)
                      VALUES 
                      (:user_id, :tenant_id, :action_type, :resource_type, :resource_id, :description, :ip_address, :user_agent, :status)";
            
            $stmt = $pdo->prepare($query);
            return $stmt->execute([
                ':user_id' => \TenantContext::getUserId(),
                ':tenant_id' => $tenantId,
                ':action_type' => $action,
                ':resource_type' => 'tenant',
                ':resource_id' => $tenantId,
                ':description' => $description,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':status' => 'success'
            ]);
        } catch (\PDOException $e) {
            error_log("Erro ao registrar auditoria de tenant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar tentativa de acesso não autorizado
     */
    public static function logUnauthorizedAccess(
        string $reason,
        string $resourceType = 'unknown',
        ?int $resourceId = null
    ): bool {
        global $pdo;

        try {
            $query = "INSERT INTO audit_logs 
                      (user_id, tenant_id, action_type, resource_type, resource_id, description, ip_address, user_agent, status)
                      VALUES 
                      (:user_id, :tenant_id, :action_type, :resource_type, :resource_id, :description, :ip_address, :user_agent, :status)";
            
            $stmt = $pdo->prepare($query);
            return $stmt->execute([
                ':user_id' => isLoggedIn() ? \TenantContext::getUserId() : null,
                ':tenant_id' => isLoggedIn() ? \TenantContext::getTenantId() : null,
                ':action_type' => 'unauthorized_access',
                ':resource_type' => $resourceType,
                ':resource_id' => $resourceId,
                ':description' => "ALERTA DE SEGURANÇA: {$reason}",
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':status' => 'failed'
            ]);
        } catch (\PDOException $e) {
            error_log("Erro ao registrar tentativa não autorizada: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar tentativa de operação cross-tenant
     */
    public static function logCrossTenantAttempt(
        int $targetTenantId,
        string $action
    ): bool {
        global $pdo;

        try {
            $query = "INSERT INTO audit_logs 
                      (user_id, tenant_id, action_type, resource_type, resource_id, description, ip_address, user_agent, status)
                      VALUES 
                      (:user_id, :tenant_id, :action_type, :resource_type, :resource_id, :description, :ip_address, :user_agent, :status)";
            
            $stmt = $pdo->prepare($query);
            return $stmt->execute([
                ':user_id' => isLoggedIn() ? \TenantContext::getUserId() : null,
                ':tenant_id' => isLoggedIn() ? \TenantContext::getTenantId() : null,
                ':action_type' => 'cross_tenant_attempt',
                ':resource_type' => 'tenant',
                ':resource_id' => $targetTenantId,
                ':description' => "ALERTA DE SEGURANÇA: Tentativa de acesso cross-tenant (ação: {$action})",
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':status' => 'failed'
            ]);
        } catch (\PDOException $e) {
            error_log("Erro ao registrar tentativa cross-tenant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter logs de auditoria de um tenant
     */
    public static function getTenantAuditLogs(int $tenantId, array $filters = []): array {
        global $pdo;

        $query = "SELECT * FROM audit_logs WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        // Filtro por tipo de ação (opcional)
        if (!empty($filters['action_type'])) {
            $query .= " AND action_type = :action_type";
            $params[':action_type'] = $filters['action_type'];
        }

        // Filtro por status (opcional)
        if (!empty($filters['status'])) {
            $query .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        // Filtro por data (opcional)
        if (!empty($filters['from_date'])) {
            $query .= " AND created_at >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $query .= " AND created_at <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }

        $query .= " ORDER BY created_at DESC LIMIT 100";

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erro ao obter logs de auditoria: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obter resumo de acessos do usuário
     */
    public static function getUserActionSummary(int $userId, int $days = 30): array {
        global $pdo;

        $query = "SELECT 
                    action_type, 
                    COUNT(*) as count,
                    MAX(created_at) as last_action
                  FROM audit_logs 
                  WHERE user_id = :user_id 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  GROUP BY action_type
                  ORDER BY count DESC";

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':days' => $days
            ]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erro ao obter resumo de ações: " . $e->getMessage());
            return [];
        }
    }
}
