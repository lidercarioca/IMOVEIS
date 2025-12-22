<?php
header('Content-Type: application/json');
session_start();
require_once '../auth.php';
require_once '../config/database.php';

checkAuth();

try {
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    
    if (!$id) {
        throw new Exception('ID obrigatório');
    }
    
    $userId = $_SESSION['user_id'];
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    // Verificar permissões
    $checkStmt = $pdo->prepare("SELECT user_id FROM agendamentos WHERE id = ?");
    $checkStmt->execute([$id]);
    $agendamento = $checkStmt->fetch();
    
    if (!$agendamento) {
        throw new Exception('Agendamento não encontrado');
    }
    
    if (!$isAdmin && $agendamento['user_id'] != $userId) {
        throw new Exception('Sem permissão para deletar este agendamento');
    }
    
    // Deletar
    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Agendamento deletado com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
