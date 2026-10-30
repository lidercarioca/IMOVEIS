<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

use App\Service\NotificationService;

try {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/session_manager.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once __DIR__ . '/../middleware/TenantMiddleware.php';
    // Autenticação: usar middleware canônico (suporta JWT e sessão)
    require_once __DIR__ . '/../middleware/AuthMiddleware.php';
    \Api\V1\Middleware\AuthMiddleware::handle();

    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();
    $tenantId = (int)\Api\V1\Middleware\TenantMiddleware::resolveTenantId();

    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['id'] ?? null;

    if (!$notificationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID da notificacao nao fornecido']);
        exit;
    }

    $service = new NotificationService($pdo);
    $service->deleteNotification((int)$notificationId, $userId, $tenantId);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Notificacao deletada'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
