<?php
// Desabilita notices/warnings que quebram JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/**
 * API: Obter Métricas do Pipeline
 * GET /api/v1/pipeline/getMetrics.php
 * COM ISOLAMENTO DE TENANT
 * 
 * Retorna:
 * {
 *   "success": true,
 *   "data": {
 *     "total_value": 150000.00,
 *     "total_items": 15,
 *     "average_value": 10000.00,
 *     "by_stage": {
 *       "1": { "name": "Prospecto", "count": 3, "value": 15000 },
 *       ...
 *     }
 *   }
 * }
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $hasBearer = is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
    $authContext = \App\Auth\AuthContext::getInstance();

    if ($hasBearer) {
        $request = ['headers' => ['Authorization' => $authHeader], 'query' => $_GET, 'path' => $_SERVER['REQUEST_URI'] ?? ''];
        $middleware = new \App\Middleware\AuthMiddleware();
        $res = $middleware->handle($request, function ($r) {
            return ['ok' => true];
        });
        if (isset($res['status']) && $res['status'] !== 200 && isset($res['error'])) {
            http_response_code((int)$res['status']);
            echo json_encode(['success' => false, 'error' => $res['error']]);
            exit;
        }
    } else {
        checkAuth();
    }

    if (!$authContext->isAuthenticated()) {
        $sessionUserId = $_SESSION['user_id'] ?? null;
        $sessionRole = $_SESSION['role'] ?? 'user';
        if ($sessionUserId !== null && (int)$sessionUserId > 0) {
            $authContext->authenticate((int)$sessionUserId, (string)$sessionRole);
        }
    }

    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
    $userId = $authContext->getUserId();

    $service = new \App\Service\PipelineService($pdo);
    $payload = $service->getMetrics($tenantId, $userId, $isAdmin);

    echo json_encode([
        'success' => true,
        'data' => $payload,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar métricas: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
