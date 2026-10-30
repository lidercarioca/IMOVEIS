<?php
// Desabilita notices/warnings que quebram JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/**
 * API: Obter opções para adicionar ao Pipeline (Leads e Propriedades)
 * GET /api/v1/pipeline/getOptions.php?type=lead&limit=100
 * 
 * Retorna lista de leads ou propriedades atribuídas ao usuário logado COM ISOLAMENTO DE TENANT
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

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

if ($authContext->getUserId() === null || (int)$authContext->getUserId() <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

// Obter tenant_id
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

try {
    $type = $_GET['type'] ?? 'lead';
    $limit = min((int)($_GET['limit'] ?? 100), 500);
    $search = $_GET['search'] ?? '';

    $service = new \App\Service\PipelineService($pdo);
    $data = $service->getOptions($tenantId, (string) $type, (string) $search, (int) $limit);

    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
