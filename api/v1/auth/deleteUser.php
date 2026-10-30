<?php

// Adaptador de compatibilidade: converte requisições legadas para nova arquitetura
// Contrato externo mantido, implementação interna usando UserService

require_once dirname(dirname(dirname(__DIR__))) . '/app/bootstrap.php';

use App\Application\User\UserService;
use App\Infrastructure\User\UserRepository;
use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

AuthMiddleware::handle();
$tenantId = TenantMiddleware::resolveTenantId();

header('Content-Type: application/json; charset=utf-8');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método não permitido',
            'error' => 'Método não permitido'
        ]);
        exit;
    }

    $input = file_get_contents('php://input');
    if (empty($input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum dado recebido',
            'error' => 'Nenhum dado recebido'
        ]);
        exit;
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'JSON inválido: ' . json_last_error_msg(),
            'error' => 'JSON inválido: ' . json_last_error_msg()
        ]);
        exit;
    }

    $userId = $data['id'] ?? null;
    if (!$userId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID do usuário não fornecido',
            'error' => 'ID do usuário não fornecido'
        ]);
        exit;
    }

    $repository = new UserRepository($GLOBALS['pdo']);
    $service = new UserService($repository);

    try {
        $service->deleteUser((int)$userId, $tenantId);
        echo json_encode([
            'success' => true,
            'message' => 'Usuário excluído com sucesso',
            'userId' => $userId
        ]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error' => $e->getMessage(),
            'userId' => $userId
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Erro em /api/v1/auth/deleteUser.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => 'Erro interno do servidor'
    ]);
}
