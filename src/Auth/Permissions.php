<?php
namespace App\Auth;

/**
 * Permissions - Mapeamento de permissões por role
 *
 * Define explicitamente o que cada role pode fazer.
 */
class Permissions
{
    public const CREATE_PROPERTY = 'create_property';
    public const UPDATE_PROPERTY = 'update_property';
    public const DELETE_PROPERTY = 'delete_property';
    public const MANAGE_USERS = 'manage_users';
    public const VIEW_DASHBOARD = 'view_dashboard';
    public const VIEW_PROPERTY = 'view_property';

    // Compatibilidade com nomes mais antigos
    public const PROPERTY_CREATE = self::CREATE_PROPERTY;
    public const PROPERTY_EDIT = self::UPDATE_PROPERTY;
    public const PROPERTY_DELETE = self::DELETE_PROPERTY;
    public const USER_MANAGE = self::MANAGE_USERS;
    public const ADMIN_SETTINGS = self::VIEW_DASHBOARD;
    public const PROPERTY_VIEW = self::VIEW_PROPERTY;

    private const ROLE_PERMISSIONS = [
        Roles::ADMIN => [
            self::CREATE_PROPERTY,
            self::UPDATE_PROPERTY,
            self::DELETE_PROPERTY,
            self::MANAGE_USERS,
            self::VIEW_DASHBOARD,
            self::VIEW_PROPERTY,
        ],
        Roles::MANAGER => [
            self::CREATE_PROPERTY,
            self::UPDATE_PROPERTY,
            self::DELETE_PROPERTY,
            self::MANAGE_USERS,
            self::VIEW_DASHBOARD,
            self::VIEW_PROPERTY,
        ],
        Roles::USER => [
            self::VIEW_PROPERTY,
            self::CREATE_PROPERTY,
            self::VIEW_DASHBOARD,
        ],
        Roles::GUEST => [
            self::VIEW_PROPERTY,
        ],
    ];

    public static function can(string $role, string $permission): bool
    {
        if (!Roles::isValid($role)) {
            return false;
        }

        return in_array($permission, self::ROLE_PERMISSIONS[$role] ?? [], true);
    }

    public static function canAll(string $role, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!self::can($role, $permission)) {
                return false;
            }
        }

        return true;
    }

    public static function canAny(string $role, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($role, $permission)) {
                return true;
            }
        }

        return false;
    }

    public static function getPermissions(string $role): array
    {
        $normalizedRole = strtolower(trim($role));

        // Compatibilidade com aliases de role que aparecem no código legado.
        if (in_array($normalizedRole, ['administrator', 'superadmin'], true)) {
            $normalizedRole = Roles::ADMIN;
        }

        return self::ROLE_PERMISSIONS[$normalizedRole] ?? [];
    }

    public static function getRolesWithPermission(string $permission): array
    {
        $roles = [];

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            if (in_array($permission, $permissions, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
