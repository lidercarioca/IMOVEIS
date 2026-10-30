<?php

namespace App\Infrastructure\User;

use PDO;

/**
 * UserRepository — Exclusive owner of SQL access to users table
 * 
 * ============================================================================
 * OWNERSHIP & SECURITY RULES
 * ============================================================================
 * 
 * This is the ONLY class permitted to execute SQL against the users table.
 * 
 * RULE 1: All writes (INSERT/UPDATE/DELETE) must originate from:
 *   - UserService (business logic layer)
 *   - Auth flows (password reset, login)
 * 
 * RULE 2: Administrative methods (marked below) bypass tenant checks:
 *   - findByIdAnyTenant()
 *   - updatePasswordData()
 *   - These are INTERNAL methods — call only via UserService or Auth flows
 * 
 * RULE 3: No direct instantiation outside of:
 *   - UserService (dependency injection)
 *   - TenantContext (authorization checks only)
 *   - Auth controllers (password reset, login)
 * 
 * USAGE:
 * 
 * ✓ CORRECT:
 *   $service = new UserService($repository);
 *   $service->createUser($tenantId, $data);
 * 
 * ✗ INCORRECT:
 *   $repository->create(...);  // calls UserService instead
 *   $repository->findByIdAnyTenant(...);  // use findById with tenant check
 * 
 * ============================================================================
 * DEPRECATION NOTICES
 * ============================================================================
 * 
 * - updatePasswordData() is an internal auth method
 *   Used only by password reset and login flows
 *   Should NOT be called from business logic
 * 
 * - findByIdAnyTenant() is an internal admin method
 *   Used only by TenantContext for cross-tenant admin checks
 *   Do NOT bypass tenant validation in production code
 * 
 * ============================================================================
 */
