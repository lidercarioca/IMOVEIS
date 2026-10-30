<?php

namespace App\Application\User;

use App\Auth\AuthContext;
use App\Auth\Roles;
use App\Infrastructure\User\UserRepository;
use RuntimeException;
use InvalidArgumentException;

class UserService
{
    private UserRepository $repository;
    private ?UserAuditService $auditService;

    public function __construct(UserRepository $repository, ?UserAuditService $auditService = null)
    {
        $this->repository = $repository;
        $this->auditService = $auditService;
    }

    public function listUsers(int $tenantId, array $filters = [], int $page = 1, int $limit = 50): array
    {
        $limit = $this->normalizeLimit($limit);
        $page = max(1, $page);
        $offset = max(0, ($page - 1) * $limit);

        $total = $this->repository->countUsers($tenantId, $filters);
        $data = $this->repository->listUsers($tenantId, $filters, $limit, $offset);

        $pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

        return [
            'data' => array_map([$this, 'sanitizeUserResponse'], $data),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ];
    }

    public function getUserById(int $id, int $tenantId): array
    {
        $user = $this->repository->findById($id, $tenantId);

        if ($user === null) {
            throw new RuntimeException('Usuário não encontrado');
        }

        return $this->sanitizeUserResponse($user);
    }

    public function authenticateUser(string $username, string $password): ?array
    {
        $user = $this->repository->findByUsernameAndActive($username);

        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            $this->recordLoginFailure((int)$user['id']);
            return null;
        }

