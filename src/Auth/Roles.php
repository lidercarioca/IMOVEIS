<?php
namespace App\Auth;

/**
 * Roles - Definição de papéis do sistema
 *
 * Papéis disponíveis:
 * - admin: acesso total ao sistema
 * - manager: gerencia propriedades e usuários
 * - user: acesso limitado a propriedades
 * - guest: sem autenticação
 */
class Roles
{
    // Definição de papéis
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const USER = 'user';
    public const GUEST = 'guest';

    // Todos os papéis válidos
    public const ALL_ROLES = [
        self::ADMIN,
        self::MANAGER,
        self::USER,
        self::GUEST,
    ];

    // Hierarquia de papéis (para verificações simples)
    private const HIERARCHY = [
        self::ADMIN => 3,      // Maior nível
        self::MANAGER => 2,
        self::USER => 1,
        self::GUEST => 0,      // Menor nível
    ];

    /**
     * Verificar se role é válido
     *
     * @param string $role
     * @return bool
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::ALL_ROLES, true);
    }

    /**
     * Verificar se um role tem mais privilégios que outro
     *
     * Exemplo: isHigherThan('admin', 'user') → true
     *
     * @param string $role1
     * @param string $role2
     * @return bool
     */
    public static function isHigherThan(string $role1, string $role2): bool
    {
        return (self::HIERARCHY[$role1] ?? -1) > (self::HIERARCHY[$role2] ?? -1);
    }

    /**
     * Verificar se um role é at least o especificado
     *
     * Exemplo: isAtLeast('manager', 'user') → true
     *
     * @param string $userRole
     * @param string $minimumRole
     * @return bool
     */
    public static function isAtLeast(string $userRole, string $minimumRole): bool
    {
        return (self::HIERARCHY[$userRole] ?? -1) >= (self::HIERARCHY[$minimumRole] ?? -1);
    }

    /**
     * Obter nível de um role (para comparações)
     *
     * @param string $role
     * @return int
     */
    public static function getLevel(string $role): int
    {
        return self::HIERARCHY[$role] ?? -1;
    }
}
