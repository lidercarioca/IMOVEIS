<?php
// Limpa qualquer output anterior
ob_start();

require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Infrastructure/User/UserRepository.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Application/User/UserService.php';

use App\Infrastructure\User\UserRepository;
use App\Application\User\UserService;

// Limpa output buffer se necessário
while (ob_get_level() > 1) {
    ob_end_clean();
}

// Verifica autenticação
checkAuth();

// Inicializa isolamento por tenant
initializeTenantMiddleware($GLOBALS['pdo']);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json; charset=utf-8');

try {
    $userRepository = new UserRepository($GLOBALS['pdo']);
    $userService = new UserService($userRepository);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // GET permite usuários autenticados (GET é apenas leitura)
            // Verificar se foi passado um ID específico
            $userId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

            if ($userId) {
                // Buscar um usuário específico por ID (apenas do tenant atual)
                try {
                    $user = $userService->getUserById($userId, $tenantId);
                    echo json_encode(['success' => true, 'data' => [$user]], JSON_UNESCAPED_UNICODE);
                } catch (\RuntimeException $e) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Usuário não encontrado ou acesso negado'], JSON_UNESCAPED_UNICODE);
                }
            } else {
                // Listar todos os usuários do tenant atual
                $result = $userService->listUsers($tenantId);
                echo json_encode(['success' => true, 'data' => $result['data']], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'POST':
            // Criar novo usuário
            $data = json_decode(file_get_contents('php://input'), true);
            // Log da requisição para debug
            $reqLog = date('c') . " [POST users request] User: " . ($_SESSION['username'] ?? 'N/A') . " | Payload: " . json_encode($data) . "\n";
            @file_put_contents(__DIR__ . '/../logs/api_requests.log', $reqLog, FILE_APPEND);

            if (!$data['username'] || !$data['password'] || !$data['name'] || !$data['email']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Dados incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            try {
                $userData = [
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['role'] ?? 'user'
                ];

                $user = $userService->createUser($tenantId, $userData);
                echo json_encode(['success' => true, 'message' => 'Usuário criado com sucesso', 'data' => $user], JSON_UNESCAPED_UNICODE);
            } catch (\RuntimeException $e) {
                http_response_code(400);
                $message = $e->getMessage();
                // Manter compatibilidade com mensagens existentes
                if (strpos($message, 'Username já está em uso') !== false) {
                    $message = 'Este nome de usuário já está em uso. Por favor, escolha outro.';
                } elseif (strpos($message, 'Email já está em uso') !== false) {
                    $message = 'Este e-mail já está cadastrado. Por favor, use outro e-mail.';
                } elseif (strpos($message, 'Permissão negada') !== false) {
                    http_response_code(403);
                    $message = 'Permissão negada para criar este tipo de usuário.';
                }
                echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            } catch (\InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'PUT':
            // Atualizar usuário
            $data = json_decode(file_get_contents('php://input'), true);
            // Log da requisição para debug
            $reqLog = date('c') . " [PUT users request] User: " . ($_SESSION['username'] ?? 'N/A') . " | Payload: " . json_encode($data) . "\n";
            @file_put_contents(__DIR__ . '/../logs/api_requests.log', $reqLog, FILE_APPEND);

            if (!$data['id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido'], JSON_UNESCAPED_UNICODE);
                break;
            }

            try {
                $updateData = [];

                if (isset($data['name']) && !empty($data['name'])) {
                    $updateData['name'] = $data['name'];
                }

                if (isset($data['email']) && !empty($data['email'])) {
                    $updateData['email'] = $data['email'];
                }

                if (isset($data['role']) && !empty($data['role'])) {
                    $updateData['role'] = $data['role'];
                }

                if (isset($data['active']) && $data['active'] !== '') {
                    $updateData['active'] = (int)(bool)$data['active'];
                }

                if (isset($data['password']) && !empty($data['password'])) {
                    $updateData['password'] = $data['password'];
                }

                if (empty($updateData)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nenhum dado para atualizar'], JSON_UNESCAPED_UNICODE);
                    break;
                }

                $user = $userService->updateUser($data['id'], $tenantId, $updateData);
                echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso', 'data' => $user], JSON_UNESCAPED_UNICODE);
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                if (strpos($message, 'não encontrado') !== false) {
                    http_response_code(403);
                    $message = 'Usuário não encontrado ou acesso negado';
                } elseif (strpos($message, 'Email já está em uso') !== false) {
                    http_response_code(400);
                    $message = 'Este e-mail já está cadastrado. Por favor, use outro e-mail.';
                } elseif (strpos($message, 'Permissão negada') !== false) {
                    http_response_code(403);
                } else {
                    http_response_code(400);
                }
                echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            } catch (\InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'DELETE':
            // Deletar usuário
            $id = $_GET['id'] ?? null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido'], JSON_UNESCAPED_UNICODE);
                break;
            }

            try {
                // Verificar se é o admin principal
                if ($id == 1) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Não é permitido deletar o usuário administrador principal'], JSON_UNESCAPED_UNICODE);
                    break;
                }

                $deleted = $userService->deleteUser((int)$id, $tenantId);

                if ($deleted) {
                    echo json_encode(['success' => true, 'message' => 'Usuário deletado com sucesso'], JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado ou acesso negado'], JSON_UNESCAPED_UNICODE);
                }
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                if (strpos($message, 'não encontrado') !== false) {
                    http_response_code(403);
                } else {
                    http_response_code(500);
                }
                echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            }
            break;
    }
} catch (Exception $e) {
    error_log("Erro na API de usuários [" . $_SERVER['REQUEST_METHOD'] . "]: " . $e->getMessage() . " - " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
