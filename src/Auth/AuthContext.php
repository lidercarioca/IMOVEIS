<?php

namespace App\Auth;

/**
 * AuthContext - Contexto de autenticação global
 *
 * Armazena informações do usuário autenticado durante a requisição.
 * Usado para propagar identidade através de services/repositories.
 *
 * Padrão Singleton para facilitar acesso sem passar por toda a cadeia.
 */
class AuthContext
{
    private static ?self $instance = null;

    private ?AuthenticatedIdentity $identity = null;
    private ?int $userId = null;
    private ?string $role = null;
    private ?array $permissions = null;
    private ?string $accessToken = null;
    private bool $authenticated = false;
    private bool $isSuperAdmin = false;

    private function __construct()
    {
        // Private para forçar singleton
    }

    /**
     * Obter instância singleton
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Materializa uma identidade resolvida como facade do runtime.
     *
     * A identidade pode ser reaproveitada quando o mesmo usuário reaparece,
     * mas não pode ser trocada silenciosamente por outra identidade.
     */
    public function materializeIdentity(AuthenticatedIdentity $identity): void
    {
        if ($this->authenticated && $this->userId !== null && $this->userId !== $identity->getUserId()) {
            throw new \LogicException(sprintf(
                'AuthContext não pode trocar identidade autenticada de %d para %d.',
                $this->userId,
                $identity->getUserId()
            ));
        }

        $this->syncFromIdentity($identity);
    }

    /**
     * Inicializar contexto com token validado.
     *
     * Mantém compatibilidade com consumidores legados.
     *
     * @param int $userId
     * @param string $role
     * @param string $accessToken
     * @return void
     */
    public function authenticate(int $userId, string $role, string $accessToken = ''): void
    {
        $normalizedRole = self::normalizeRole($role);
        $rawRole = strtolower(str_replace(['_', '-', ' '], '', trim($role)));
        $isSuperAdmin = in_array($rawRole, ['superadmin'], true)
            || (!empty($_SESSION['is_superadmin']) && $normalizedRole === Roles::ADMIN);

        $result = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
            RequestAuthenticationResult::SOURCE_SESSION,
            $userId,
            $normalizedRole,
            Permissions::getPermissions($normalizedRole),
            $isSuperAdmin,
            null,
            null
        );

        $identity = AuthenticatedIdentity::fromRequestAuthenticationResult($result);
        if ($identity === null) {
            throw new \LogicException('Não foi possível materializar a identidade legada no AuthContext.');
        }

        $this->userId = $userId;
        $this->role = $normalizedRole;
        $this->accessToken = $accessToken;
        $this->authenticated = true;
        $this->isSuperAdmin = $isSuperAdmin;
        $this->permissions = Permissions::getPermissions($normalizedRole);
        $this->materializeIdentity($identity);
    }

    /**
     * Fazer logout (limpar contexto)
     *
     * @return void
     */
    public function logout(): void
    {
        $this->identity = null;
        $this->userId = null;
        $this->role = null;
        $this->accessToken = null;
        $this->authenticated = false;
        $this->isSuperAdmin = false;
        $this->permissions = null;
    }

    /**
     * Verificar se autenticado
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * Obter user ID
     *
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Obter role
     *
     * @return string|null
     */
    public function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * Obter token de acesso
     *
     * @return string|null
     */
    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Obter permissões
     *
     * @return string[]|null
     */
    public function getPermissions(): ?array
    {
        return $this->permissions;
    }

    /**
     * Verificar se tem permissão
     *
     * @param string $permission
     * @return bool
     */
    public function can(string $permission): bool
    {
        if (!$this->authenticated || $this->permissions === null) {
            return false;
        }

        return in_array($permission, $this->permissions, true);
    }

    /**
     * Verificar se é admin
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === Roles::ADMIN;
    }

    /**
     * Verificar se é superadmin
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    /**
     * Verificar se tem role
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Resetar contexto (útil para testes)
     *
     * @return void
     */
    public static function reset(): void
    {
        if (self::$instance !== null) {
            self::$instance->logout();
        }
        self::$instance = null;
    }

    private function syncFromIdentity(AuthenticatedIdentity $identity): void
    {
        $normalizedRole = self::normalizeRole($identity->getRole());

        $this->identity = $identity;
        $this->userId = $identity->getUserId();
        $this->role = $normalizedRole;
        $this->permissions = Permissions::getPermissions($normalizedRole);
        $this->authenticated = true;
        $this->isSuperAdmin = $identity->isSuperAdmin();
    }

    private static function normalizeRole(?string $role): string
    {
        $normalizedRole = strtolower(trim((string)($role ?? '')));
        $normalizedRole = str_replace(['_', '-', ' '], '', $normalizedRole);

        if (in_array($normalizedRole, ['administrator', 'superadmin', 'admin'], true)) {
            return Roles::ADMIN;
        }

        if (!Roles::isValid($normalizedRole)) {
            return Roles::GUEST;
        }

        return $normalizedRole;
    }
}
