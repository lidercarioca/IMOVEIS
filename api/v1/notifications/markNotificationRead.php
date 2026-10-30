<?php

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';

use App\Http\ApiResponse;
use App\Service\NotificationService;

$useStandardResponse = isset($_GET['standard_response']);
if (!$useStandardResponse) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/session_manager.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/auth.php';
    require_once __DIR__ . '/../middleware/TenantMiddleware.php';

    $authContext = \App\Auth\AuthContext::getInstance();
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $hasBearer = is_string($authHeader) && preg_match('/^Bearer\s+.+$/i', trim($authHeader)) === 1;

    if ($hasBearer) {
        $request = [
            'headers' => ['Authorization' => $authHeader],
            'query' => $_GET,
            'path' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
        ];

        $middleware = new \App\Middleware\AuthMiddleware();
        $authResult = $middleware->handle($request, function ($req) {
            return ['status' => 200, 'continue' => true];
        });

        if (($authResult['status'] ?? 200) !== 200) {
            http_response_code((int)($authResult['status'] ?? 401));
            if ($useStandardResponse) {
                ApiResponse::error($authResult['error'] ?? 'Token inválido ou expirado', (int)($authResult['status'] ?? 401));
            }
            echo json_encode(['success' => false, 'error' => $authResult['error'] ?? 'Token inválido ou expirado']);
            exit;
        }
    } else {
        checkAuth();
    }

    if (!$authContext->isAuthenticated() && !empty($_SESSION['user_id'] ?? '')) {
        $authContext->authenticate((int)$_SESSION['user_id'], $_SESSION['role'] ?? 'user');
    }

    $userId = $authContext->getUserId();
    if (!$userId) {
        http_response_code(401);
        if ($useStandardResponse) {
            ApiResponse::error('Not authenticated', 401);
        }
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $tenantId = (int)\Api\V1\Middleware\TenantMiddleware::resolveTenantId();

    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['id'] ?? null;
    $markAll = isset($input['all']) && $input['all'];

    $service = new NotificationService($pdo);

    if ($markAll) {
        $service->markAllNotificationsRead($userId, $tenantId);
    } else {
        if (!$notificationId) {
            http_response_code(400);
            if ($useStandardResponse) {
                ApiResponse::error('ID da notificacao nao fornecido', 400);
            }
            echo json_encode(['success' => false, 'error' => 'ID da notificacao nao fornecido']);
            exit;
        }

        $service->markNotificationRead((int)$notificationId, $userId, $tenantId);
    }

    if ($useStandardResponse) {
        ApiResponse::success(['success' => true, 'message' => 'Notificacao marcada como lida']);
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Notificacao marcada como lida'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    if ($useStandardResponse) {
        ApiResponse::error($e->getMessage(), 500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
