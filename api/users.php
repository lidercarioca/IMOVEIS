<?php
// Limpa qualquer output anterior
ob_start();

require_once '../config/database.php';
require_once '../auth.php';

// Limpa output buffer se necessário
while (ob_get_level() > 1) {
    ob_end_clean();
}

// Verifica autenticação
checkAuth();

// Verifica se é admin (retorna erro JSON em vez de redirecionar)
if (!isAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    switch($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Verificar se foi passado um ID específico
            $userId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
            
            if ($userId) {
                // Buscar um usuário específico por ID
                $sql = "SELECT id, username, role, name, email, created_at, last_login, active 
                       FROM users 
                       WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $userId]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Listar todos os usuários (excluindo o admin principal)
                $sql = "SELECT id, username, role, name, email, created_at, last_login, active 
                       FROM users 
                       WHERE username != 'admin'
                       ORDER BY created_at DESC";
                $stmt = $pdo->query($sql);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode(['success' => true, 'data' => $users], JSON_UNESCAPED_UNICODE);
            break;

    case 'POST':
        // Criar novo usuário
        $data = json_decode(file_get_contents('php://input'), true);
        // Log da requisição para debug (mascarando dados sensíveis)
        $dataSafe = $data;
        if (isset($dataSafe['password'])) {
            $dataSafe['password'] = '***REDACTED***';
        }
        $reqLog = date('c') . " [POST users request] User: " . ($_SESSION['username'] ?? 'N/A') . " | Payload: " . json_encode($dataSafe) . "\n";
        @file_put_contents(__DIR__ . '/../logs/api_requests.log', $reqLog, FILE_APPEND);
        
        if (!$data['username'] || !$data['password'] || !$data['name'] || !$data['email']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
            exit;
        }
        
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = $data['role'] ?? 'user';
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, email) VALUES (:username, :password, :role, :name, :email)");
            
            $stmt->execute([
                ':username' => $data['username'],
                ':password' => $password,
                ':role' => $role,
                ':name' => $data['name'],
                ':email' => $data['email']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Usuário criado com sucesso']);
            
        } catch (PDOException $e) {
            // Log detalhado em arquivo para debug
            $logData = date('c') . " [POST users] Erro PDO: " . $e->getMessage() . " | Code: " . $e->getCode() . " | User: " . ($_SESSION['username'] ?? 'N/A') . "\n";
            @file_put_contents(__DIR__ . '/../logs/api_errors.log', $logData, FILE_APPEND);
            error_log("Erro PDO ao criar usuário: " . $e->getMessage());
            http_response_code(400);
            
            if ($e->getCode() == 23000) { // Código para violação de chave única
                if (strpos($e->getMessage(), "username") !== false) {
                    $message = "Este nome de usuário já está em uso. Por favor, escolha outro.";
                } else if (strpos($e->getMessage(), "email") !== false) {
                    $message = "Este e-mail já está cadastrado. Por favor, use outro e-mail.";
                } else {
                    $message = "Dados duplicados encontrados. Por favor, verifique as informações.";
                }
            } else {
                $message = "Erro ao criar usuário. Por favor, tente novamente.";
            }
            
            echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'PUT':
        // Atualizar usuário
        $data = json_decode(file_get_contents('php://input'), true);
        // Log da requisição para debug (mascarando dados sensíveis)
        $dataSafe = $data;
        if (isset($dataSafe['password'])) {
            $dataSafe['password'] = '***REDACTED***';
        }
        $reqLog = date('c') . " [PUT users request] User: " . ($_SESSION['username'] ?? 'N/A') . " | Payload: " . json_encode($dataSafe) . "\n";
        @file_put_contents(__DIR__ . '/../logs/api_requests.log', $reqLog, FILE_APPEND);
        
        if (!$data['id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
            exit;
        }
        
        try {
            $updates = [];
            $params = [];
            
            if (isset($data['name']) && !empty($data['name'])) {
                $updates[] = "name = :name";
                $params[':name'] = $data['name'];
            }
            
            if (isset($data['email']) && !empty($data['email'])) {
                $updates[] = "email = :email";
                $params[':email'] = $data['email'];
            }
            
            if (isset($data['role']) && !empty($data['role'])) {
                $updates[] = "role = :role";
                $params[':role'] = $data['role'];
            }
            
            if (isset($data['active']) && $data['active'] !== '') {
                $updates[] = "active = :active";
                $params[':active'] = $data['active'] ? 1 : 0;
            }
            
            if (isset($data['password']) && !empty($data['password'])) {
                $updates[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nenhum dado para atualizar']);
                exit;
            }
            
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = :id";
            $params[':id'] = $data['id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso']);
            
        } catch (PDOException $e) {
            // Log detalhado em arquivo para debug
            $payload = json_encode(array_replace($data ?? [], ['password' => isset($data['password']) ? '***' : '']));
            $logData = date('c') . " [PUT users] Erro PDO: " . $e->getMessage() . " | Code: " . $e->getCode() . " | User: " . ($_SESSION['username'] ?? 'N/A') . " | Payload: " . $payload . "\n";
            @file_put_contents(__DIR__ . '/../logs/api_errors.log', $logData, FILE_APPEND);
            error_log("Erro ao atualizar usuário: " . $e->getMessage());
            http_response_code(400);
            
            if ($e->getCode() == 23000) { // Código para violação de chave única
                if (strpos($e->getMessage(), "email") !== false) {
                    $message = "Este e-mail já está cadastrado. Por favor, use outro e-mail.";
                } else {
                    $message = "Dados duplicados encontrados. Por favor, verifique as informações.";
                }
            } else {
                $message = "Erro ao atualizar usuário. Por favor, tente novamente.";
            }
            
            echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'DELETE':
        // Deletar usuário
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Usuário deletado com sucesso']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
            }
            
        } catch (PDOException $e) {
            $logData = date('c') . " [DELETE users] Erro PDO: " . $e->getMessage() . " | Code: " . $e->getCode() . " | User: " . ($_SESSION['username'] ?? 'N/A') . "\n";
            @file_put_contents(__DIR__ . '/../logs/api_errors.log', $logData, FILE_APPEND);
            error_log("Erro ao deletar usuário: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar usuário'], JSON_UNESCAPED_UNICODE);
        }
        break;
    }
} catch (Exception $e) {
    error_log("Erro na API de usuários [" . $_SERVER['REQUEST_METHOD'] . "]: " . $e->getMessage() . " - " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
