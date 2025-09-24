<?php
require_once '../auth.php';
require_once '../config/database.php';
checkAuth();

header('Content-Type: application/json');

try {
    $userId = $_SESSION['user_id'];
    
    // Busca todas as mensagens do usuário, não lidas primeiro, depois as lidas
    $stmt = $pdo->prepare("
        SELECT m.*, p.title as property_title 
        FROM messages m 
        LEFT JOIN properties p ON m.property_id = p.id 
        WHERE m.user_id = ?
        ORDER BY m.is_read ASC, m.created_at DESC 
        LIMIT 50
    ");
    
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Conta total de não lidas
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) FROM messages 
        WHERE is_read = FALSE AND user_id = ?
    ");
    
    $stmtCount->execute([$userId]);
    $unreadCount = $stmtCount->fetchColumn();
    
    echo json_encode([
        'messages' => $messages,
        'unread_count' => $unreadCount
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar mensagens']);
}
