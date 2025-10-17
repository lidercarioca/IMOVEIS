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
    
    // Busca notificações de backup
    $stmt = $pdo->prepare("
        SELECT id, type, message, created_at, status 
        FROM backup_notifications 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Limpa buffer e envia resposta
    if (ob_get_length()) ob_clean();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
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