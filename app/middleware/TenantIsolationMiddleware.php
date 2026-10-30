<?php

/**
 * TenantIsolationMiddleware
 * 
 * Middleware que garante isolamento de dados por tenant em todas as operações
 * - Valida tenant_id do usuário
 * - Filtra queries automaticamente
 * - Bloqueia acesso cross-tenant (exceto superadmin)
 * 
 * @created 2026-03-23
 */

namespace App\Middleware;

use PDO;

class TenantIsolationMiddleware
{

    private static $pdo;
    private static $currentUserId;
    private static $currentTenantId;
    private static $isSuperAdmin = false;

    /**
     * Inicializa o middleware com contexto da sessão
     */
    public static function initialize(PDO $pdo)
    {
        self::$pdo = $pdo;

        // Delegar descoberta ao núcleo via adapter de compatibilidade
        if (session_status() === PHP_SESSION_NONE) {
            if (self::isHttpRequestContext() && headers_sent()) {
                throw new \RuntimeException('Não foi possível inicializar o tenant porque os headers já foram enviados antes da sessão ser iniciada.');
            }

            session_start();
        }

        require_once __DIR__ . '/../security/TenantContext.php';
        \TenantContext::initialize();

        self::$currentTenantId = \TenantContext::getTenantId();
        self::$currentUserId = $_SESSION['user_id'] ?? null;
        self::$isSuperAdmin = \TenantContext::isSuperAdmin();
    }

    /**
     * Obtém o tenant ID do contexto atual
     */
    private static function isHttpRequestContext(): bool
    {
        $hasHttpMetadata = !empty($_SERVER['REQUEST_METHOD'])
            || !empty($_SERVER['SERVER_NAME'])
            || !empty($_SERVER['HTTP_HOST'])
            || !empty($_SERVER['REMOTE_ADDR'])
            || !empty($_SERVER['SCRIPT_NAME']);

        if (!$hasHttpMetadata) {
            return false;
        }

        return PHP_SAPI !== 'cli' || (!empty($_SERVER['REQUEST_METHOD']) && (!empty($_SERVER['HTTP_HOST']) || !empty($_SERVER['SERVER_NAME']) || !empty($_SERVER['REMOTE_ADDR'])));
    }

    public static function getTenantId()
    {
        return self::$currentTenantId;
    }

    /**
     * Obtém o user ID do contexto atual
     */
    public static function getUserId()
    {
        return self::$currentUserId;
    }

    /**
     * Verifica se o usuário é superadmin
     */
    public static function isSuperAdmin()
    {
        return self::$isSuperAdmin;
    }

    /**
     * Valida se um recurso (ID) pertence ao tenant do usuário
     * 
     * @param string $table Nome da tabela
     * @param int $resourceId ID do recurso
     * @return bool true se o recurso pertence ao tenant
     */
    public static function validateResourceAccess($table, $resourceId)
    {
        // Superadmin pode acessar qualquer coisa
        if (self::$isSuperAdmin) {
            return true;
        }

        $stmt = self::$pdo->prepare(
            "SELECT id FROM {$table} WHERE id = ? AND tenant_id = ? LIMIT 1"
        );
        $stmt->execute([$resourceId, self::$currentTenantId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Filtra uma query para incluir apenas dados do tenant atual
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parâmetros da query
     * @param string|array $tables Tabelas envolvidas
     * @return array [sql corrigido, params corrigidos]
     */
    public static function filterByTenant($sql, $params = [], $tables = [])
    {
        // Superadmin não precisa filtrar (a menos que queira)
        if (self::$isSuperAdmin) {
            return [$sql, $params];
        }

        // Se não há tabelas especificadas, tenta detectar
        if (empty($tables)) {
            $tables = self::detectTablesFromSQL($sql);
        }

        // Fortalecer a query para garantir isolamento
        foreach ($tables as $table) {
            if (self::hasTenanColumn($table)) {
                if (stripos($sql, "{$table}.tenant_id") !== false) {
                    continue;
                }

                // Adicionar filtro de tenant_id apenas uma vez
                if (stripos($sql, 'WHERE') === false) {
                    $sql .= " WHERE {$table}.tenant_id = ?";
                } else {
                    $sql = preg_replace('/\bWHERE\b/i', "WHERE {$table}.tenant_id = ? AND ", $sql, 1);
                }
                $params[] = self::$currentTenantId;
            }
        }

        return [$sql, $params];
    }

    /**
     * Detecta tabelas que possuem coluna tenant_id
     */
    private static function hasTenanColumn($table)
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $tablesWithTenant = [
            'users',
            'properties',
            'leads',
            'agendamentos',
            'financeiro',
            'commissions',
            'property_sales',
            'audit_logs'
        ];

        $cache[$table] = in_array($table, $tablesWithTenant);
        return $cache[$table];
    }

    /**
     * Detecta quais tabelas estão sendo usadas na query
     */
    private static function detectTablesFromSQL($sql)
    {
        $tables = [];

        // Procura por padrões comum em SQL
        // JOIN, FROM, UPDATE, INSERT INTO
        if (preg_match_all('/(?:FROM|JOIN|INTO|UPDATE)\s+`?(\w+)`?/i', $sql, $matches)) {
            $tables = $matches[1];
        }

        return array_unique($tables);
    }

    /**
     * Valida e sanitiza um tenant_id fornecido
     * Usado para quando o usuário tenta acessar um tenant específico
     */
    public static function validateTenantAccess($targetTenantId)
    {
        if (self::$isSuperAdmin) {
            // Superadmin pode acessar qualquer tenant
            $stmt = self::$pdo->prepare("SELECT id FROM tenants WHERE id = ? LIMIT 1");
            $stmt->execute([$targetTenantId]);
            return (bool)$stmt->fetch();
        } else {
            // Usuário comum só pode acessar seu próprio tenant
            return $targetTenantId === self::$currentTenantId;
        }
    }

    /**
     * Log de acesso não autorizado
     */
    public static function logUnauthorizedAccess($resource, $resourceId, $reason = '')
    {
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, tenant_id, action_type, resource_type, resource_id, description, ip_address, user_agent, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'failed')
            ");

            $stmt->execute([
                self::$currentUserId,
                self::$currentTenantId,
                'unauthorized_access_attempt',
                $resource,
                $resourceId,
                "Tentativa de acesso não autorizado: {$reason}",
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (\Exception $e) {
            // Silenciosamente falhar - não deve quebrar a aplicação
        }
    }
}
