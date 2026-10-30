<?php

use App\Auth\AuthContext;
use App\Infrastructure\User\UserRepository;
use App\Tenant\TenantContext as CoreTenantContext;
use App\Tenant\TenantResolver;
use App\Tenant\TenantResolution;

/**
 * Legacy compatibility wrapper over the new Tenant core.
 */
class TenantContext
{
    private static ?CoreTenantContext $context = null;

    public static function initialize(): void
    {
        if (self::$context !== null) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (PHP_SAPI === 'cli' || headers_sent()) {
                $_SESSION = $_SESSION ?? [];
            } else {
                if (self::isHttpRequestContext() && headers_sent()) {
                    throw new \RuntimeException('Não foi possível inicializar o contexto de tenant porque os headers já foram enviados antes da sessão ficar ativa.');
                }

                session_start();
            }
        }

        // O resolver só pode usar o tenant do usuário autenticado quando o
        // AuthContext reflete a sessão atual. Sem isso, fluxos legados que
        // carregam apenas session_manager poderiam cair no fallback público.
        //
        // Segurança adicional: não sobrescrever um AuthContext já autenticado
        // (por exemplo, materializado por AuthMiddleware a partir de um JWT).
        // Se a identidade já existir, preservamos a identidade atual e NÃO
        // reautenticamos a partir de $_SESSION. Caso contrário, mantemos o
        // comportamento legado e materializamos a identidade via sessão.
        if (!empty($_SESSION['user_id'])) {
            if (!AuthContext::getInstance()->isAuthenticated()) {
                AuthContext::getInstance()->authenticate(
                    (int) $_SESSION['user_id'],
                    (string) ($_SESSION['role'] ?? 'user')
                );
            }
        }

        $resolver = new TenantResolver();
        $resolution = $resolver->resolve([
            'session' => $_SESSION,
            'query' => $_GET,
            'server' => $_SERVER,
            'headers' => self::extractHeaders($_SERVER),
        ]);

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $role = $_SESSION['role'] ?? null;
        $selectedTenantId = isset($_SESSION['selected_tenant_id']) ? (int) $_SESSION['selected_tenant_id'] : null;
        $isSuperAdmin = !empty($_SESSION['is_superadmin']) || $resolution->isSuperAdmin();

        self::$context = CoreTenantContext::fromResolution(
            $resolution,
            $userId,
            $role,
            $isSuperAdmin,
            $selectedTenantId
        );

        // Espelhar o tenant resolvido na sessão e em $_SERVER para compatibilidade
        if ($resolution->getTenantId() !== null) {
            $_SERVER['TENANT_ID'] = $resolution->getTenantId();
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['tenant_id'] = $resolution->getTenantId();
            }
        } else {
            // Garantir que não haja valor obsoleto na sessão
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['tenant_id'])) {
                unset($_SESSION['tenant_id']);
            }
        }
    }

    public static function getTenantId(): ?int
    {
        self::initialize();
        return self::$context?->getTenantId();
    }

    public static function getUserId(): ?int
    {
        self::initialize();

        if (self::$context !== null && self::$context->getUserId() !== null) {
            return self::$context->getUserId();
        }

        $userId = $_SESSION['user_id'] ?? AuthContext::getInstance()->getUserId();
        return is_numeric($userId) ? (int) $userId : null;
    }

    public static function getRole(): ?string
    {
        self::initialize();

        if (self::$context !== null && self::$context->getRole() !== null) {
            return self::$context->getRole();
        }

        return $_SESSION['role'] ?? AuthContext::getInstance()->getRole();
    }

    public static function getSelectedTenantId(): ?int
    {
        self::initialize();
        return self::$context?->getSelectedTenant();
    }

    public static function hasTenantSelected(): bool
    {
        self::initialize();
        return self::$context !== null && self::$context->getTenantId() !== null;
    }

    public static function getRequestTenantId(): ?int
    {
        self::initialize();
        return self::$context?->getResolution()->getMetadata()['request_tenant_id'] ?? null;
    }

    public static function isSuperAdmin(): bool
    {
        self::initialize();
        return self::$context?->isSuperAdmin() ?? false;
    }

    public static function canManageUser(int $targetUserId, string $action = 'read'): bool
    {
        self::initialize();

        $currentUserId = self::getUserId();
        if ($currentUserId === null || $currentUserId <= 0) {
            return false;
        }

        $tenantId = self::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return false;
        }

        if (!AuthContext::getInstance()->isAuthenticated()) {
            return false;
        }

        if (self::isSuperAdmin() || AuthContext::getInstance()->isAdmin()) {
            return true;
        }

        return $currentUserId === $targetUserId;
    }

    public static function isAdmin(): bool
    {
        self::initialize();
        return self::isSuperAdmin() || AuthContext::getInstance()->isAdmin();
    }

    public static function getResolutionSource(): string
    {
        self::initialize();
        return self::$context->getResolution()->getResolvedBy();
    }

    public static function selectTenant(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (PHP_SAPI === 'cli' || headers_sent()) {
                $_SESSION = $_SESSION ?? [];
            } else {
                if (self::isHttpRequestContext() && headers_sent()) {
                    throw new \RuntimeException('Não foi possível selecionar o tenant porque os headers já foram enviados antes da sessão ficar ativa.');
                }

                session_start();
            }
        }

        $userId = $_SESSION['user_id'] ?? AuthContext::getInstance()->getUserId();
        $userId = is_numeric($userId) ? (int) $userId : null;

        if ($userId === null || $userId <= 0) {
            return false;
        }

        $isExplicitSuperAdmin = !empty($_SESSION['is_superadmin']);
        if (!$isExplicitSuperAdmin && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
            $repository = new UserRepository($GLOBALS['pdo']);
            $user = $repository->findByIdAnyTenant($userId);
            $isExplicitSuperAdmin = !empty($user['is_superadmin']);
        }

        if (!$isExplicitSuperAdmin) {
            if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof \PDO)) {
                return false;
            }

            $repository = new UserRepository($GLOBALS['pdo']);
            if (!$repository->userBelongsToTenant($userId, $tenantId)) {
                return false;
            }
        }

        $_SESSION['selected_tenant_id'] = $tenantId;
        $_SESSION['tenant_id'] = $tenantId;
        $_SERVER['TENANT_ID'] = $tenantId;

        if (self::$context !== null) {
            self::$context = CoreTenantContext::fromResolution(
                new TenantResolution(
                    $tenantId,
                    TenantResolver::SOURCE_SUPERADMIN_SELECTED,
                    1.0,
                    false,
                    self::$context->isSuperAdmin(),
                    false,
                    TenantResolver::SOURCE_SUPERADMIN_SELECTED,
                    self::$context->getResolution()->getMetadata()
                ),
                self::$context->getUserId(),
                self::$context->getRole(),
                self::$context->isSuperAdmin(),
                $tenantId
            );
        }

        return true;
    }

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

    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = $server['CONTENT_TYPE'];
        }

        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $server['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
