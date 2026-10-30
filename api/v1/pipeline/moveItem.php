<?php
// Desabilita notices/warnings que quebram JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/logger_functions.php';
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
            http_response_code((int) $res['status']);
            echo json_encode(['success' => false, 'error' => $res['error']]);
            exit;
        }
        initializeTenantMiddleware($pdo);
        $tenantId = getCurrentTenantId();
    } else {
        checkAuth();
        if (!$authContext->isAuthenticated()) {
            if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                $authContext->authenticate((int) $_SESSION['user_id'], $_SESSION['role'] ?? 'user');
            }
        }
        if (!$authContext->isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Não autenticado']);
            exit;
        }
        initializeTenantMiddleware($pdo);
        $tenantId = getCurrentTenantId();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['pipeline_id']) || !isset($input['to_stage_id'])) {
        throw new Exception('pipeline_id e to_stage_id são obrigatórios');
    }

    $pipelineId = (int) $input['pipeline_id'];
    $toStageId = (int) $input['to_stage_id'];
    $value = $input['value'] ?? null;
    $expectedCloseDate = $input['expected_close_date'] ?? null;
    $notes = $input['notes'] ?? null;

    $service = new \App\Service\PipelineService($pdo);
    $service->moveItem(
        $tenantId,
        (int) ($authContext->getUserId() ?? 0),
        (bool) $authContext->isAdmin(),
        $pipelineId,
        $toStageId,
        $value,
        $expectedCloseDate,
        $notes
    );

    echo json_encode([
        'success' => true,
        'message' => 'Item movido com sucesso',
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
