<?php

namespace App\Tenant;

use App\Auth\AuthContext;
use App\Infrastructure\User\UserRepository;
use PDO;

/**
 * TenantResolver - Resolução canônica do tenant.
 *
 * Ele avalia as possíveis fontes de tenant e retorna um TenantResolution único.
 */
class TenantResolver
{
    public const SOURCE_SUPERADMIN_SELECTED = 'superadmin_selected_tenant';
    public const SOURCE_AUTH_USER = 'authenticated_user_tenant';
    public const SOURCE_SESSION = 'session_tenant';
    public const SOURCE_REQUEST = 'request_tenant_id';
    public const SOURCE_HEADER = 'header_x_tenant_id';
    public const SOURCE_SUBDOMAIN = 'subdomain_tenant_id';
    public const SOURCE_FALLBACK = 'fallback_default_tenant';

    private AuthContext $authContext;
    private ?UserRepository $userRepository;

    public function __construct(?AuthContext $authContext = null, ?UserRepository $userRepository = null)
    {
        $this->authContext = $authContext ?? AuthContext::getInstance();
        $this->userRepository = $userRepository;
    }

    public function resolve(array $request = []): TenantResolution
    {
        $session = $request['session'] ?? $_SESSION ?? [];
        $query = $request['query'] ?? $_GET ?? [];
        $server = $request['server'] ?? $_SERVER ?? [];
        $headers = $request['headers'] ?? $this->extractHeaders($server);

        $isAuthenticated = $this->authContext->isAuthenticated();
        $userId = $this->authContext->getUserId();
        $role = $this->authContext->getRole();
        $sessionTenantId = $this->normalizeTenantId($session['tenant_id'] ?? null);
        $selectedTenantId = $this->normalizeTenantId($session['selected_tenant_id'] ?? null);
        $requestTenantId = $this->normalizeTenantId($query['tenant_id'] ?? null);
        $headerTenantId = $this->normalizeTenantId($headers['x-tenant-id'] ?? $headers['X-Tenant-Id'] ?? $headers['HTTP_X_TENANT_ID'] ?? null);
        $subdomainTenantId = $this->normalizeTenantId($server['SUBDOMAIN_TENANT_ID'] ?? null);
        $isSuperAdmin = $this->evaluateSuperAdmin($session, $userId);

        $metadata = [
            'session_tenant_id' => $sessionTenantId,
            'selected_tenant_id' => $selectedTenantId,
            'request_tenant_id' => $requestTenantId,
            'header_tenant_id' => $headerTenantId,
            'subdomain_tenant_id' => $subdomainTenantId,
            'session_is_superadmin' => !empty($session['is_superadmin']),
            'auth_user_id' => $userId,
            'auth_role' => $role,
        ];

        if ($isAuthenticated) {
            if ($isSuperAdmin) {
                if ($selectedTenantId !== null) {
                    return new TenantResolution(
                        $selectedTenantId,
                        self::SOURCE_SUPERADMIN_SELECTED,
                        1.0,
                        false,
                        true,
                        false,
                        self::SOURCE_SUPERADMIN_SELECTED,
                        $metadata
                    );
                }

                if ($requestTenantId !== null) {
                    return new TenantResolution(
                        $requestTenantId,
                        self::SOURCE_REQUEST,
                        0.85,
                        false,
                        true,
                        false,
                        self::SOURCE_REQUEST,
                        $metadata
                    );
                }

                if ($headerTenantId !== null) {
                    return new TenantResolution(
                        $headerTenantId,
                        self::SOURCE_HEADER,
                        0.80,
                        false,
                        true,
                        false,
                        self::SOURCE_HEADER,
                        $metadata
                    );
                }

                if ($sessionTenantId !== null) {
                    return new TenantResolution(
                        $sessionTenantId,
                        self::SOURCE_SESSION,
                        0.75,
                        false,
                        true,
                        false,
                        self::SOURCE_SESSION,
                        $metadata
                    );
                }
            }

            $userTenantId = $this->resolveTenantIdFromUser($userId);
            $validatedSessionTenantId = $this->resolveValidatedTenantForUser($userId, $sessionTenantId, false);
            $validatedRequestTenantId = $this->resolveValidatedTenantForUser($userId, $requestTenantId, false);
            $validatedHeaderTenantId = $this->resolveValidatedTenantForUser($userId, $headerTenantId, false);

            if ($userTenantId !== null) {
                return new TenantResolution(
                    $userTenantId,
                    self::SOURCE_AUTH_USER,
                    1.0,
                    false,
                    $isSuperAdmin,
                    false,
                    self::SOURCE_AUTH_USER,
                    $metadata
                );
            }

            if ($validatedSessionTenantId !== null) {
                return new TenantResolution(
                    $validatedSessionTenantId,
                    self::SOURCE_SESSION,
                    0.8,
                    false,
                    $isSuperAdmin,
                    false,
                    self::SOURCE_SESSION,
                    $metadata
                );
            }

            if ($validatedRequestTenantId !== null) {
                return new TenantResolution(
                    $validatedRequestTenantId,
                    self::SOURCE_REQUEST,
                    0.7,
                    false,
                    $isSuperAdmin,
                    false,
                    self::SOURCE_REQUEST,
                    $metadata
                );
            }

            if ($validatedHeaderTenantId !== null) {
                return new TenantResolution(
                    $validatedHeaderTenantId,
                    self::SOURCE_HEADER,
                    0.65,
                    false,
                    $isSuperAdmin,
                    false,
                    self::SOURCE_HEADER,
                    $metadata
                );
            }

            if ($this->shouldAllowSessionTenantFallback($userId, $sessionTenantId, $session)) {
                return new TenantResolution(
                    $sessionTenantId,
                    self::SOURCE_SESSION,
                    0.55,
                    false,
                    $isSuperAdmin,
                    false,
                    self::SOURCE_SESSION,
                    $metadata
                );
            }

            return new TenantResolution(
                null,
                'auth_user_missing_valid_tenant',
                0.0,
                false,
                $isSuperAdmin,
                false,
                'tenant_validation_failed',
                $metadata
            );
        }

        if ($subdomainTenantId !== null) {
            return new TenantResolution(
                $subdomainTenantId,
                self::SOURCE_SUBDOMAIN,
                0.9,
                true,
                $isSuperAdmin,
                false,
                self::SOURCE_SUBDOMAIN,
                $metadata
            );
        }

        if ($requestTenantId !== null) {
            return new TenantResolution(
                $requestTenantId,
                self::SOURCE_REQUEST,
                0.7,
                true,
                $isSuperAdmin,
                false,
                self::SOURCE_REQUEST,
                $metadata
            );
        }

        if ($headerTenantId !== null) {
            return new TenantResolution(
                $headerTenantId,
                self::SOURCE_HEADER,
                0.65,
                true,
                $isSuperAdmin,
                false,
                self::SOURCE_HEADER,
                $metadata
            );
        }

        return new TenantResolution(
            1,
            self::SOURCE_FALLBACK,
            0.25,
            true,
            $isSuperAdmin,
            true,
            self::SOURCE_FALLBACK,
            $metadata
        );
    }

