<?php

namespace App\Integrations;

use PDO;
use DateTime;
use Exception;

/**
 * WebhookManager
 * 
 * Gerencia webhooks com retry logic, exponential backoff e payload enriquecido
 */
class WebhookManager {
    private PDO $pdo;
    private string $tableName = 'webhook_subscriptions';
    private string $logsTableName = 'webhook_logs';
    private int $maxRetries = 5;
    private float $baseDelay = 1; // segundos
    
    public function __construct(?PDO $pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            global $pdo;
            if (!$pdo) {
                throw new Exception("PDO connection not available");
            }
            $this->pdo = $pdo;
        }
    }
    
    /**
     * Disparar evento de webhook
     */
    public function dispatchEvent(string $eventType, array $data, ?int $userId = null, ?array $metadata = null): bool {
        try {
            $eventId = $this->generateEventId();
            $timestamp = (new DateTime())->format('Y-m-d H:i:s');
            
            // Prepara payload enriquecido
            $payload = [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'timestamp' => $timestamp,
                'data' => $data,
                'metadata' => array_merge(
                    $metadata ?? [],
                    [
                        'user_id' => $userId,
                        'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_'),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]
                )
            ];
            
            // Busca subscriptions ativas para este evento
            $stmt = $this->pdo->prepare("
                SELECT id, url, secret, retry_count, last_retry_at 
                FROM {$this->tableName}
                WHERE event_type = :event_type 
                AND active = 1 
                AND user_id = COALESCE(:user_id, user_id)
            ");
            
            $stmt->execute([
                ':event_type' => $eventType,
                ':user_id' => $userId
            ]);
            
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Dispara webhook para cada subscription
            foreach ($subscriptions as $subscription) {
                $this->sendWebhookAsync(
                    $subscription['id'],
                    $subscription['url'],
                    $payload,
                    $subscription['secret'],
                    0,
                    $subscription['last_retry_at']
                );
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Webhook dispatch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar webhook com retry logic
     */
    private function sendWebhookAsync(
        int $subscriptionId,
        string $url,
        array $payload,
        ?string $secret = null,
        int $retryCount = 0,
        ?string $lastRetryAt = null
    ): void {
        try {
            // Calcula delay com exponential backoff
            $delay = $this->calculateBackoffDelay($retryCount);
            
            // Registra tentativa
            $this->logWebhookAttempt($subscriptionId, $payload['event_id'], 'pending', $delay);
            
            // Prepara headers
            $headers = [
                'Content-Type: application/json',
                'User-Agent: RR-Imoveis-Webhook/1.0',
                'X-Webhook-ID: ' . $payload['event_id'],
                'X-Webhook-Retry: ' . $retryCount
            ];
            
            // Adiciona assinatura HMAC se secret existe
            if ($secret) {
                $signature = hash_hmac('sha256', json_encode($payload), $secret);
                $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
            }
            
            // Envia webhook em background (usando at ou fallback)
            $this->enqueueWebhookJob($subscriptionId, $url, $payload, $secret, $retryCount, $delay);
            
        } catch (Exception $e) {
            error_log("Webhook async send error: " . $e->getMessage());
        }
    }
    
    /**
     * Enfileirar webhook para envio posterior
     */
    private function enqueueWebhookJob(
        int $subscriptionId,
        string $url,
        array $payload,
        ?string $secret,
        int $retryCount,
        float $delay
    ): void {
        try {
            // Cria arquivo de fila ou usa banco de dados
            $queueDir = dirname(__DIR__, 2) . '/queue/webhooks';
            if (!is_dir($queueDir)) {
                mkdir($queueDir, 0755, true);
            }
            
            $jobFile = $queueDir . '/' . uniqid('webhook_') . '.json';
            $jobData = [
                'subscription_id' => $subscriptionId,
                'url' => $url,
                'payload' => $payload,
                'secret' => $secret,
                'retry_count' => $retryCount,
                'delay' => $delay,
                'created_at' => time(),
                'execute_at' => time() + $delay
            ];
            
            file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT));
            chmod($jobFile, 0644);
        } catch (Exception $e) {
            error_log("Queue enqueue error: " . $e->getMessage());
        }
    }
    
    /**
     * Processar fila de webhooks (executar via cron)
     */
    public function processQueue(): array {
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'retried' => 0
        ];
        
        try {
            $queueDir = dirname(__DIR__, 2) . '/queue/webhooks';
            if (!is_dir($queueDir)) {
                return $results;
            }
            
            $files = glob($queueDir . '/*.json');
            foreach ($files as $file) {
                $results['processed']++;
                $jobData = json_decode(file_get_contents($file), true);
                
                // Verifica se é hora de executar
                if ($jobData['execute_at'] > time()) {
                    continue;
                }
                
                // Tenta enviar
                $success = $this->executeWebhookJob($jobData);
                
                if ($success) {
                    $results['succeeded']++;
                    unlink($file); // Remove da fila
                } else if ($jobData['retry_count'] < $this->maxRetries) {
                    // Reinscreve para retry
                    $jobData['retry_count']++;
                    $jobData['delay'] = $this->calculateBackoffDelay($jobData['retry_count']);
                    $jobData['execute_at'] = time() + $jobData['delay'];
                    file_put_contents($file, json_encode($jobData, JSON_PRETTY_PRINT));
                    $results['retried']++;
                } else {
                    // Falhou após máximo de tentativas
                    $results['failed']++;
                    $this->logWebhookAttempt(
                        $jobData['subscription_id'],
                        $jobData['payload']['event_id'],
                        'failed',
                        0,
                        'Max retries exceeded'
                    );
                    unlink($file);
                }
            }
        } catch (Exception $e) {
            error_log("Queue process error: " . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Obter tamanho da fila de webhooks pendentes
     */
    public function getPendingQueueSize(): int {
        try {
            $queueDir = dirname(__DIR__, 2) . '/queue/webhooks';
            if (!is_dir($queueDir)) {
                return 0;
            }

            $files = glob($queueDir . '/*.json');
            return is_array($files) ? count($files) : 0;
        } catch (Exception $e) {
            error_log("Webhook queue size error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Executar um job de webhook
     */
    private function executeWebhookJob(array $jobData): bool {
        try {
            $ch = curl_init($jobData['url']);
            
            $headers = [
                'Content-Type: application/json',
                'User-Agent: RR-Imoveis-Webhook/1.0',
                'X-Webhook-ID: ' . $jobData['payload']['event_id'],
                'X-Webhook-Retry: ' . $jobData['retry_count']
            ];
            
            if ($jobData['secret']) {
                $signature = hash_hmac('sha256', json_encode($jobData['payload']), $jobData['secret']);
                $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
            }
            
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($jobData['payload']),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            // Sucesso: 2xx
            if ($httpCode >= 200 && $httpCode < 300) {
                $this->logWebhookAttempt(
                    $jobData['subscription_id'],
                    $jobData['payload']['event_id'],
                    'success',
                    0,
                    "HTTP $httpCode"
                );
                return true;
            }
            
            // Erro temporário: 5xx ou timeout
            if ($httpCode >= 500 || empty($response)) {
                $this->logWebhookAttempt(
                    $jobData['subscription_id'],
                    $jobData['payload']['event_id'],
                    'retry',
                    0,
                    "HTTP $httpCode - " . ($error ?: 'Timeout/Network error')
                );
                return false;
            }
            
            // Erro permanente: 4xx
            $this->logWebhookAttempt(
                $jobData['subscription_id'],
                $jobData['payload']['event_id'],
                'failed',
                0,
                "HTTP $httpCode - Permanent error"
            );
            return false;
            
        } catch (Exception $e) {
            error_log("Webhook execution error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcular delay com exponential backoff
     */
    private function calculateBackoffDelay(int $retryCount): float {
        // exponential backoff: 1s, 2s, 4s, 8s, 16s (max)
        $delay = $this->baseDelay * pow(2, $retryCount);
        return min($delay, 300); // máximo 5 minutos
    }
    
    /**
     * Registrar tentativa de webhook
     */
    private function logWebhookAttempt(
        int $subscriptionId,
        string $eventId,
        string $status,
        float $delay = 0,
        ?string $error = null
    ): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->logsTableName}
                (subscription_id, event_id, status, delay_seconds, error_message, created_at)
                VALUES (:subscription_id, :event_id, :status, :delay, :error, NOW())
            ");
            
            $stmt->execute([
                ':subscription_id' => $subscriptionId,
                ':event_id' => $eventId,
                ':status' => $status,
                ':delay' => $delay,
                ':error' => $error
            ]);
            
            // Atualiza último retry da subscription
            $stmt = $this->pdo->prepare("
                UPDATE {$this->tableName}
                SET last_retry_at = NOW(), retry_count = retry_count + 1
                WHERE id = :id
            ");
            $stmt->execute([':id' => $subscriptionId]);
            
        } catch (Exception $e) {
            error_log("Webhook log error: " . $e->getMessage());
        }
    }
    
    /**
     * Gerar ID único para evento
     */
    private function generateEventId(): string {
        return 'evt_' . bin2hex(random_bytes(16));
    }
    
    /**
     * Verificar integridade de webhook (para processadores)
     */
    public static function verifyWebhookSignature(string $payload, string $signature, string $secret): bool {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}
