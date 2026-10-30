<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

use App\Service\NotificationService;

try {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/session_manager.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once __DIR__ . '/../middleware/TenantMiddleware.php';

    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $tenantId = (int)\Api\V1\Middleware\TenantMiddleware::resolveTenantId();

    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $service = new NotificationService($pdo);
    $notifications = $service->listReadNotificationsForUser($userId, $tenantId, $limit, $offset);
    $total = $service->countReadNotificationsForUser($userId, $tenantId);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total' => $total,
        'page' => $page
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
