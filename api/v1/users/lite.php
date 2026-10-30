<?php

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/security/TenantContext.php';

use App\Application\User\UserService;
use App\Infrastructure\User\UserRepository;
use App\Http\Response;
use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

AuthMiddleware::handle();
$tenantId = TenantMiddleware::resolveTenantId();

$filters = ['active' => 1];
if (isset($_GET['role'])) {
    $filters['role'] = trim((string)$_GET['role']);
}

$page = 1;
$limit = 1000;

$repository = new UserRepository($GLOBALS['pdo']);
$service = new UserService($repository);

try {
    $result = $service->listUsers($tenantId, $filters, $page, $limit);
    $data = array_map(function (array $user) {
        return [
            'id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'role' => $user['role'] ?? null,
            'active' => $user['active'] ?? null,
        ];
    }, $result['data']);

    Response::success($data, '', 200, false);
} catch (Throwable $throwable) {
    error_log('User lite error: ' . $throwable->getMessage());
    Response::serverError('Erro ao carregar lista de usuários');
}
