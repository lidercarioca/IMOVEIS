<?php
/**
 * Tenant Isolation Helpers
 * 
 * Funções globais para validar tenant_id em queries da API
 * 
 * @created 2026-03-23
 */

require_once __DIR__ . '/../middleware/TenantIsolationMiddleware.php';

use App\Middleware\TenantIsolationMiddleware as TenantMiddleware;

/**
 * Inicializa o middleware de isolamento
 */
function initializeTenantMiddleware($pdo) {
    TenantMiddleware::initialize($pdo);
}

/**
 * Obtém o tenant ID do usuário autenticado
 */
function getCurrentTenantId() {
    return TenantMiddleware::getTenantId();
}

/**
 * Obtém o user ID
 */
function getCurrentUserId() {
    return TenantMiddleware::getUserId();
}

/**
 * Verifica se é superadmin
 */
function isSuperAdmin() {
    return TenantMiddleware::isSuperAdmin();
}

/**
 * Valida acesso a um recurso específico
 */
function canAccessResource($table, $resourceId) {
    if (!TenantMiddleware::validateResourceAccess($table, $resourceId)) {
        TenantMiddleware::logUnauthorizedAccess($table, $resourceId, 'Resource not in tenant');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
}

/**
 * Filtra query por tenant_id
 * 
 * Uso:
 *   $sql = "SELECT * FROM properties WHERE status = 'active'";
 *   list($sql, $params) = filterByTenantId($sql, $params, 'properties');
 */
function filterByTenantId($sql, $params = [], $tables = []) {
    return TenantMiddleware::filterByTenant($sql, $params, $tables);
}

/**
 * Helper para adicionar filtro de tenant_id em uma condição WHERE
 * 
 * Uso:
 *   $sql = "SELECT * FROM properties";
 *   $sql .= addTenantIdFilter('properties'); // Adiciona " WHERE properties.tenant_id = ?"
 */
function addTenantIdFilter($table, $params = []) {
    if (isSuperAdmin()) {
        return ['', $params]; // Superadmin vê tudo
    }
    return [" AND {$table}.tenant_id = ?", array_merge($params, [getCurrentTenantId()])];
}

/**
 * Wrapper seguro para queries PDO
 */
function executeWithTenant($pdo, $sql, $params = []) {
    if (!isSuperAdmin()) {
        list($sql, $params) = filterByTenantId($sql, $params);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Valida que um usuário é admin ou superadmin
 */
function requireAdminOrSuperAdmin() {
    // Usar isAdmin() que já existe em session_manager.php
    // isAdmin() retorna true para 'admin', 'administrator' ou 'superadmin'
    if (!isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }
    
    $role = strtolower(trim($_SESSION['role']));
    if ($role !== 'admin' && $role !== 'administrator' && $role !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Apenas admins podem realizar esta ação']);
        exit;
    }
}

/**
 * Valida que apenas superadmin pode fazer algo
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Apenas superadmin pode realizar esta ação']);
        exit;
    }
}

?>