class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listar usuários do tenant com filtros opcionais
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros opcionais (role, active, search)
     * @param int $limit Limite de registros
     * @param int $offset Deslocamento
     * @return array Array de usuários (sem password)
     */
    public function listUsers(int $tenantId, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = 'SELECT id, tenant_id, is_superadmin, username, role, name, email, phone, broker_default_commission_percentage, created_at, last_login, active, force_password_change, password_changed_at FROM users u';

        [$sql, $params] = $this->buildFilters($sql, $tenantId, $filters);
        $sql .= ' ORDER BY u.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar usuários do tenant com filtros opcionais
     * 
     * @param int $tenantId ID do tenant
     * @param array $filters Filtros opcionais
     * @return int Total de registros
     */
    public function countUsers(int $tenantId, array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) as total FROM users u';
        [$sql, $params] = $this->buildFilters($sql, $tenantId, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($result['total']) ? (int)$result['total'] : 0;
    }

    /**
     * Buscar usuário por ID validando tenant
     * 
     * @param int $id ID do usuário
     * @param int $tenantId ID do tenant
     * @return array|null Dados do usuário ou null se não encontrado
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, is_superadmin, username, role, name, email, phone, broker_default_commission_percentage, created_at, last_login, active, force_password_change, password_changed_at '
                . 'FROM users WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Buscar role do usuário dentro do tenant
     *
     * @param int $userId ID do usuário
     * @param int $tenantId ID do tenant
     * @return string|null Role do usuário ou null se não existir
     */
    public function getRole(int $userId, int $tenantId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT role FROM users WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $tenantId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data['role'] ?? null;
    }

    /**
     * Verificar se um usuário pertence ao tenant
     *
     * @param int $userId ID do usuário
     * @param int $tenantId ID do tenant
     * @return bool true se existir, false caso contrário
     */
    public function existsInTenant(int $userId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $tenantId]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Gate central de pertencimento entre usuário e tenant.
     *
     * Esta regra é o contrato canônico do boundary para acesso por identidade
     * autenticada. É segura para uso repetido e não depende de sessão, GET,
     * header, TenantContext ou fallback.
     *
     * @param int $userId
     * @param int $tenantId
     * @return bool
     */
    public function userBelongsToTenant(int $userId, int $tenantId): bool
    {
        if ($userId <= 0 || $tenantId <= 0) {
            return false;
        }

        return $this->existsInTenant($userId, $tenantId);
    }

    /**
     * Buscar usuários por IDs validando tenant
     *
     * @param int[] $userIds IDs dos usuários
     * @param int $tenantId ID do tenant
     * @return array
     */
    public function getUsersByIds(array $userIds, int $tenantId): array
    {
        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT id, name, email FROM users WHERE tenant_id = ? AND id IN ({$placeholders}) AND deleted_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $userIds));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar usuário da sessão atual com campos mínimos para check_login.php
     *
     * @param int $userId ID do usuário
     * @return array|null Dados do usuário ou null se não encontrado
     */
    public function findSessionUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, role, email FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Buscar usuário por username (sem validação de tenant)
     * Username é único globalmente no banco
     * Usado principalmente para login
     * 
     * @param string $username Nome de usuário
     * @return array|null Dados completos do usuário (com password) ou null
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, is_superadmin, username, password, role, name, email, created_at, last_login, active, force_password_change, password_changed_at, login_attempts, last_login_attempt '
                . 'FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Buscar usuário por email (sem validação de tenant)
     * Email é único globalmente no banco
     * 
     * @param string $email Email do usuário
     * @return array|null Dados do usuário ou null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, is_superadmin, username, role, name, email, created_at, active '
                . 'FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Buscar usuários por role e tenant
     * 
     * @param string $role Role do usuário
     * @param int $tenantId ID do tenant
     * @return array Lista de usuários
     */
    public function findByRoleAndTenant(string $role, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, username, role, name, email, active '
                . 'FROM users WHERE role = ? AND tenant_id = ?'
        );
        $stmt->execute([$role, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar usuários por role dentro do tenant (quando informado)
     * 
     * @param string $role Role do usuário
     * @param int|null $tenantId ID do tenant, quando disponível
     * @return array Lista de usuários
     */
    public function findByRole(string $role, ?int $tenantId = null): array
    {
        if ($tenantId === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, username, role, name, email, active '
                . 'FROM users WHERE role = ? AND tenant_id = ?'
        );
        $stmt->execute([$role, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar usuário por username exato e ativo
     * 
     * @param string $username Nome de usuário
     * @return array|null Dados do usuário ou null
     */
    public function findByUsernameAndActive(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, username, password, role, active, is_superadmin, force_password_change, login_attempts, last_login_attempt '
                . 'FROM users WHERE username = BINARY ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([$username]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Criar novo usuário
     * 
     * @param array $data Dados do usuário (tenant_id, username, password, role, name, email, phone, is_superadmin, active, force_password_change)
     * @return int ID do usuário criado
     */
    public function create(array $data): int
    {
        $sql = 'INSERT INTO users (tenant_id, username, password, role, name, email, phone, is_superadmin, active, force_password_change, broker_default_commission_percentage, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['tenant_id'],
            $data['username'],
            $data['password'],
            $data['role'] ?? 'user',
            $data['name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['is_superadmin'] ?? 0,
            $data['active'] ?? 1,
            $data['force_password_change'] ?? 0,
            array_key_exists('broker_default_commission_percentage', $data) ? $data['broker_default_commission_percentage'] : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Atualizar usuário
     * Sempre valida tenant_id para evitar cross-tenant update
     * 
     * @param int $id ID do usuário
     * @param int $tenantId ID do tenant
     * @param array $data Campos a atualizar (role, name, email, phone, active, force_password_change, password)
     * @return bool true se sucesso, false caso contrário
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];

        // Whitelist de campos permitidos para update
        $allowedFields = ['role', 'name', 'email', 'phone', 'active', 'force_password_change', 'password', 'broker_default_commission_percentage'];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields, true)) {
                $fields[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        // Adicionar timestamp de atualização se password foi alterada
        if (isset($data['password'])) {
            $fields[] = 'password_changed_at = NOW()';
        }

        $params[] = $id;
        $params[] = $tenantId;

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Deletar usuário
     * Sempre valida tenant_id e protege id=1 (admin principal)
     * 
     * @param int $id ID do usuário
     * @param int $tenantId ID do tenant
     * @return bool true se deletado, false caso contrário
     */
    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ? AND tenant_id = ? AND id != 1');
        return $stmt->execute([$id, $tenantId]);
    }

    /**
     * ⚠️  INTERNAL ADMIN METHOD — DO NOT CALL DIRECTLY FROM BUSINESS LOGIC
     * 
     * Registrar último login bem-sucedido
     * Atualiza metadata de autenticação (sem validação de tenant)
     * 
     * SECURITY: This method does NOT validate tenant_id
     * Called ONLY from:
     *   - Auth flows (successful login)
     * 
     * @param int $id ID do usuário
     * @return bool true se sucesso
     */
    public function updateLastLogin(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Registrar tentativa de login (falha)
     * Incrementa contador e atualiza timestamp
     * 
     * @param int $id ID do usuário
     * @return bool true se sucesso
     */
    public function registerLoginAttempt(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_attempt = NOW(), login_attempts = login_attempts + 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * ⚠️  INTERNAL AUTH METHOD — DO NOT CALL DIRECTLY FROM BUSINESS LOGIC
     * 
     * Atualizar senha e dados relacionados
     * Redefine force_password_change e atualiza timestamp
     * 
     * SECURITY: This method does NOT validate tenant_id
     * Called ONLY from:
     *   - UserService (via password reset flow)
     *   - Auth flows (password changes)
     * 
     * IMPORTANT: Password must ALREADY BE HASHED before calling this method
     * Use UserService::updateUser() or UserService::resetPassword() instead
     * 
     * @param int $id ID do usuário
     * @param string $password Hash da nova senha (must be hashed with PASSWORD_DEFAULT or PASSWORD_BCRYPT)
     * @return bool true se sucesso
     */
    public function updatePasswordData(int $id, string $password): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password = ?, password_changed_at = NOW(), force_password_change = 0 WHERE id = ?'
        );
        return $stmt->execute([$password, $id]);
    }

    public function ensurePasswordResetTableExists(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (token(64)),
            INDEX (user_id)
        )");
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function findLatestPasswordResetForUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, token, expires_at, used, created_at FROM password_resets WHERE user_id = ? AND used = 0 ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    public function createPasswordResetToken(int $userId, string $token, string $expiresAt): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
        );

        return $stmt->execute([$userId, $token, $expiresAt]);
    }

    public function findPasswordResetByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, token, expires_at, used FROM password_resets WHERE token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    public function findActivePasswordResetByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, token, expires_at, used FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    public function claimPasswordResetToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, token, expires_at, used FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    public function markPasswordResetUsed(int $passwordResetId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ? AND used = 0');
        return $stmt->execute([$passwordResetId]);
    }

    /**
     * ⚠️  INTERNAL ADMIN METHOD — DO NOT CALL DIRECTLY FROM BUSINESS LOGIC
     * 
     * Buscar usuário por ID SEM validação de tenant
     * Usado EXCLUSIVAMENTE em contextos administrativos/sistema
     * 
     * SECURITY: This method BYPASSES tenant_id check
     * This allows SUPERADMIN to manage users across tenants
     * 
     * Called ONLY from:
     *   - TenantContext (authorization checks for SUPERADMIN)
     *   - UserService (administrative operations with proper authorization checks)
     * 
     * DO NOT use this in regular business logic — use findById() instead
     * 
     * @param int $id ID do usuário
     * @return array|null Dados do usuário ou null se não encontrado
     */
    public function findByIdAnyTenant(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, is_superadmin, username, role, name, email, phone, created_at, last_login, active, force_password_change, password_changed_at '
                . 'FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Construir cláusula WHERE dinamicamente com filtros
     * Método privado para manter DRY e centralizar lógica de filtros
     * 
     * @param string $sql SQL base
     * @param int $tenantId ID obrigatório do tenant
     * @param array $filters Filtros opcionais (role, active, search)
     * @return array [sql modificado, params array]
     */
    private function buildFilters(string $sql, int $tenantId, array $filters): array
    {
        $conditions = [];
        $params = [];

        // Tenant ID é obrigatório
        $conditions[] = 'u.tenant_id = ?';
        $params[] = $tenantId;

        // Excluir contas superadmin das listagens por origem de dados.
        // Superadmin continua existindo no banco e pode ser acessado por ID,
        // mas não deve aparecer nas consultas de listagem.
        $conditions[] = 'u.is_superadmin = 0';

        // Filtro por role
        if (!empty($filters['role'])) {
            $conditions[] = 'u.role = ?';
            $params[] = $filters['role'];
        }

        // Filtro por active
        if (isset($filters['active'])) {
            $conditions[] = 'u.active = ?';
            $params[] = $filters['active'] ? 1 : 0;
        }

        // Filtro por search (nome, username, email)
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        // Filtro por is_superadmin
        if (isset($filters['is_superadmin'])) {
            $conditions[] = 'u.is_superadmin = ?';
            $params[] = $filters['is_superadmin'] ? 1 : 0;
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        return [$sql, $params];
    }
}
