<?php
require_once '../auth.php';
require_once '../config/database.php';
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$userId = $_SESSION['user_id'];

try {
    if ($id) {
        // Marca uma mensagem específica como lida
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$id, $userId]);
    } else {
        // Marca todas as mensagens como lidas
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar mensagem']);
}
