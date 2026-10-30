<?php

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';

use App\Http\ApiResponse;
use App\Service\NotificationService;

$useStandardResponse = isset($_GET['standard_response']);

if (!$useStandardResponse) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    // Conexão com banco
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/session_manager.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    // Garantir resolução de tenant
    require_once __DIR__ . '/../middleware/TenantMiddleware.php';
    // Autenticação: usar o middleware canônico para materializar AuthContext
    require_once __DIR__ . '/../middleware/AuthMiddleware.php';
    \Api\V1\Middleware\AuthMiddleware::handle();

    // User ID da identidade autenticada do request
    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();

    if (!$userId) {
        if ($useStandardResponse) {
            ApiResponse::success([
                'notifications' => [],
                'unreadCount' => 0,
                'authenticated' => false,
            ]);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'notifications' => [],
            'unreadCount' => 0,
            'authenticated' => false
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tenantId = (int)\Api\V1\Middleware\TenantMiddleware::resolveTenantId();

    $service = new NotificationService($pdo);
    $notifications = $service->listNotificationsForUser($userId, $tenantId, 50);
    $unreadCount = $service->countUnreadNotificationsForUser($userId, $tenantId);

    // Retorno
    if ($useStandardResponse) {
        ApiResponse::success([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'authenticated' => true,
        ]);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unreadCount' => $unreadCount,
        'authenticated' => true
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[getNotifications] Exception: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    if ($useStandardResponse) {
        ApiResponse::error('Erro ao buscar notificações', 500);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