        // Automatic password hash upgrade on login
        // Rehash password if it doesn't meet current hash standard
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->repository->updatePasswordData((int)$user['id'], $newHash);
            error_log("[UserService] Password hash upgraded for user ID: " . $user['id']);
        }

        $this->recordLoginSuccess((int)$user['id']);

        $response = $this->sanitizeUserResponse($user);
        $response['is_superadmin'] = isset($user['is_superadmin']) ? (int)$user['is_superadmin'] : 0;

        return $response;
    }

    public function recordLoginSuccess(int $userId): bool
    {
        return $this->repository->updateLastLogin($userId);
    }

    public function recordLoginFailure(int $userId): bool
    {
        return $this->repository->registerLoginAttempt($userId);
    }

    public function findAdminsByTenant(int $tenantId): array
    {
        return $this->repository->findByRoleAndTenant('admin', $tenantId);
    }

    public function createUser(int $tenantId, array $data, bool $system_creation = false): array
    {
        $this->validateRequiredFields($data, ['username', 'name', 'email', 'password']);
        $this->validateUsername($data['username']);
        $this->validateEmail($data['email']);

        if ($this->repository->findByUsername($data['username']) !== null) {
            throw new RuntimeException('Username já está em uso');
        }

        if ($this->repository->findByEmail($data['email']) !== null) {
            throw new RuntimeException('Email já está em uso');
        }

        $role = $data['role'] ?? 'user';

        // Permissões de criação (aplicadas no serviço):
        // - Superadmin: pode criar qualquer role
        // - Admin: pode criar apenas usuários com role 'user'
        // - Usuário comum: não pode criar usuários
        // - system_creation: bypass de RBAC (usado durante onboarding)
        if (!$system_creation) {
            if (!$this->currentUserIsAdmin()) {
                throw new RuntimeException('Permissão negada para criar usuário');
            }

            // Se é admin, só permite criar role 'user'.
            // O flag de superadmin continua vindo do estado autenticado centralizado.
            if (!$this->currentUserIsSuperAdmin() && $role !== 'user') {
                throw new RuntimeException('Apenas superadmin pode criar usuário com role diferente de user');
            }
        }

        if (array_key_exists('broker_default_commission_percentage', $data)) {
            $this->validateBrokerDefaultCommissionPercentage($data['broker_default_commission_percentage']);
        }

        $payload = [
            'tenant_id' => $tenantId,
            'username' => trim($data['username']),
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $role,
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'phone' => $data['phone'] ?? null,
            'active' => isset($data['active']) ? (int)(bool)$data['active'] : 1,
            'is_superadmin' => $role === 'superadmin' ? 1 : 0,
            'force_password_change' => isset($data['force_password_change']) ? (int)(bool)$data['force_password_change'] : 0,
            'broker_default_commission_percentage' => array_key_exists('broker_default_commission_percentage', $data)
                ? $data['broker_default_commission_percentage']
                : null,
        ];

        $userId = $this->repository->create($payload);
        $createdUser = $this->getUserById($userId, $tenantId);

        $this->emitUserAudit(
            $userId,
            [],
            $createdUser,
            'create',
            $tenantId,
            'Usuário criado'
        );

        return $createdUser;
    }

    public function updateUser(int $id, int $tenantId, array $data): array
    {
        $user = $this->repository->findById($id, $tenantId);

        if ($user === null) {
            throw new RuntimeException('Usuário não encontrado');
        }

        if (!$this->canManageUser($id)) {
            throw new RuntimeException('Permissão negada para atualizar este usuário');
        }

        $currentUser = $user;
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }

        if (isset($data['email'])) {
            $this->validateEmail($data['email']);
            $existing = $this->repository->findByEmail($data['email']);
            if ($existing !== null && $existing['id'] !== $id) {
                throw new RuntimeException('Email já está em uso');
            }
            $updateData['email'] = trim($data['email']);
        }

        if (array_key_exists('broker_default_commission_percentage', $data)) {
            $this->validateBrokerDefaultCommissionPercentage($data['broker_default_commission_percentage']);
            $updateData['broker_default_commission_percentage'] = $data['broker_default_commission_percentage'];
        }

        if (array_key_exists('phone', $data)) {
            $updateData['phone'] = $data['phone'] ?? null;
        }

        if (isset($data['password']) && $data['password'] !== '') {
            $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (array_key_exists('broker_default_commission_percentage', $data)) {
            if (!$this->currentUserIsAdmin()) {
                throw new InvalidArgumentException('Apenas admin ou superadmin pode alterar a comissão padrão do corretor');
            }

            $this->validateBrokerDefaultCommissionPercentage($data['broker_default_commission_percentage']);
            $updateData['broker_default_commission_percentage'] = $data['broker_default_commission_percentage'];
        }

        if (isset($data['active'])) {
            if (!$this->currentUserIsAdmin()) {
                throw new RuntimeException('Apenas admin ou superadmin pode alterar o status ativo');
            }

            $updateData['active'] = (int)(bool)$data['active'];
        }

        // Apenas Superadmin pode alterar a role.
        // Para Admin e usuários comuns, o campo é simplesmente ignorado.
        if ($this->currentUserIsSuperAdmin() && isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }

        unset($data['id']);

        if (
            array_key_exists('tenant_id', $data) ||
            array_key_exists('username', $data) ||
            array_key_exists('is_superadmin', $data)
        ) {
            throw new InvalidArgumentException('Campos tenant_id, username e is_superadmin não podem ser alterados');
        }

        if (!empty($updateData)) {
            $this->repository->update($id, $tenantId, $updateData);
        }

        $updatedUser = $this->getUserById($id, $tenantId);

        $oldValues = [];
        if (isset($user['name'])) {
            $oldValues['name'] = $user['name'];
        }

        $newValues = [];
        if (isset($updatedUser['name'])) {
            $newValues['name'] = $updatedUser['name'];
        }

        $this->emitUserAudit(
            $id,
            $oldValues,
            $newValues,
            'update',
            $tenantId,
            'Usuário atualizado'
        );

        return $updatedUser;
    }

    public function deleteUser(int $id, int $tenantId): bool
    {
        if ($id === 1) {
            throw new RuntimeException('Não é permitido excluir o usuário administrador principal');
        }

        $user = $this->repository->findById($id, $tenantId);
        if ($user === null) {
            throw new RuntimeException('Usuário não encontrado');
        }

        if (!$this->canManageUser($id)) {
            throw new RuntimeException('Permissão negada para excluir este usuário');
        }

        $deleted = $this->repository->delete($id, $tenantId);

        if ($deleted) {
            $this->emitUserAudit(
                $id,
                $user,
                [],
                'delete',
                $tenantId,
                'Usuário removido'
            );
        }

        return $deleted;
    }

    private function emitUserAudit(int $targetUserId, array $oldValues, array $newValues, string $actionType, int $tenantId, ?string $description = null): void
    {
        if ($this->auditService === null) {
            return;
        }

        $context = AuthContext::getInstance();
        $actorUserId = $context->isAuthenticated() ? $context->getUserId() : null;

        if ($actorUserId === null || $actorUserId <= 0 || $tenantId <= 0) {
            return;
        }

        $this->auditService->logUserChange(
            $targetUserId,
            $oldValues,
            $newValues,
            $actionType,
            $actorUserId,
            $tenantId,
            $description ?? sprintf('Usuário %s', $actionType),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            'success'
        );
    }

    private function currentUserIsAdmin(): bool
    {
        $context = AuthContext::getInstance();

        if (!$context->isAuthenticated()) {
            return false;
        }

        return $context->isAdmin() || $context->isSuperAdmin();
    }

    private function currentUserIsSuperAdmin(): bool
    {
        $context = AuthContext::getInstance();

        if (!$context->isAuthenticated()) {
            return false;
        }

        return $context->isSuperAdmin();
    }

    private function canManageUser(int $targetUserId): bool
    {
        $context = AuthContext::getInstance();

        if (!$context->isAuthenticated()) {
            return false;
        }

        if ($this->currentUserIsAdmin()) {
            return true;
        }

        return (int)$context->getUserId() === $targetUserId;
    }

    private function validateRequiredFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($data[$field]) || !is_string($data[$field])) {
                throw new InvalidArgumentException("Campo obrigatório ausente ou inválido: {$field}");
            }
        }
    }

    private function validateUsername(string $username): void
    {
        $username = trim($username);

        if (strlen($username) < 3) {
            throw new InvalidArgumentException('Username deve ter pelo menos 3 caracteres');
        }
    }

    private function validateEmail(string $email): void
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email inválido');
        }
    }

    private function validateBrokerDefaultCommissionPercentage($value): void
    {
        if ($value === null) {
            return;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException('broker_default_commission_percentage deve ser numérico');
        }

        $numericValue = (float)$value;

        if ($numericValue < 0 || $numericValue > 100) {
            throw new InvalidArgumentException('broker_default_commission_percentage deve estar entre 0 e 100');
        }
    }

    private function sanitizeUserResponse(array $user): array
    {
        unset($user['password']);

        return [
            'id' => $user['id'] ?? null,
            'tenant_id' => $user['tenant_id'] ?? null,
            'username' => $user['username'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'phone' => $user['phone'] ?? null,
            'broker_default_commission_percentage' => array_key_exists('broker_default_commission_percentage', $user)
                ? $user['broker_default_commission_percentage']
                : null,
            'role' => $user['role'] ?? null,
            'active' => isset($user['active']) ? (int)$user['active'] : null,
            'created_at' => $user['created_at'] ?? null,
            'last_login' => $user['last_login'] ?? null,
            'force_password_change' => isset($user['force_password_change']) ? (int)$user['force_password_change'] : null,
            'password_changed_at' => $user['password_changed_at'] ?? null,
        ];
    }

    private function normalizeLimit(int $limit): int
    {
        if ($limit < 1) {
            return 1;
        }

        return min($limit, 1000);
    }

    /**
     * Reset password by user id using already-hashed password.
     *
     * IMPORTANT: This method receives the hashed password and delegates
     * persistence to the repository. It intentionally does NOT perform
     * tenant checks or manipulate password_resets table in this LOTE.
     *
     * @param int $userId
     * @param string $passwordHash
     * @return bool
     */
    public function requestPasswordReset(string $email): ?array
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail inválido');
        }

        $user = $this->repository->findByEmail($email);
        if ($user === null) {
            return null;
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $lastReset = $this->repository->findLatestPasswordResetForUser($userId);
        if ($lastReset !== null) {
            $lastTime = strtotime($lastReset['created_at'] ?? 'now');
            if ($lastTime !== false && time() - $lastTime < 900) {
                return null;
            }
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $created = $this->repository->createPasswordResetToken($userId, $token, $expiresAt);
        if (!$created) {
            throw new RuntimeException('Não foi possível gerar o token de redefinição de senha');
        }

        return [
            'user_id' => $userId,
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function validatePasswordResetToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $record = $this->repository->findPasswordResetByToken($token);
        if ($record === null) {
            return null;
        }

        if (!empty($record['used'])) {
            return null;
        }

        if (strtotime($record['expires_at']) < time()) {
            return null;
        }

        return $record;
    }

    public function getPasswordResetStatus(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return 'invalid';
        }

        $record = $this->repository->findPasswordResetByToken($token);
        if ($record === null) {
            return 'invalid';
        }

        if (!empty($record['used'])) {
            return 'used';
        }

        if (strtotime($record['expires_at']) < time()) {
            return 'expired';
        }

        return 'valid';
    }

    public function canDisplayPasswordResetForm(string $token): bool
    {
        return $this->getPasswordResetStatus($token) === 'valid';
    }

    public function completePasswordReset(string $token, string $newPassword): bool
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('Token de redefinição inválido');
        }

        $this->repository->beginTransaction();

        try {
            $record = $this->repository->claimPasswordResetToken($token);
            if ($record === null) {
                $this->repository->rollback();
                return false;
            }

            $userId = (int)($record['user_id'] ?? 0);
            if ($userId <= 0) {
                $this->repository->rollback();
                throw new RuntimeException('Usuário inválido para redefinição de senha');
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updated = $this->repository->updatePasswordData($userId, $hash);
            if (!$updated) {
                $this->repository->rollback();
                return false;
            }

            $marked = $this->repository->markPasswordResetUsed((int)$record['id']);
            if (!$marked) {
                $this->repository->rollback();
                return false;
            }

            $this->repository->commit();
            return true;
        } catch (\Throwable $throwable) {
            $this->repository->rollback();
            throw $throwable;
        }
    }

    public function resetPasswordByUserId(int $userId, string $passwordHash): bool
    {
        return $this->repository->updatePasswordData($userId, $passwordHash);
    }
}
