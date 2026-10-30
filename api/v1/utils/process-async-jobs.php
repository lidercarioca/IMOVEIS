<?php
/**
 * Processador de jobs assíncronos
 * Executa em background sem bloquear a requisição do usuário
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/utils/AsyncJobQueue.php';

// Executar sem timeout
set_time_limit(300);

try {
    $queue = new AsyncJobQueue($pdo);
    $result = $queue->processPending(20);
    
    // Fechar conexão rápido (keep-alive não necessário)
    header('Connection: close');
    header('Content-Length: ' . strlen(json_encode($result)));
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
