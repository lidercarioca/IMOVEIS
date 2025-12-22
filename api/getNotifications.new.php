<?php
require_once '../auth.php';
require_once '../config/database.php';

// Garante que nenhum output seja enviado antes do JSON
ob_start();

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');
header('Content-Type: application/json; charset=utf-8');

try {
    // Verifica autenticação
    checkAuth();
    
    $userId = $_SESSION['user_id'];
    
    // Busca notificações
    $stmt = $pdo->prepare("
        SELECT id, title, message, type, created_at, is_read 
        FROM notifications 
        WHERE user_id = ? OR user_id IS NULL 
        ORDER BY is_read ASC, created_at DESC 
        LIMIT 50
    ");
    
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Conta não lidas
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE (user_id = ? OR user_id IS NULL) 
        AND is_read = FALSE
    ");
    
    $stmtCount->execute([$userId]);
    $unreadCount = $stmtCount->fetchColumn();
    
    // Limpa buffer e envia resposta
    if (ob_get_length()) ob_clean();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unreadCount' => $unreadCount
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // Limpa buffer e envia erro
    if (ob_get_length()) ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Encerra execução
exit;
