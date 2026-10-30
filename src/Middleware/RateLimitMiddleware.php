<?php

namespace App\Middleware;

use App\Security\RateLimiter;
use App\Auth\AuthContext;
use App\Logging\LoggerInterface;
use App\Logging\LoggerFactory;
use App\Audit\AuditLogger;
use App\Observability\RequestTracker;

/**
 * RateLimitMiddleware - Proteção contra abuso
 *
 * Limita número de requisições por IP ou usuário.
 * Retorna 429 (Too Many Requests) se limite excedido.
 *
 * Limites pré-configurados:
 * - login: 5 req/min
 * - api: 100 req/min
 * - aggressive: 10 req/min
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiter $rateLimiter;
    private LoggerInterface $logger;
    private ?AuditLogger $auditLogger;

    // Limites por rota/ação
    private array $routeLimits = [];

    public function __construct(
        ?RateLimiter $rateLimiter = null,
        ?LoggerInterface $logger = null,
        ?AuditLogger $auditLogger = null
    ) {
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
        $this->logger = $logger ?? LoggerFactory::make();
        $this->auditLogger = $auditLogger;
    }

    /**
     * Registrar limite para ação/rota específica
     *
     * @param string $action Identificador da ação
     * @param int $limit Requisições por minuto
     * @return void
     */
    public function setLimit(string $action, int $limit): void
    {
        $this->routeLimits[$action] = $limit;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $request, callable $next): array
    {
        try {
            // Determinar identificador (IP ou user ID)
            $identifier = $this->getIdentifier($request);

            // Determinar limite
            $action = $request['action'] ?? 'api';
            $limit = $this->routeLimits[$action] ?? 100; // Padrão: 100 req/min

            // Verificar rate limit
            if (!$this->rateLimiter->checkCustomLimit($identifier, $action, $limit)) {
                return $this->handleRateLimitExceeded($request, $identifier, $action, $limit);
            }

            // Adicionar headers informativos
            $headerInfo = $this->rateLimiter->getHeaderInfo($identifier, $limit);

            // Continuar para próximo middleware
            $response = $next($request);

            // Adicionar headers de rate limit na resposta
            if (is_array($response)) {
                $response['headers']['X-RateLimit-Limit'] = (string)$headerInfo['limit'];
                $response['headers']['X-RateLimit-Remaining'] = (string)$headerInfo['remaining'];
                $response['headers']['X-RateLimit-Reset'] = (string)$headerInfo['reset'];
            }

            return $response;
        } catch (\UnexpectedValueException $e) {
            $this->logger->warning('Rate limit identity rejected', [
                'request_id' => RequestTracker::getId(),
                'reason' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'anonymous ip')) {
                return [
                    'status' => 400,
                    'error' => 'INVALID_IP',
                    'message' => 'Não foi possível validar sua conexão. Tente novamente.',
                ];
            }

            return [
                'status' => 401,
                'error' => 'Identidade não materializável',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Rate limit middleware error', [
                'request_id' => RequestTracker::getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'error' => 'Erro de rate limit',
            ];
        }
    }

    /**
     * Obter identificador para rate limiting
     *
     * Prioridade:
     * 1. User ID se autenticado
     * 2. IP da requisição
     *
     * @param array $request
     * @return string
     */
    private function getIdentifier(array $request): string
    {
        $authContext = AuthContext::getInstance();

        if ($authContext->isAuthenticated()) {
            $userId = $authContext->getUserId();

            if (!is_int($userId) || $userId <= 0) {
                throw new \UnexpectedValueException('Invalid authenticated user id');
            }

            return 'user_' . $userId;
        }

        $ip = $request['ip'] ?? null;
        if (!is_string($ip)) {
            throw new \UnexpectedValueException('Invalid anonymous ip');
        }

        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new \UnexpectedValueException('Invalid anonymous ip');
        }

        return $ip;
    }

    /**
     * Lidar com limite excedido
     *
     * @param array $request
     * @param string $identifier
     * @param string $action
     * @param int $limit
     * @return array
     */
    private function handleRateLimitExceeded(
        array $request,
        string $identifier,
        string $action,
        int $limit
    ): array {
        $headerInfo = $this->rateLimiter->getHeaderInfo($identifier, $limit);

        $this->logger->warning('Rate limit exceeded', [
            'request_id' => RequestTracker::getId(),
            'identifier' => $identifier,
            'action' => $action,
            'limit' => $limit,
            'reset_in' => $headerInfo['reset_in'],
        ]);

        // Registrar na auditoria se autenticado
        if ($this->auditLogger) {
            $authContext = AuthContext::getInstance();
            if ($authContext->isAuthenticated()) {
                $this->auditLogger->log(
                    'rate_limit_exceeded',
                    'System',
                    null,
                    [
                        'action' => $action,
                        'limit' => $limit,
                    ],
                    $authContext->getUserId()
                );
            }
        }

        return [
            'status' => 429,
            'error' => 'Muitas requisições. Tente novamente em ' . $headerInfo['reset_in'] . 's',
            'headers' => [
                'X-RateLimit-Limit' => (string)$headerInfo['limit'],
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string)$headerInfo['reset'],
                'Retry-After' => (string)$headerInfo['reset_in'],
            ],
        ];
    }
}
