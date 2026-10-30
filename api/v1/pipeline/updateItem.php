<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

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
header('Content-Type: application/json; charset=utf-8');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'PUT' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    if ($method === 'POST' && empty($rawInput)) {
        $data = $_POST;
    } else {
        $data = json_decode($rawInput, true);
    }

    if (!is_array($data) || !isset($data['pipeline_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID do pipeline não fornecido']);
        exit;
    }

    $service = new \App\Service\PipelineService($pdo);
    $result = $service->updateItem(
        $tenantId,
        (int) ($authContext->getUserId() ?? 0),
        (bool) $authContext->isAdmin(),
        (int) $data['pipeline_id'],
        $data
    );

    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Item atualizado com sucesso',
        'data' => [
            'pipeline_id' => (int) $data['pipeline_id'],
            'notes' => $data['notes'] ?? null,
            'value' => $data['value'] ?? null,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $status = 400;
    $message = $e->getMessage();
    if (str_contains($message, 'Item não encontrado')) {
        $status = 404;
    }

    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
}
