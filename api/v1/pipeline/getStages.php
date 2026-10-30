<?php
// Desabilita notices/warnings que quebram JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/**
 * API: Obter Estágios do Pipeline
 * GET /api/v1/pipeline/stages.php
 * COM ISOLAMENTO DE TENANT
 *
 * Retorna todos os estágios do funil com cores e descrições
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
        if ($sessionUserId !== null && (int) $sessionUserId > 0) {
            $authContext->authenticate((int) $sessionUserId, (string) $sessionRole);
        }
    }

    if (!$authContext->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();

    $service = new \App\Service\PipelineService($pdo);
    $stages = $service->getStages($tenantId);

    echo json_encode([
        'success' => true,
        'data' => $stages,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
