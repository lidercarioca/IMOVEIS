<?php
header('Content-Type: application/json');
session_start();
require_once '../auth.php';
require_once '../config/database.php';

checkAuth();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['tipo']) || !isset($data['valor']) || !isset($data['data_transacao'])) {
        throw new Exception('Dados incompletos');
    }
    
    // Validar tipo
    $tiposValidos = ['receita', 'despesa', 'comissao'];
    if (!in_array($data['tipo'], $tiposValidos)) {
        throw new Exception('Tipo inválido');
    }
    
    $tipo = $data['tipo'];
    $valor = floatval($data['valor']);
    $data_transacao = $data['data_transacao'];
    $descricao = $data['descricao'] ?? '';
    $categoria = $data['categoria'] ?? 'Outra';
    $propertyId = $data['property_id'] ?? null;
    $userId = $_SESSION['user_id'];
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    // Admin pode registrar para outro usuário
    if ($isAdmin && isset($data['user_id'])) {
        $userId = $data['user_id'];
    }
    
    // Validar valor
    if ($valor <= 0) {
        throw new Exception('Valor deve ser maior que zero');
    }
    
    // Validar data
    if (!DateTime::createFromFormat('Y-m-d', $data_transacao)) {
        throw new Exception('Data inválida');
    }
    
    // Validar propriedade se fornecida
    if ($propertyId) {
        $stmtCheck = $pdo->prepare("SELECT id FROM properties WHERE id = ?");
        $stmtCheck->execute([$propertyId]);
        if (!$stmtCheck->fetch()) {
            throw new Exception('Propriedade não encontrada');
        }
    }
    
    // Criar tabela se não existir
    $createTable = "CREATE TABLE IF NOT EXISTS financeiro (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT,
        user_id INT NOT NULL,
        tipo VARCHAR(50),
        descricao VARCHAR(255),
        valor DECIMAL(10, 2) NOT NULL,
        data_transacao DATE NOT NULL,
        categoria VARCHAR(100),
        status VARCHAR(50) DEFAULT 'pendente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($createTable);
    
    // Inserir transação
    $stmt = $pdo->prepare("INSERT INTO financeiro (tipo, property_id, user_id, descricao, valor, data_transacao, categoria)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([$tipo, $propertyId, $userId, $descricao, $valor, $data_transacao, $categoria]);
    
    $id = $pdo->lastInsertId();
    
    // Buscar o registro criado
    $result = $pdo->prepare("SELECT * FROM financeiro WHERE id = ?");
    $result->execute([$id]);
    $transacao = $result->fetch();
    $transacao['valor'] = (float)$transacao['valor'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Transação registrada com sucesso',
        'data' => $transacao
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
