<?php
namespace App\Middleware;

use App\Auth\AuthContext;
use App\Auth\Permissions;
use App\Logging\LoggerInterface;
use App\Logging\LoggerFactory;
use App\Audit\AuditLogger;
use App\Observability\RequestTracker;

/**
 * AuthorizationMiddleware - Validação de permissões (RBAC)
 *
 * Verifica se usuário autenticado tem permissão para acessar recurso.
 * Se não autorizado, retorna erro 403.
 *
 * Uso esperado após AuthMiddleware.
 */
class AuthorizationMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private ?AuditLogger $auditLogger;

    // Mapeamento de rota/ação para permissão necessária
    private array $routePermissions = [];

    public function __construct(?LoggerInterface $logger = null, ?AuditLogger $auditLogger = null)
    {
        $this->logger = $logger ?? LoggerFactory::make();
        $this->auditLogger = $auditLogger;
    }

    /**
     * Registrar permissão necessária para rota/ação
     *
     * @param string $action Identificador da ação (ex: "property.edit")
     * @param string|array $permissions Permissão(ões) necessária(s)
     * @return void
     */
    public function requirePermission(string $action, $permissions): void
    {
        $this->routePermissions[$action] = (array)$permissions;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $request, callable $next): array
    {
        try {
            $authContext = AuthContext::getInstance();

            // Se não autenticado, retornar 401
            if (!$authContext->isAuthenticated()) {
                $this->logger->warning('Unauthorized access attempt', [
                    'request_id' => RequestTracker::getId(),
                    'path' => $request['path'] ?? 'unknown',
                ]);

                return [
                    'status' => 401,
                    'error' => 'Não autenticado',
                ];
            }

            // Verificar permissões se configuradas
            $action = $request['action'] ?? null;

            if ($action && isset($this->routePermissions[$action])) {
                $requiredPermissions = $this->routePermissions[$action];
                $hasPermission = false;

                // Verificar se tem qualquer uma das permissões
                foreach ($requiredPermissions as $permission) {
                    if ($authContext->can($permission)) {
                        $hasPermission = true;
                        break;
                    }
                }

                if (!$hasPermission) {
                    $this->logUnauthorizedAccess($authContext, $action, $requiredPermissions);

                    return [
                        'status' => 403,
                        'error' => 'Acesso negado',
                    ];
                }
            }

            // Continuar para próximo middleware
            return $next($request);

        } catch (\Exception $e) {
            $this->logger->error('Authorization middleware error', [
                'request_id' => RequestTracker::getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'error' => 'Erro de autorização',
            ];
        }
    }

    /**
     * Registrar tentativa de acesso negado
     *
     * @param AuthContext $authContext
     * @param string $action
     * @param array $requiredPermissions
     * @return void
     */
    private function logUnauthorizedAccess(
        AuthContext $authContext,
        string $action,
        array $requiredPermissions
    ): void {
        $this->logger->warning('Unauthorized action attempt', [
            'request_id' => RequestTracker::getId(),
            'user_id' => $authContext->getUserId(),
            'role' => $authContext->getRole(),
            'action' => $action,
            'required_permissions' => $requiredPermissions,
        ]);

        // Registrar na auditoria
        if ($this->auditLogger) {
            $this->auditLogger->log(
                'unauthorized_access',
                'System',
                null,
                [
                    'action' => $action,
                    'required_permissions' => $requiredPermissions,
                    'user_role' => $authContext->getRole(),
                ],
                $authContext->getUserId()
            );
        }
    }
}
