<?php
require_once '../config/database.php';
require_once '../auth.php';

// Verifica autenticação e se é admin
checkAuth();
checkAdmin();

header('Content-Type: application/json');

try {
    switch($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Listar usuários (excluindo o admin principal)
            $sql = "SELECT id, username, role, name, email, created_at, last_login, active 
                   FROM users 
                   WHERE username != 'admin'
                   ORDER BY created_at DESC";
            $stmt = $pdo->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $users]);
            break;

    case 'POST':
        // Criar novo usuário
        $data = json_decode(file_get_contents('php://input'), true);
        
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
            
            echo json_encode(['success' => false, 'message' => $message]);
        }
        break;

    case 'PUT':
        // Atualizar usuário
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data['id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
            exit;
        }
        
        try {
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updates[] = "name = :name";
                $params[':name'] = $data['name'];
            }
            
            if (isset($data['email'])) {
                $updates[] = "email = :email";
                $params[':email'] = $data['email'];
            }
            
            if (isset($data['role'])) {
                $updates[] = "role = :role";
                $params[':role'] = $data['role'];
            }
            
            if (isset($data['active'])) {
                $updates[] = "active = :active";
                $params[':active'] = $data['active'];
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
            error_log("Erro ao atualizar usuário: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar usuário']);
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
            error_log("Erro ao deletar usuário: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar usuário']);
        }
        break;
    }
} catch (Exception $e) {
    error_log("Erro na API de usuários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
