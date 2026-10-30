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

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;
$limit = min($limit, 1000);

$filters = [];
if (isset($_GET['role'])) {
    $filters['role'] = trim((string)$_GET['role']);
}

if (isset($_GET['active'])) {
    $filters['active'] = in_array(strtolower((string)$_GET['active']), ['1', 'true', 'yes'], true) ? 1 : 0;
}

if (isset($_GET['q'])) {
    $filters['search'] = trim((string)$_GET['q']);
} elseif (isset($_GET['search'])) {
    $filters['search'] = trim((string)$_GET['search']);
}

$repository = new UserRepository($GLOBALS['pdo']);
$service = new UserService($repository);

try {
    $result = $service->listUsers($tenantId, $filters, $page, $limit);

    Response::success(
        $result['data'],
        '',
        200,
        false,
        [
            'pagination' => $result['pagination'],
        ]
    );
} catch (Throwable $throwable) {
    error_log('User list error: ' . $throwable->getMessage());
    Response::serverError('Erro ao carregar usuários');
}
