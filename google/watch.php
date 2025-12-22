<?php
/**
 * Script para ativar o watch no inbox do Gmail via Pub/Sub
 * Execução: php google/watch.php
 * 
 * Este script:
 * 1. Carrega o token de autorização
 * 2. Ativa o watch no inbox via Gmail API
 * 3. Mostra o historyId para rastrear mudanças
 * 4. Configura o tópico Pub/Sub
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/GmailService.php';

$topicName = getenv('GOOGLE_PUBSUB_TOPIC') ?: '';

if (!$topicName) {
    echo "Erro: GOOGLE_PUBSUB_TOPIC não configurado em .env\n";
    echo "Exemplo: GOOGLE_PUBSUB_TOPIC=projects/seu-project-id/topics/gmail-notifications\n";
    exit(1);
}

try {
    $g = new GmailService();
    $watchResult = $g->watchInbox($topicName);
    
    echo "✓ Watch ativado no inbox!\n";
    echo "  HistoryId: " . $watchResult->getHistoryId() . "\n";
    echo "  Expira em: " . $watchResult->getExpiration() . " ms\n";
    echo "  Tópico Pub/Sub: " . $topicName . "\n\n";
    echo "Próximos passos:\n";
    echo "1. Configure a subscription do Pub/Sub para fazer push para: https://seu-dominio.com/google/pubsub_push.php\n";
    echo "2. Novos emails dispararão notificações via Pub/Sub\n";
    echo "3. Como fallback, execute: php google/poll_unread.php (a cada 3-5 minutos)\n";
    
} catch (Exception $e) {
    echo "✗ Erro ao ativar watch: " . $e->getMessage() . "\n";
    exit(1);
}

?>
