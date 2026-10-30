<?php
namespace App\Auth;

/**
 * AuthorizationService - Validação centralizada de RBAC
 *
 * Este serviço centraliza verificações de permissão e role para
 * o backend, garantindo que toda lógica sensível seja validada
 * no server-side, independente do frontend.
 */
class AuthorizationService
{
    private AuthContext $context;

    public function __construct(?AuthContext $context = null)
    {
        $this->context = $context ?? AuthContext::getInstance();
    }

    /**
     * Verifica se o usuário está autenticado.
     *
     * @return void
     * @throws \RuntimeException
     */
    public function requireAuthenticated(): void
    {
        if (!$this->context->isAuthenticated()) {
            throw new \RuntimeException('Não autenticado');
        }
    }

    /**
     * Verifica permissão específica.
     *
     * @param string $permission
     * @return void
     * @throws \RuntimeException
     */
    public function requirePermission(string $permission): void
    {
        $this->requireAuthenticated();

        if (!$this->context->can($permission)) {
            throw new \RuntimeException('Acesso negado');
        }
    }

    /**
     * Verifica se o usuário possui qualquer uma das permissões.
     *
     * @param string[] $permissions
     * @return void
     * @throws \RuntimeException
     */
    public function requireAnyPermission(array $permissions): void
    {
        $this->requireAuthenticated();

        foreach ($permissions as $permission) {
            if ($this->context->can($permission)) {
                return;
            }
        }

        throw new \RuntimeException('Acesso negado');
    }

    /**
     * Verifica role mínima.
     *
     * @param string $role
     * @return void
     * @throws \RuntimeException
     */
    public function requireRole(string $role): void
    {
        $this->requireAuthenticated();

        if (!$this->context->hasRole($role)) {
            throw new \RuntimeException('Acesso negado');
        }
    }

    /**
     * Retorna se o usuário tem permissão específica.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return $this->context->isAuthenticated() && $this->context->can($permission);
    }
}
