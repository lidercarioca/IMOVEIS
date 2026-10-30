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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    Response::error('ID do usuário inválido', 400);
}

$repository = new UserRepository($GLOBALS['pdo']);
$service = new UserService($repository);

try {
    $user = $service->getUserById($id, $tenantId);
    Response::success($user, 'Usuário carregado com sucesso');
} catch (Throwable $throwable) {
    if ($throwable instanceof RuntimeException) {
        Response::error($throwable->getMessage(), 404);
    }

    error_log('User show error: ' . $throwable->getMessage());
    Response::serverError('Erro ao carregar usuário');
}
