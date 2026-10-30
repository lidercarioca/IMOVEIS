<?php

// Adaptador de compatibilidade: converte requisições legadas para nova arquitetura
// Retorna lista simplificada de usuários usando UserService

require_once dirname(dirname(dirname(__DIR__))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/security/TenantContext.php';

use App\Application\User\UserService;
use App\Infrastructure\User\UserRepository;
use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

AuthMiddleware::handle();
$tenantId = TenantMiddleware::resolveTenantId();

header('Content-Type: application/json; charset=utf-8');

try {
    $role = $_GET['role'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;
    $limit = min($limit, 10000);

    $filters = ['active' => 1];
    if ($role) {
        $filters['role'] = $role;
    }

    $repository = new UserRepository($GLOBALS['pdo']);
    $service = new UserService($repository);

    $result = $service->listUsers($tenantId, $filters, 1, $limit);

    // Mapear para formato antigo: id, name, username, role
    $data = array_map(function (array $user) {
        return [
            'id' => $user['id'] ?? null,
            'name' => $user['name'] ?? null,
            'username' => $user['username'] ?? null,
            'role' => $user['role'] ?? null,
        ];
    }, $result['data']);

    echo json_encode([
        'success' => true,
        'data' => $data,
        'tenant_id' => $tenantId
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Erro em /api/v1/users/getList.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => $e->getMessage()
    ]);
}
