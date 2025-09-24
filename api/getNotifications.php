<?php
require_once '../auth.php';
require_once '../config/database.php';
checkAuth();

header('Content-Type: application/json');

try {
    $userId = $_SESSION['user_id'];
    
    // Busca notificações não lidas primeiro, depois as lidas, limitando a 50 no total
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? OR user_id IS NULL 
        ORDER BY is_read ASC, created_at DESC 
        LIMIT 50
    ");
    
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Conta total de não lidas
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE (user_id = ? OR user_id IS NULL) 
        AND is_read = FALSE
    ");
    
    $stmtCount->execute([$userId]);
    $unreadCount = $stmtCount->fetchColumn();
    
    echo json_encode([
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar notificações']);
}
