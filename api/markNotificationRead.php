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
        // Marca uma notificação específica como lida
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND (user_id = ? OR user_id IS NULL)
        ");
        $stmt->execute([$id, $userId]);
    } else {
        // Marca todas as notificações como lidas
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE (user_id = ? OR user_id IS NULL)
        ");
        $stmt->execute([$userId]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar notificação']);
}
