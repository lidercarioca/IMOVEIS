<?php

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/security/TenantContext.php';

use App\Application\User\UserService;
use App\Infrastructure\User\UserRepository;
use App\Http\Response;
use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

// Segurança: apenas sessões autenticadas
AuthMiddleware::handle();
$tenantId = TenantMiddleware::resolveTenantId();

// Aceita somente POST
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    Response::error('Método não permitido', 405);
}

// Ler e validar JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    Response::error('Dados JSON inválidos', 400);
}

if (!is_array($data)) {
    Response::error('Payload inválido', 400);
}

// Proibir campos que não podem ser definidos pelo cliente
$forbidden = ['tenant_id', 'id', 'is_superadmin'];
foreach ($forbidden as $f) {
    if (array_key_exists($f, $data)) {
        Response::error("Campo não permitido: {$f}", 400);
    }
}

// Campos esperados (validação básica de presença)
$required = ['username', 'name', 'email', 'password'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        Response::error("Campo obrigatório ausente: {$field}", 400);
    }
}

// Não chamar Repository diretamente: usar Service
$repository = new UserRepository($GLOBALS['pdo']);
$service = new UserService($repository);

try {
    $created = $service->createUser($tenantId, $data);
    Response::success($created, 'Usuário criado com sucesso', 201);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
    if ($msg === 'Usuário não encontrado') {
        Response::error($msg, 404);
    }

    if (
        $msg === 'Permissão negada para criar usuário' ||
        $msg === 'Apenas superadmin pode criar usuário com role diferente de user'
    ) {
        Response::forbidden($msg);
    }

    if ($msg === 'Username já está em uso' || $msg === 'Email já está em uso') {
        Response::error($msg, 400);
    }

    Response::serverError('Erro ao criar usuário');
} catch (Throwable $t) {
    error_log('User create error: ' . $t->getMessage());
    Response::serverError('Erro ao criar usuário');
}
