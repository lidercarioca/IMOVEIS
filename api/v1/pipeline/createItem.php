<?php
// Desabilita notices/warnings que quebram JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

/**
 * API: Criar Item no Pipeline
 * POST /api/v1/pipeline/createItem.php
 *
 * Body JSON:
 * {
 *   "lead_id": 5,
 *   "pipeline_stage_id": 1,
 *   "value": 150000,
 *   "expected_close_date": "2026-02-14",
 *   "notes": "Cliente novo"
 * }
 */

header('Content-Type: application/json; charset=utf-8');

// Resolve authentication: if Authorization Bearer present, validate via central AuthMiddleware
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
    // No bearer token — fallback to session-based authentication (preserve legacy behavior)
    checkAuth();
}

// Materializar a identidade autenticada no AuthContext para que a operação use
// a identidade central da requisição, não um valor arbitrário do payload ou sessão
// divergente do contexto autenticado.
if (!$authContext->isAuthenticated()) {
    $sessionUserId = $_SESSION['user_id'] ?? null;
    $sessionRole = $_SESSION['role'] ?? 'user';
    if ($sessionUserId !== null && (int)$sessionUserId > 0) {
        $authContext->authenticate((int)$sessionUserId, (string)$sessionRole);
    }
}

$userId = $authContext->getUserId();
if ($userId === null || (int)$userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    $service = new \App\Service\PipelineService($pdo);
    $result = $service->createItem($tenantId, $userId, $input);

    echo json_encode([
        'success' => true,
        'pipeline_id' => (int)($result['pipeline_id'] ?? 0),
        'message' => $result['message'] ?? 'Item adicionado ao pipeline'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
