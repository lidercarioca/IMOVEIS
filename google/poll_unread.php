<?php
/**
 * Script de polling para buscar emails não lidos do Gmail
 * Execução manual: php google/poll_unread.php
 * Agendar: Adicionar ao cron ou Windows Task Scheduler a cada 3-5 minutos
 * 
 * Este script:
 * 1. Conecta ao Gmail via API
 * 2. Busca mensagens não lidas
 * 3. Evita duplicatas via messageId
 * 4. Cria notificações no painel
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/GmailService.php';

date_default_timezone_set(getenv('TZ') ?: 'America/Sao_Paulo');

$logFile = __DIR__ . '/../logs/gmail_integration.log';
function gLog($msg) { 
    global $logFile; 
    @mkdir(dirname($logFile), 0755, true);
    error_log(date('[c] ') . $msg . PHP_EOL, 3, $logFile); 
}

try {
    gLog('=== Iniciando polling de emails ===');
    
    $g = new GmailService();
    $messages = $g->listUnread(50);
    gLog('Encontrados ' . count($messages) . ' mensagens não lidas.');

    $processedCount = 0;
    $notificationCount = 0;

    foreach ($messages as $m) {
        $msgId = $m->getId();
        $processedCount++;

        // Evita duplicação: verifica se já existe notificação para essa message id
        $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM notifications WHERE link LIKE ? AND type = 'gmail'");
        $stmt->execute(['%gmail_id=' . $msgId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($r && intval($r['c']) > 0) {
            gLog("Message {$msgId} já processada, pulando");
            continue;
        }

        // Busca conteúdo completo
        try {
            $full = $g->getMessage($msgId, 'full');
            $headers = GmailService::parseHeaders($full);
            
            $from = $headers['From'] ?? 'desconhecido';
            $subject = $headers['Subject'] ?? '(sem assunto)';
            $snippet = $full->getSnippet() ?? '';

            // Cria notificação
            $title = 'Novo Email: ' . substr($subject, 0, 50);
            $message = 'Você recebeu um novo email de ' . $from . ': ' . substr($snippet, 0, 100);
            $link = 'painel.php?tab=emails&gmail_id=' . urlencode($msgId);

            $ins = $pdo->prepare("
                INSERT INTO notifications (type, title, message, link, is_read, created_at, user_id) 
                VALUES (?,?,?,?,0,NOW(),NULL)
            ");
            $ins->execute(['gmail', $title, $message, $link]);
            
            $notificationCount++;
            gLog("✓ Notificação criada para message={$msgId} | De: {$from}");
        } catch (Exception $e) {
            gLog("✗ Erro ao processar message={$msgId}: " . $e->getMessage());
        }
    }

    gLog("=== Polling concluído | Processadas: {$processedCount} | Notificações: {$notificationCount} ===\n");

} catch (Exception $e) {
    gLog('ERRO: ' . $e->getMessage());
    error_log('Gmail Polling Error: ' . $e->getMessage());
    exit(1);
}

?>
