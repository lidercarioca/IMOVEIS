<?php

/**
 * API para obter leads do usuário autenticado com propriedades de interesse
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/session_manager.php';
require_once __DIR__ . '/../../../api/check_auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/utils/error_logger.php';
require_once __DIR__ . '/../../../app/helpers/tenant_isolation_helpers.php';
require_once __DIR__ . '/../../../src/Service/AgendamentoService.php';

use App\Service\AgendamentoService;

header('Content-Type: application/json');

try {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $hasBearer = is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
    $authContext = \App\Auth\AuthContext::getInstance();

    if ($hasBearer) {
        $request = [
            'headers' => ['Authorization' => $authHeader],
            'query' => $_GET,
            'path' => $_SERVER['REQUEST_URI'] ?? ''
        ];

        $middleware = new \App\Middleware\AuthMiddleware();
        $res = $middleware->handle($request, function ($r) {
            return ['ok' => true];
        });

        if (isset($res['status']) && $res['status'] !== 200 && isset($res['error'])) {
            http_response_code((int)$res['status']);
            echo json_encode(['success' => false, 'error' => $res['error']], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        checkAuth();
    }

    if (!$authContext->isAuthenticated() && !empty($_SESSION['user_id'] ?? '')) {
        $authContext->authenticate((int)$_SESSION['user_id'], $_SESSION['role'] ?? 'user');
    }

    if (!$authContext->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }

    $pdo = getConnection();
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $userId = $authContext->getUserId();

    $service = new AgendamentoService($pdo);
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
    $leads = $service->getLeadsWithProperties($tenantId, $userId, $isAdmin);

    error_log("DEBUG getLeadsWithProperties - userId: {$userId}, tenantId: {$tenantId}, isAdmin: " . ($isAdmin ? 'true' : 'false'));
    error_log("DEBUG leads count: " . count($leads));

    echo json_encode([
        'success' => true,
        'data' => $leads
    ]);
} catch (Throwable $e) {
    log_exception($e, ['endpoint' => 'getLeadsWithProperties.php']);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => getenv('ENVIRONMENT') === 'development' ? $e->getMessage() : 'Erro ao carregar leads'
    ]);
}
