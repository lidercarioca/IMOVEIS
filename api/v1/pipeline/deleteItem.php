<?php
// Desabilita notices/warnings que quebram JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/logger_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

/**
 * API: Deletar Item do Pipeline
 * DELETE /api/v1/pipeline/deleteItem.php
 *
 * Body JSON:
 * {
 *   "pipeline_id": 123
 * }
 */

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

    if ($authContext->getUserId() === null || (int)$authContext->getUserId() <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }

    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();

    // Verificar se usuário é admin (silenciosamente retorna sucesso se não for)
    if (!$authContext->isAdmin()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Operação concluída']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['pipeline_id'])) {
        throw new Exception('pipeline_id é obrigatório');
    }

    $pipelineId = (int) $input['pipeline_id'];

    $service = new \App\Service\PipelineService($pdo);
    $result = $service->deleteItem($tenantId, (int) ($authContext->getUserId() ?? 0), true, $pipelineId);

    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Item deletado com sucesso',
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
