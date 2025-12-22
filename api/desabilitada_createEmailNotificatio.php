<?php
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
    
    // Notificações apenas para admins
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Acesso negado'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email_id']) || !isset($data['from_name']) || !isset($data['from_email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetros obrigatórios faltando'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Validação do e-mail do remetente
    if (!filter_var($data['from_email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'E-mail do remetente inválido'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Verifica se a notificação para este email já existe
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM notifications 
        WHERE type = 'email' 
        AND title LIKE CONCAT('%', ?, '%')
        AND is_read = FALSE
    ");
    
    $stmt->execute([$data['email_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        // Notificação já existe
        echo json_encode([
            'success' => true,
            'message' => 'Notificação já existe para este email'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Cria a notificação
    $title = 'Novo Email de ' . $data['from_name'];
    $message = 'Você recebeu um novo email de ' . $data['from_email'];
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (type, title, message, link, is_read, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        'email',
        $title,
        $message,
        'painel.php?tab=messages',
        0,
        $_SESSION['user_id']
    ]);
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Notificação criada com sucesso'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao criar notificação: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
