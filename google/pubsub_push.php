<?php
/**
 * Endpoint: Google Pub/Sub Push
 * Recebe notificações de novos emails do Gmail via Pub/Sub
 * 
 * Configuração:
 * - URL: https://seu-dominio.com/google/pubsub_push.php
 * - Método: POST
 * - Headers: Content-Type: application/json
 * 
 * Fluxo:
 * 1. Gmail watch() notifica via Pub/Sub
 * 2. Pub/Sub faz POST para este endpoint
 * 3. Extraímos o historyId
 * 4. Buscamos emails não lidos
 * 5. Criamos notificações
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/GmailService.php';

$logFile = __DIR__ . '/../logs/gmail_integration.log';
function gLog($msg) { 
    global $logFile; 
    @mkdir(dirname($logFile), 0755, true);
    error_log(date('[c] [pubsub] ') . $msg . PHP_EOL, 3, $logFile); 
}

// Lê o payload do Pub/Sub
$raw = file_get_contents('php://input');
gLog('Recebido payload: ' . strlen($raw) . ' bytes');

$data = json_decode($raw, true);
if (!$data || !isset($data['message'])) {
    http_response_code(400);
    gLog('Erro: Formato inválido');
    echo json_encode(['success' => false, 'error' => 'Formato inválido']);
    exit();
}

try {
    // Mensagem do Pub/Sub tem data base64
    $payload = $data['message']['data'] ?? null;
    if ($payload) {
        $decoded = base64_decode($payload);
        $json = json_decode($decoded, true);
        
        $historyId = $json['historyId'] ?? null;
        $emailAddress = $json['emailAddress'] ?? null;
        
        gLog("historyId={$historyId}, email={$emailAddress}");
    }

    // Busca emails não lidos (simplificado: usa listUnread)
    $g = new GmailService();
    $messages = $g->listUnread(50);
    gLog('Encontrados ' . count($messages) . ' emails não lidos');

    $notificationCount = 0;
    foreach ($messages as $m) {
        $msgId = $m->getId();
        
        // Evita duplicatas
        $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM notifications WHERE link LIKE ? AND type = 'gmail'");
        $stmt->execute(['%gmail_id=' . $msgId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r && intval($r['c']) > 0) continue;

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
            gLog("Notificação criada para {$msgId}");
        } catch (Exception $e) {
            gLog("Erro processando {$msgId}: " . $e->getMessage());
        }
    }

    gLog("Processadas {$notificationCount} notificações");

} catch (Exception $e) {
    gLog('Erro: ' . $e->getMessage());
}

// Responde 204 No Content ao Pub/Sub (requerido)
http_response_code(204);
exit();

?>
