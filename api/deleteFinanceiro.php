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
    $checkStmt = $pdo->prepare("SELECT user_id FROM financeiro WHERE id = ?");
    $checkStmt->execute([$id]);
    $transacao = $checkStmt->fetch();
    
    if (!$transacao) {
        throw new Exception('Transação não encontrada');
    }
    
    if (!$isAdmin && $transacao['user_id'] != $userId) {
        throw new Exception('Sem permissão para deletar esta transação');
    }
    
    // Deletar
    $stmt = $pdo->prepare("DELETE FROM financeiro WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transação deletada com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
