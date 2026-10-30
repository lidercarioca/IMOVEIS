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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if ($useStandardResponse) {
        ApiResponse::error('Método não permitido', 405);
    }
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
$userId = \App\Auth\AuthContext::getInstance()->getUserId();
$tenantId = (int)\Api\V1\Middleware\TenantMiddleware::resolveTenantId();

try {
    $messageService = new MessageService($pdo);
    $messageService->markAsRead($userId, $tenantId, $id);

    if ($useStandardResponse) {
        ApiResponse::success(['success' => true]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    if ($useStandardResponse) {
        ApiResponse::error('Erro ao atualizar mensagem', 500);
    }
    echo json_encode(['error' => 'Erro ao atualizar mensagem']);
}
