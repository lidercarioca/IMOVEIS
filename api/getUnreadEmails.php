<?php
/**
 * API para obter MENSAGENS não lidas do formulário de contato
 * ATENÇÃO: Retorna MENSAGENS (tabela 'messages'), NÃO EMAILS reais do Gmail
 * Para emails reais do Gmail/servidor, use getUnreadGmailEmails.php
 */
// limpa output buffer ANTES de qualquer include
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../auth.php';
require_once '../config/database.php';

try {
    ob_end_clean();
    checkAuth();
    ob_start();
    
    // Busca MENSAGENS não lidas (formulários do site)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM messages 
        WHERE is_read = FALSE
    ");
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unreadCount = (int)$result['count'];
    
    // Busca as últimas MENSAGENS não lidas
    $stmt = $pdo->prepare("
        SELECT id, from_name, from_email, subject, created_at
        FROM messages 
        WHERE is_read = FALSE
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'messages' => $messages,
        'type' => 'form_messages'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar emails: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
