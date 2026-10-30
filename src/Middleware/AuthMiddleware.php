<?php

namespace App\Middleware;

use App\Auth\JwtService;
use App\Auth\AuthContext;
use App\Auth\Permissions;
use App\Auth\RequestAuthenticationResult;
use App\Logging\LoggerInterface;
use App\Logging\LoggerFactory;
use App\Observability\RequestTracker;

/**
 * AuthMiddleware - Validação de JWT
 *
 * Extrai e valida token JWT do header Authorization.
 * Se válido, armazena no AuthContext.
 * Se inválido, retorna erro 401.
 *
 * Uso esperado em rotas protegidas.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;
    private LoggerInterface $logger;

    public function __construct(?JwtService $jwtService = null, ?LoggerInterface $logger = null)
    {
        $this->jwtService = $jwtService ?? new JwtService();
        $this->logger = $logger ?? LoggerFactory::make();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $request, callable $next): array
    {
        try {
            $token = $this->extractToken($request);
            $authHeader = $this->extractAuthorizationHeader($request);
            $hasBearerHeader = $authHeader !== null && str_starts_with(trim($authHeader), 'Bearer ');

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

            if ($authHeader !== null && !$hasBearerHeader) {
                $jwtResult = new RequestAuthenticationResult(
                    RequestAuthenticationResult::STATUS_MALFORMED_CREDENTIAL,
                    RequestAuthenticationResult::SOURCE_JWT,
                    null,
                    null,
                    [],
                    false,
                    'MALFORMED_CREDENTIAL',
                    RequestTracker::getId()
                );
            } elseif ($token !== null && $token !== '') {
                try {
                    $payload = $this->jwtService->validateAndDecode($token);
                    $userId = (int)($payload->sub ?? 0);
                    $role = (string)($payload->role ?? 'user');
                    $permissions = Permissions::getPermissions($role);
                    $isSuperAdmin = strtolower($role) === 'superadmin';

                    $jwtResult = new RequestAuthenticationResult(
                        RequestAuthenticationResult::STATUS_JWT_AUTHENTICATED,
                        RequestAuthenticationResult::SOURCE_JWT,
                        $userId,
                        $role,
                        $permissions,
                        $isSuperAdmin,
                        null,
                        RequestTracker::getId()
                    );
                } catch (\Exception $e) {
                    $this->logger->warning('Invalid JWT token', [
                        'request_id' => RequestTracker::getId(),
                        'error' => $e->getMessage(),
                    ]);

                    $jwtResult = $this->buildFailureResult($e);
                }
            } elseif ($authHeader === null && !$token) {
                $this->logger->warning('Missing JWT token', [
                    'request_id' => RequestTracker::getId(),
                    'path' => $request['path'] ?? 'unknown',
                ]);

                $jwtResult = new RequestAuthenticationResult(
                    RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
                    RequestAuthenticationResult::SOURCE_NONE,
                    null,
                    null,
                    [],
                    false,
                    'NO_CREDENTIAL',
                    RequestTracker::getId()
                );
            }

            $resolved = \App\Auth\AuthenticationPolicy::decide($jwtResult, $sessionResult, $hasBearerHeader);

            if (!$resolved->isAuthenticated()) {
                $this->logger->warning('Authentication rejected', [
                    'request_id' => RequestTracker::getId(),
                    'status' => $resolved->getStatus(),
                    'source' => $resolved->getSource(),
                    'conflict' => $resolved->getConflictState(),
                ]);

                return [
                    'status' => 401,
                    'error' => $this->resolveErrorMessage($resolved),
                    'authentication_result' => $resolved,
                ];
            }

            $identity = \App\Auth\AuthenticatedIdentityAdapter::adapt($resolved);
            if ($identity === null) {
                return [
                    'status' => 401,
                    'error' => 'Identidade não materializável',
                    'authentication_result' => $resolved,
                ];
            }

            $this->logger->debug('JWT validated', [
                'request_id' => RequestTracker::getId(),
                'user_id' => $identity->getUserId(),
                'role' => $identity->getRole(),
                'source' => $identity->getSource(),
                'authentication_result' => [
                    'status' => $resolved->getStatus(),
                    'source' => $resolved->getSource(),
                    'authenticated' => $resolved->isAuthenticated(),
                ],
            ]);

            $response = $next($request);
            if (is_array($response)) {
                $response['authentication_result'] = $resolved;
            }

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Auth middleware error', [
                'request_id' => RequestTracker::getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'error' => 'Erro de autenticação',
                'authentication_result' => new RequestAuthenticationResult(
                    RequestAuthenticationResult::STATUS_AUTHENTICATION_ERROR,
                    RequestAuthenticationResult::SOURCE_NONE,
                    null,
                    null,
                    [],
                    false,
                    'AUTHENTICATION_ERROR',
                    RequestTracker::getId()
                ),
            ];
        }
    }

    private function buildFailureResult(\Exception $exception): RequestAuthenticationResult
    {
        $message = strtolower($exception->getMessage());

        if (stripos($message, 'revogado') !== false || stripos($message, 'revoked') !== false) {
            return new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_JWT_REVOKED,
                RequestAuthenticationResult::SOURCE_JWT,
                null,
                null,
                [],
                false,
                'JWT_REVOKED',
                RequestTracker::getId()
            );
        }

        if (stripos($message, 'expir') !== false || stripos($message, 'expired') !== false) {
            return new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_JWT_EXPIRED,
                RequestAuthenticationResult::SOURCE_JWT,
                null,
                null,
                [],
                false,
                'JWT_EXPIRED',
                RequestTracker::getId()
            );
        }

        if (
            stripos($message, 'token inválido') !== false
            || stripos($message, 'invalid') !== false
            || stripos($message, 'malform') !== false
            || stripos($message, 'parse') !== false
        ) {
            return new RequestAuthenticationResult(
                RequestAuthenticationResult::STATUS_JWT_INVALID,
                RequestAuthenticationResult::SOURCE_JWT,
                null,
                null,
                [],
                false,
                'JWT_INVALID',
                RequestTracker::getId()
            );
        }

        return new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_JWT_INVALID,
            RequestAuthenticationResult::SOURCE_JWT,
            null,
            null,
            [],
            false,
            'JWT_INVALID',
            RequestTracker::getId()
        );
    }

    private function resolveErrorMessage(RequestAuthenticationResult $result): string
    {
        $status = $result->getStatus();

        if ($status === RequestAuthenticationResult::STATUS_JWT_EXPIRED) {
            return 'Token expirado';
        }

        if ($status === RequestAuthenticationResult::STATUS_JWT_REVOKED) {
            return 'Token revogado';
        }

        if ($status === RequestAuthenticationResult::STATUS_JWT_INVALID) {
            return 'Token inválido';
        }

        if ($status === RequestAuthenticationResult::STATUS_MALFORMED_CREDENTIAL) {
            return 'Credencial malformada';
        }

        if ($status === RequestAuthenticationResult::STATUS_AUTHENTICATION_ERROR) {
            return 'Conflito de identidade';
        }

        return 'Não autorizado';
    }

    private function extractAuthorizationHeader(array $request): ?string
    {
        $headers = $request['headers'] ?? [];

        foreach (['Authorization', 'authorization', 'HTTP_AUTHORIZATION'] as $key) {
            if (isset($headers[$key]) && is_string($headers[$key]) && trim($headers[$key]) !== '') {
                return trim($headers[$key]);
            }
        }

        return null;
    }

    /**
     * Extrair token do header Authorization
     *
     * Suporta formato: "Bearer TOKEN"
     *
     * @param array $request
     * @return string|null
     */
    private function extractToken(array $request): ?string
    {
        $authHeader = $this->extractAuthorizationHeader($request);
        if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return $request['query']['token'] ?? null;
    }
}
