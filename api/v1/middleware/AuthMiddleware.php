<?php

namespace Api\V1\Middleware;

use App\Auth\AuthenticationPolicy;
use App\Auth\AuthContext;
use App\Auth\JwtService;
use App\Auth\Permissions;
use App\Auth\RequestAuthenticationResult;

class AuthMiddleware
{
    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function handle(): void
    {
        self::ensureSession();

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $hasBearerHeader = !empty($authHeader) && preg_match('/^Bearer\s+.+$/i', trim($authHeader));

        $jwtResult = new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_NONE
        );

        $sessionResult = function_exists('buildSessionAuthenticationResult')
            ? buildSessionAuthenticationResult()
            : new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
                RequestAuthenticationResult::SOURCE_NONE
            );

        if ($hasBearerHeader) {
            $token = trim((string) preg_replace('/^Bearer\s+/i', '', $authHeader));
            $jwtService = new JwtService();

            try {
                $payload = $jwtService->validateAndDecode($token);
                $role = strtolower(trim((string)($payload->role ?? 'user')));
                $userId = isset($payload->sub) ? (int)$payload->sub : null;
                $adminLike = in_array($role, ['admin', 'administrator', 'superadmin'], true);

                $jwtResult = new RequestAuthenticationResult(
                    RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
                    RequestAuthenticationResult::SOURCE_JWT,
                    $userId,
                    $role,
                    Permissions::getPermissions($role),
                    $adminLike,
                    null,
                    $_SERVER['REQUEST_ID'] ?? null
                );
            } catch (\Throwable $e) {
                $message = strtolower($e->getMessage());

                if (stripos($message, 'revogado') !== false || stripos($message, 'revoked') !== false) {
                    $jwtResult = new RequestAuthenticationResult(
                        RequestAuthenticationResult::STATUS_JWT_REVOKED,
                        RequestAuthenticationResult::SOURCE_JWT,
                        null,
                        null,
                        [],
                        false,
                        'JWT_REVOKED',
                        $_SERVER['REQUEST_ID'] ?? null
                    );
                } elseif (stripos($message, 'expir') !== false || stripos($message, 'expired') !== false) {
                    $jwtResult = new RequestAuthenticationResult(
                        RequestAuthenticationResult::STATUS_JWT_EXPIRED,
                        RequestAuthenticationResult::SOURCE_JWT,
                        null,
                        null,
                        [],
                        false,
                        'JWT_EXPIRED',
                        $_SERVER['REQUEST_ID'] ?? null
                    );
                } else {
                    $jwtResult = new RequestAuthenticationResult(
                        RequestAuthenticationResult::STATUS_JWT_INVALID,
                        RequestAuthenticationResult::SOURCE_JWT,
                        null,
                        null,
                        [],
                        false,
                        'JWT_INVALID',
                        $_SERVER['REQUEST_ID'] ?? null
                    );
                }
            }
        }

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, $hasBearerHeader);

        if (!$resolved->isAuthenticated()) {
            $status = $resolved->getStatus();
            $message = 'Não autorizado';

            if ($status === RequestAuthenticationResult::STATUS_JWT_EXPIRED) {
                $message = 'Token expirado';
            } elseif ($status === RequestAuthenticationResult::STATUS_JWT_REVOKED) {
                $message = 'Token revogado';
            } elseif ($status === RequestAuthenticationResult::STATUS_JWT_INVALID) {
                $message = 'Token inválido';
            } elseif ($status === RequestAuthenticationResult::STATUS_AUTHENTICATION_ERROR) {
                $message = 'Erro de autenticação';
            }

            self::respondUnauthorized($message);
        }

        if ($resolved->getSource() === RequestAuthenticationResult::SOURCE_JWT) {
            $userId = (int)($resolved->getUserId() ?? 0);
            $role = $resolved->getRole() ?? 'user';
            AuthContext::getInstance()->authenticate($userId, $role);
        } else {
            $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
            $sessionRole = $_SESSION['role'] ?? 'user';
            AuthContext::getInstance()->authenticate($sessionUserId, $sessionRole);
        }

        $tenantId = null;
        if (file_exists(__DIR__ . '/../../../app/security/TenantContext.php')) {
            require_once __DIR__ . '/../../../app/security/TenantContext.php';
            \TenantContext::initialize();
            $tenantId = \TenantContext::getTenantId();
        }

        $authUserId = AuthContext::getInstance()->getUserId();

        if ($authUserId === null || $tenantId === null || intval($tenantId) <= 0) {
            self::respondUnauthorized('Sessão inválida ou não autenticada');
        }

        $_SESSION['last_activity'] = time();
    }

    public static function respondUnauthorized(string $message = 'Autenticação necessária'): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => 'unauthorized'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
