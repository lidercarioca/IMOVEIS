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
if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    Response::error('Método não permitido', 405);
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    Response::error('Dados JSON inválidos', 400);
}

$id = 0;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
} elseif (isset($data['id'])) {
    $id = intval($data['id']);
}

if ($id <= 0) {
    Response::error('ID do usuário inválido', 400);
}

$repository = new UserRepository($GLOBALS['pdo']);
$service = new UserService($repository);

try {
    $user = $service->updateUser($id, $tenantId, $data ?? []);
    Response::success($user, 'Usuário atualizado com sucesso');
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
    if ($msg === 'Usuário não encontrado') {
        Response::error($msg, 404);
    }

    if (
        $msg === 'Permissão negada para atualizar este usuário' ||
        $msg === 'Apenas admin ou superadmin pode alterar o status ativo' ||
        $msg === 'Apenas superadmin pode alterar a role'
    ) {
        Response::forbidden($msg);
    }

    if ($msg === 'Email já está em uso') {
        Response::error($msg, 400);
    }

    Response::serverError('Erro ao atualizar usuário');
} catch (Throwable $throwable) {
    error_log('User update error: ' . $throwable->getMessage());
    Response::serverError('Erro ao atualizar usuário');
}
