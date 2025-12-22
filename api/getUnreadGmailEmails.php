<?php
/**
 * API para obter EMAILS reais não lidos do Gmail/servidor do cliente
 * ATENÇÃO: Esta API retorna EMAILS do Gmail/servidor, NÃO mensagens de formulário
 * Para mensagens de contato do formulário, use getUnreadEmails.php
 * 
 * Requer integração com Gmail API (google/apiclient)
 * Arquivo de configuração: google/GmailService.php
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
    
    // Apenas admins podem acessar emails do cliente
    if (!isAdmin()) {
        http_response_code(403);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Acesso negado. Apenas admins podem visualizar emails do cliente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Verificar se Gmail API está configurada
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        http_response_code(503);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Gmail API não está instalada. Configure a integração Gmail para habilitar este recurso.',
            'type' => 'gmail_emails',
            'status' => 'not_configured'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    require_once '../vendor/autoload.php';
    
    // Verificar se GmailService existe
    if (!file_exists(__DIR__ . '/../google/GmailService.php')) {
        http_response_code(503);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Serviço Gmail não configurado. Entre em contato com o administrador.',
            'type' => 'gmail_emails',
            'status' => 'not_configured'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    require_once __DIR__ . '/../google/GmailService.php';
    
    // Inicializa serviço Gmail
    $gmailService = new GmailService();
    
    // Busca emails não lidos
    $unreadGmailEmails = $gmailService->listUnread(50);
    
    $emails = [];
    foreach ($unreadGmailEmails as $gmailMsg) {
        $msgId = $gmailMsg->getId();
        
        // Busca detalhes da mensagem
        $fullMsg = $gmailService->getMessage($msgId, 'full');
        $headers = GmailService::parseHeaders($fullMsg);
        
        $emails[] = [
            'id' => $msgId,
            'from' => $headers['From'] ?? 'desconhecido',
            'subject' => $headers['Subject'] ?? '(sem assunto)',
            'date' => $headers['Date'] ?? null,
            'snippet' => $fullMsg->getSnippet() ?? '',
            'source' => 'gmail'
        ];
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'unread_count' => count($emails),
        'emails' => $emails,
        'type' => 'gmail_emails',
        'source' => 'Gmail API'
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar emails do Gmail: ' . $e->getMessage(),
        'type' => 'gmail_emails'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