    public function resolveValidatedTenantForUser(?int $userId, ?int $candidateTenantId, bool $isSuperAdmin = false): ?int
    {
        if ($candidateTenantId === null || $userId === null || $userId <= 0) {
            return null;
        }

        if ($isSuperAdmin) {
            return $candidateTenantId;
        }

        try {
            $repository = $this->getUserRepository();
            if ($repository === null) {
                return null;
            }

            return $repository->userBelongsToTenant($userId, $candidateTenantId) ? $candidateTenantId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveTenantIdFromUser(?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        try {
            $repository = $this->getUserRepository();
            if ($repository === null) {
                return null;
            }

            $user = $repository->findByIdAnyTenant($userId);
            if (!$user || empty($user['tenant_id'])) {
                return null;
            }

            return (int)$user['tenant_id'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function evaluateSuperAdmin(array $session, ?int $userId): bool
    {
        if (!empty($session['is_superadmin'])) {
            return true;
        }

        try {
            $repository = $this->getUserRepository();
            if ($repository !== null && $userId !== null) {
                $user = $repository->findByIdAnyTenant($userId);
                if (!empty($user['is_superadmin'])) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    private function getUserRepository(): ?UserRepository
    {
        if ($this->userRepository !== null) {
            return $this->userRepository;
        }

        if (!(isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO)) {
            return null;
        }

        try {
            $pdo = $GLOBALS['pdo'];
            $checkStmt = $pdo->query('SELECT 1 FROM users LIMIT 1');
            if ($checkStmt === false) {
                return null;
            }

            $this->userRepository = new UserRepository($pdo);
            return $this->userRepository;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function shouldAllowSessionTenantFallback(?int $userId, ?int $sessionTenantId, array $session): bool
    {
        if ($userId === null || $userId <= 0 || $sessionTenantId === null || $sessionTenantId <= 0) {
            return false;
        }

        $sessionUserId = isset($session['user_id']) ? (int) $session['user_id'] : 0;
        return $sessionUserId === $userId;
    }

    private function normalizeTenantId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $tenantId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $tenantId === false ? null : (int)$tenantId;
    }

    private function extractHeaders(array $server): array
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
