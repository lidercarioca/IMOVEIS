<?php
header('Content-Type: application/json');
session_start();
require_once '../auth.php';
require_once '../config/database.php';
require_once '../app/utils/NotificationManager.php';

checkAuth();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['property_id']) || !isset($data['data_agendamento'])) {
        throw new Exception('Dados incompletos');
    }
    
    $propertyId = $data['property_id'];
    $dataAgendamento = $data['data_agendamento'];
    $leadsId = $data['leads_id'] ?? null;
    $descricao = $data['descricao'] ?? '';
    $userId = $_SESSION['user_id'];
    
    // Verificar se a propriedade existe
    $stmtCheck = $pdo->prepare("SELECT id FROM properties WHERE id = ?");
    $stmtCheck->execute([$propertyId]);
    if (!$stmtCheck->fetch()) {
        throw new Exception('Propriedade não encontrada');
    }
    
    // Criar tabela de agendamentos se não existir
    $createTable = "CREATE TABLE IF NOT EXISTS agendamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        leads_id INT,
        user_id INT NOT NULL,
        data_agendamento DATETIME NOT NULL,
        status VARCHAR(50) DEFAULT 'confirmado' COMMENT 'confirmado, cancelado, realizado',
        descricao TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (leads_id) REFERENCES leads(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($createTable);
    
    // Inserir agendamento
    $stmt = $pdo->prepare("INSERT INTO agendamentos (property_id, leads_id, user_id, data_agendamento, descricao)
            VALUES (?, ?, ?, ?, ?)");
    
    $stmt->execute([$propertyId, $leadsId, $userId, $dataAgendamento, $descricao]);
    
    $id = $pdo->lastInsertId();
    
    // Buscar o agendamento criado
    $result = $pdo->prepare("SELECT a.*, p.title as property_title, u.name as user_name
                                     FROM agendamentos a
                                     LEFT JOIN properties p ON a.property_id = p.id
                                     LEFT JOIN users u ON a.user_id = u.id
                                     WHERE a.id = ?");
    
    $result->execute([$id]);
    $agendamento = $result->fetch();
    
    // Disparar notificação de novo agendamento
    try {
        $notificationManager = new NotificationManager($pdo);
        $notificationManager->notifyNewAgendamento(
            $agendamento['property_title'],
            $agendamento['data_agendamento'],
            $agendamento['user_name']
        );
    } catch (Exception $notifyError) {
        // Log erro mas não falha a criação do agendamento
        error_log("Erro ao notificar agendamento: " . $notifyError->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Agendamento criado com sucesso',
        'data' => $agendamento
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
