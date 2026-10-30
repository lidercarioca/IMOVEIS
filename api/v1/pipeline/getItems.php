<?php
// Desabilita notices/warnings que quebram JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/**
 * API: Obter Items do Pipeline (Kanban)
 * GET /api/v1/pipeline/getItems.php?stage_id=X&user_id=Y
 *
 * Retorna leads/properties organizados por estágios COM ISOLAMENTO DE TENANT
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
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
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            $authContext->authenticate((int) $_SESSION['user_id'], $_SESSION['role'] ?? 'user');
        }
    }

    if (!$authContext->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
    $userId = (int) ($authContext->getUserId() ?? 0);
    $stageId = isset($_GET['stage_id']) && $_GET['stage_id'] !== '' ? (int) $_GET['stage_id'] : null;
    $archived = isset($_GET['archived']) ? (int) $_GET['archived'] : 0;

    $service = new \App\Service\PipelineService($pdo);
    $data = $service->getItems($tenantId, $userId, $isAdmin, $stageId, $archived);

    echo json_encode([
        'success' => true,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
