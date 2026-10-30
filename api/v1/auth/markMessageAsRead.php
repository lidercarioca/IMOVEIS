<?php

/**
 * API para marcar UMA mensagem como lida
 * Recebe o ID da mensagem e marca como lida
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

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
        echo json_encode(['success' => false, 'error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    initializeTenantMiddleware($pdo);
    $userId = $authContext->getUserId();
    $tenantId = getCurrentTenantId();

    // Recebe o ID da mensagem
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['message_id']) || !is_numeric($data['message_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID da mensagem é obrigatório'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $messageId = (int)$data['message_id'];

    $messageService = new \App\Service\MessageService($pdo);
    $messageService->markAsRead($userId, $tenantId, $messageId);

    echo json_encode([
        'success' => true,
        'message' => 'Mensagem marcada como lida'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao marcar mensagem: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
