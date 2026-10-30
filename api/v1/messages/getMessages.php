<?php

use App\Http\ApiResponse;
use App\Service\MessageService;

require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once __DIR__ . '/../middleware/TenantMiddleware.php';
// legacy tenant helper removed; tenant resolved via TenantMiddleware

checkAuth();

$useStandardResponse = isset($_GET['standard_response']);
if (!$useStandardResponse) {
    header('Content-Type: application/json');
}

try {
    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $tenantId = (int)\Api\V1\Middleware\TenantMiddleware::resolveTenantId();
    $messageService = new MessageService($pdo);

    $messages = $messageService->listForUser($userId, $tenantId);
    $unreadCount = $messageService->countUnreadForUser($userId, $tenantId);

    if ($useStandardResponse) {
        ApiResponse::success([
            'messages' => $messages,
            'unread_count' => $unreadCount,
        ]);
    }

    echo json_encode([
        'messages' => $messages,
        'unread_count' => $unreadCount,
    ]);
} catch (Exception $e) {
    if ($useStandardResponse) {
        ApiResponse::error('Erro ao buscar mensagens', 500);
    }

    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar mensagens']);
}
