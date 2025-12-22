<?php
/**
 * API para criar notificações de EMAILS reais do Gmail
 * SEPARADO de mensagens de formulário (createEmailNotification.php)
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
    
    // Apenas admins podem criar notificações de emails do cliente
    if (!isAdmin()) {
        http_response_code(403);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Acesso negado'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['gmail_id']) || !isset($data['from']) || !isset($data['subject'])) {
        http_response_code(400);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetros obrigatórios faltando: gmail_id, from, subject'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Validação de email
    if (!filter_var($data['from'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Email do remetente inválido'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Verifica se a notificação para este email já existe (evita duplicatas)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM notifications 
        WHERE type = 'gmail' 
        AND link LIKE CONCAT('%', ?, '%')
        AND is_read = FALSE
    ");
    
    $stmt->execute([$data['gmail_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        // Notificação já existe
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Notificação já existe para este email do Gmail'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Cria a notificação
    $title = 'Novo Email: ' . substr($data['subject'], 0, 50);
    $message = 'Você recebeu um novo email de ' . $data['from'] . ': ' . substr($data['snippet'] ?? '', 0, 100);
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (type, title, message, link, is_read, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        'gmail',
        $title,
        $message,
        'painel.php?tab=emails&gmail_id=' . urlencode($data['gmail_id']),
        0,
        $_SESSION['user_id']
    ]);
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Notificação de email Gmail criada com sucesso'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log('[createGmailNotification] Erro: ' . $e->getMessage(), 3, __DIR__ . '/../logs/api_errors.log');
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao criar notificação de email: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
