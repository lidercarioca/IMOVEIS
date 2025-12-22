<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/check_auth.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !$data['id']) {
        throw new Exception('ID da transação é obrigatório');
    }
    
    $id = $data['id'];
    
    // Verificar se é admin ou proprietário da transação
    $stmt = $pdo->prepare('SELECT user_id FROM financeiro WHERE id = ?');
    $stmt->execute([$id]);
    $transacao = $stmt->fetch();
    
    if (!$transacao) {
        throw new Exception('Transação não encontrada');
    }
    
    // Se não for admin, verificar se é o proprietário
    if ($_SESSION['user_role'] !== 'admin' && $transacao['user_id'] != $_SESSION['user_id']) {
        throw new Exception('Permissão negada');
    }
    
    // Montar array de updates
    $updates = [];
    $params = [];
    
    if (isset($data['tipo'])) {
        $updates[] = 'tipo = ?';
        $params[] = $data['tipo'];
    }
    
    if (isset($data['descricao'])) {
        $updates[] = 'descricao = ?';
        $params[] = $data['descricao'];
    }
    
    if (isset($data['valor'])) {
        $updates[] = 'valor = ?';
        $params[] = floatval($data['valor']);
    }
    
    if (isset($data['data_transacao'])) {
        $updates[] = 'data_transacao = ?';
        $params[] = $data['data_transacao'];
    }
    
    if (isset($data['categoria'])) {
        $updates[] = 'categoria = ?';
        $params[] = $data['categoria'];
    }
    
    if (isset($data['status'])) {
        $updates[] = 'status = ?';
        $params[] = $data['status'];
    }
    
    $updates[] = 'updated_at = NOW()';
    
    if (empty($updates)) {
        throw new Exception('Nenhum campo para atualizar');
    }
    
    $params[] = $id;
    
    $sql = 'UPDATE financeiro SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        // Retornar transação atualizada
        $stmt = $pdo->prepare('SELECT * FROM financeiro WHERE id = ?');
        $stmt->execute([$id]);
        $transacao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Transação atualizada com sucesso',
            'data' => $transacao
        ]);
    } else {
        throw new Exception('Nenhum registro foi atualizado');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}