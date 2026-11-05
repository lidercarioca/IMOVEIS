<?php

namespace App\Middleware;

require_once __DIR__ . '/../security/TenantContext.php';

/**
 * TenantMiddleware
 * 
 * Middleware que garante:
 * 1. Isolamento de dados entre tenants
 * 2. Validação de acesso cross-tenant
 * 3. Filtragem automática de queries por tenant
 * 4. Logging de acessos suspeitos
 */
class TenantMiddleware
{

    /**
     * Aplicar middleware - deve ser chamado no início de cada requisição authenticate
     * 
     * Verifica:
     * - SUPERADMIN necessita ter selecionado um tenant
     * - Usuários normais devem ter um tenant_id associado
     * - Validações de acesso ao recurso
     */
    public static function apply()
    {
        // Inicializa contexto de tenant
        \TenantContext::initialize();

        // Se for SUPERADMIN, validar se ele tem um tenant selecionado
        if (\TenantContext::isSuperAdmin() && !checkAuth()) {
            // Isso está ok, será feito o redirect após login
            return;
        }

        // Se a requisição já requer autenticação
        if (\TenantContext::isSuperAdmin() && !\TenantContext::hasTenantSelected()) {
            // SUPERADMIN sem tenant selecionado - redirecionar para seleção
            // Isso será tratado na lógica de redirecionamento pós-login
            return;
        }

        // Se não for SUPERADMIN e não houver tenant_id, algo está errado
        if (!isLoggedIn()) {
            return;
        }

        if (!isAdmin() && !hasRole('user') && !\TenantContext::isSuperAdmin()) {
            // Usuário sem role válido
            error_log("Usuário com role inválido - ID: " . \TenantContext::getUserId());
            http_response_code(403);
            exit('Acesso negado: role inválido');
        }

        // Validar integridade da sessão de tenant
        if (!\TenantContext::hasTenantSelected() && !isAdmin() && !hasRole('user') && !\TenantContext::isSuperAdmin()) {
            http_response_code(403);
            exit('Acesso negado: selecione uma empresa para continuar');
        }

        // Se tenant foi selecionado, validar status ativo para todos (exceto superadmin)
        if (\TenantContext::hasTenantSelected() && !\TenantContext::isSuperAdmin()) {
            global $pdo;
            $tenantId = \TenantContext::getTenantId();
            try {
                $stmt = $pdo->prepare('SELECT status, name FROM tenants WHERE id = ? LIMIT 1');
                $stmt->execute([$tenantId]);
                $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$tenant || $tenant['status'] !== 'active') {
                    http_response_code(403);

                    $tenantName = $tenant['name'] ?? 'desconhecida';
                    $message = "<h1>Empresa indisponível</h1>";
                    $message .= "<p>A empresa <strong>{$tenantName}</strong> está desativada no momento.</p>";
                    $message .= "<p>Somente o Superadmin pode reativá-la em <a href='/select_tenant.php'>Selecionar empresa</a>.</p>";
                    $message .= "<p>Se você for o administrador da empresa, entre em contato com o Superadmin.</p>";

                    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Empresa inativa', 'tenant' => $tenantName]);
                    } else {
                        header('Content-Type: text/html; charset=UTF-8');
                        echo '<div style="max-width:740px; margin:60px auto; padding:30px; background:#fff3cd; color:#856404; border:1px solid #ffeeba; border-radius:10px; font-family:Arial, sans-serif;">' . $message . '</div>';
                    }
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Erro TenantMiddleware status: ' . $e->getMessage());
                http_response_code(500);
                exit('Erro interno (tenant status)');
            }
        }
    }

    /**
     * Aplicar filtro de tenant a uma query
     * 
     * Uso:
     * ```php
     * $query = "SELECT * FROM properties WHERE status = :status";
     * $query = TenantMiddleware::applyTenantFilter($query, 'properties');
     * // Resultado: "SELECT * FROM properties WHERE status = :status AND tenant_id = :tenant_id"
     * ```
     */
    public static function applyTenantFilter(string $query, string $tableName): string
    {
        if (\TenantContext::hasTenantSelected()) {
            // Adicionar filtro de tenant se a tabela tiver a coluna
            $hasTenantColumn = self::tableHasTenantColumn($tableName);

            if ($hasTenantColumn) {
                if (stripos($query, 'WHERE') !== false) {
                    // Já tem WHERE, adicionar AND
                    $query = preg_replace(
                        '/WHERE\s+/i',
                        'WHERE ' . $tableName . '.tenant_id = :__tenant_id AND ',
                        $query
                    );
                } else {
                    // Não tem WHERE, adicionar
                    $query .= ' WHERE ' . $tableName . '.tenant_id = :__tenant_id';
                }
            }
        }

        return $query;
    }

    /**
     * Obter parâmetros adicionais para a query com tenant_id
     */
    public static function getTenantParams(array $existingParams = []): array
    {
        if (\TenantContext::hasTenantSelected()) {
            $existingParams[':__tenant_id'] = \TenantContext::getTenantId();
        }
        return $existingParams;
    }

    /**
     * Verificar se uma tabela tem coluna tenant_id
     * 
     * Tabelas que devem estar "cientes" de tenant:
     * - users
     * - properties
     * - leads
     * - reports
     * - alerts
     * - etc.
     */
    private static function tableHasTenantColumn(string $tableName): bool
    {
        // Tabelas que possuem tenant_id
        $tenantAwareTables = [
            'users',
            'properties',
            'leads',
            'reports',
            'alerts',
            'commissions',
            'audit_logs',
            'superadmin_tenant_sessions'
        ];

        return in_array(strtolower($tableName), $tenantAwareTables);
    }

    /**
     * Validar acesso a um recurso específico
     * 
     * Simula uma proteção adicional para certas rotas críticas
     */
    public static function validateResourceAccess(string $resourceType, int $resourceId): bool
    {
        global $pdo;

        // Construir nome da tabela baseado no tipo
        $tableMap = [
            'property' => 'properties',
            'lead' => 'leads',
            'user' => 'users',
            'report' => 'reports'
        ];

        $table = $tableMap[$resourceType] ?? null;
        if (!$table) {
            return false;
        }

        // Se a tabela tem tenant_id, validar
        if (!self::tableHasTenantColumn($table)) {
            return true; // Tabela não usa tenant
        }

        // Verificar se o recurso pertence ao tenant atual
        $query = "SELECT id FROM {$table} WHERE id = :resource_id AND tenant_id = :tenant_id LIMIT 1";

        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':resource_id' => $resourceId,
                ':tenant_id' => \TenantContext::getTenantId()
            ]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
        } catch (\PDOException $e) {
            error_log("Erro ao validar acesso a recurso: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar tentativa de acesso indevido (possível breach)
     */
    public static function logUnauthorizedAccess(string $reason, array $context = [])
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => \TenantContext::getUserId(),
            'tenant_id' => \TenantContext::getTenantId(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'reason' => $reason,
            'context' => $context,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];

        error_log("TENANT_SECURITY_ALERT: " . json_encode($logData));
    }
}

// Aplicar middleware automaticamente em requisições authenticated
if (function_exists('isLoggedIn') && isLoggedIn()) {
    TenantMiddleware::apply();
}
