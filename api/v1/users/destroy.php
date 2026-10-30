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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['POST', 'DELETE'], true)) {
    Response::error('Método não permitido', 405);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    if (is_array($data) && isset($data['id'])) {
        $id = intval($data['id']);
    }
}

if ($id <= 0) {
    Response::error('ID do usuário inválido', 400);
}

$repository = new UserRepository($GLOBALS['pdo']);
$service = new UserService($repository);

try {
    $deleted = $service->deleteUser($id, $tenantId);
    if (!$deleted) {
        Response::error('Não foi possível excluir o usuário', 500);
    }

    Response::success(null, 'Usuário excluído com sucesso');
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
    if ($msg === 'Usuário não encontrado') {
        Response::error($msg, 404);
    }

    if (
        $msg === 'Não é permitido excluir o usuário administrador principal' ||
        $msg === 'Permissão negada para excluir este usuário'
    ) {
        Response::forbidden($msg);
    }

    Response::serverError('Erro ao excluir usuário');
} catch (Throwable $throwable) {
    error_log('User destroy error: ' . $throwable->getMessage());
    Response::serverError('Erro ao excluir usuário');
}
