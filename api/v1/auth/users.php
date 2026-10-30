<?php

// Adaptador de compatibilidade: converte requisições legadas para nova arquitetura
// Contrato externo mantido, implementação interna usando UserService

require_once dirname(dirname(dirname(__DIR__))) . '/app/bootstrap.php';

use App\Application\User\IdempotencyRequestService;
use App\Application\User\UserAuditService;
use App\Application\User\UserService;
use App\Auth\AuthContext;
use App\Infrastructure\User\IdempotencyRequestRepository;
use App\Infrastructure\User\UserAuditRepository;
use App\Infrastructure\User\UserRepository;
use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

AuthMiddleware::handle();
$tenantId = TenantMiddleware::resolveTenantId();

header('Content-Type: application/json; charset=utf-8');

$repository = new UserRepository($GLOBALS['pdo']);
$auditRepository = new UserAuditRepository($GLOBALS['pdo']);
$idempotencyRepository = new IdempotencyRequestRepository($GLOBALS['pdo']);
$service = new UserService($repository, new UserAuditService($auditRepository));
$idempotencyService = new IdempotencyRequestService($idempotencyRepository);

function currentUserIdForIdempotency(): int
{
    $context = AuthContext::getInstance();
    return (int) ($context->getUserId() ?? 0);
}

function readIdempotencyKey(): ?string
{
    $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $_SERVER['HTTP_IDEMPOTENCYKEY'] ?? null;

    if (!is_string($key)) {
        return null;
    }

    $normalized = trim($key);
    return $normalized === '' ? null : $normalized;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {

        // =========================
        // GET USERS
        // =========================
        case 'GET':
            $userId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

            if ($userId) {
                try {
                    $user = $service->getUserById($userId, $tenantId);
                    echo json_encode([
                        'success' => true,
                        'data' => [$user]
                    ]);
                } catch (Throwable $e) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuário não encontrado',
                        'error' => 'Usuário não encontrado'
                    ]);
                }
            } else {
                $filters = [];
                $result = $service->listUsers($tenantId, $filters, 1, 10000);
                echo json_encode([
                    'success' => true,
                    'data' => $result['data']
                ]);
            }
            break;

        // =========================
        // CREATE USER
        // =========================
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Dados incompletos',
                    'error' => 'Dados incompletos'
                ]);
                exit;
            }

            $idempotencyKey = readIdempotencyKey();

            try {
                if ($idempotencyKey !== null) {
                    $result = $idempotencyService->executeWithKey(
                        $tenantId,
                        currentUserIdForIdempotency(),
                        'POST',
                        'users',
                        'create',
                        $idempotencyKey,
                        $data,
                        function () use ($service, $tenantId, $data) {
                            $created = $service->createUser($tenantId, $data);
                            return [
                                'status' => 201,
                                'body' => [
                                    'success' => true,
                                    'message' => 'Usuário criado com sucesso',
                                    'data' => $created,
                                ],
                            ];
                        },
                        201
                    );

                    http_response_code($result['status']);
                    echo json_encode($result['body']);
                    break;
                }

                $created = $service->createUser($tenantId, $data);
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuário criado com sucesso',
                    'data' => $created
                ]);
            } catch (RuntimeException $e) {
                $statusCode = stripos($e->getMessage(), 'em processamento') !== false ? 409 : 409;
                http_response_code($statusCode);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage()
                ]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage()
                ]);
            }
            break;

        // =========================
        // UPDATE USER
        // =========================
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID não fornecido',
                    'error' => 'ID não fornecido'
                ]);
                exit;
            }

            $id = (int)$data['id'];
            $idempotencyKey = readIdempotencyKey();

            try {
                if ($idempotencyKey !== null) {
                    $result = $idempotencyService->executeWithKey(
                        $tenantId,
                        currentUserIdForIdempotency(),
                        'PUT',
                        'users',
                        'update',
                        $idempotencyKey,
                        $data,
                        function () use ($service, $tenantId, $id, $data) {
                            $updated = $service->updateUser($id, $tenantId, $data);
                            return [
                                'status' => 200,
                                'body' => [
                                    'success' => true,
                                    'message' => 'Usuário atualizado com sucesso',
                                    'data' => $updated,
                                ],
                            ];
                        },
                        200
                    );

                    http_response_code($result['status']);
                    echo json_encode($result['body']);
                    break;
                }

                $updated = $service->updateUser($id, $tenantId, $data);
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuário atualizado com sucesso',
                    'data' => $updated
                ]);
            } catch (RuntimeException $e) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage()
                ]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage()
                ]);
            }
            break;

        // =========================
        // DELETE USER
        // =========================
        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID não fornecido',
                    'error' => 'ID não fornecido'
                ]);
                exit;
            }

            $idempotencyKey = readIdempotencyKey();

            try {
                if ($idempotencyKey !== null) {
                    $result = $idempotencyService->executeWithKey(
                        $tenantId,
                        currentUserIdForIdempotency(),
                        'DELETE',
                        'users',
                        'delete',
                        $idempotencyKey,
                        ['id' => $id],
                        function () use ($service, $tenantId, $id) {
                            $service->deleteUser($id, $tenantId);
                            return [
                                'status' => 200,
                                'body' => [
                                    'success' => true,
                                    'message' => 'Usuário deletado com sucesso',
                                ],
                            ];
                        },
                        200
                    );

                    http_response_code($result['status']);
                    echo json_encode($result['body']);
                    break;
                }

                $service->deleteUser($id, $tenantId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuário deletado com sucesso'
                ]);
            } catch (RuntimeException $e) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage()
                ]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage()
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Método não permitido',
                'error' => 'Método não permitido'
            ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Erro em /api/v1/auth/users.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => 'Erro interno do servidor'
    ]);
}
