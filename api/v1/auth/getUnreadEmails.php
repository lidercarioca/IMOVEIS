<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/session_manager.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

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
            echo json_encode(['success' => false, 'error' => $res['error']]);
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
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();

    $messageService = new \App\Service\MessageService($pdo);
    $unreadCount = $messageService->countUnreadForTenant($tenantId);
    $messages = $messageService->listUnreadForTenant($tenantId, 10);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'messages' => $messages,
        'type' => 'form_messages'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
