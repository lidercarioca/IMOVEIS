<?php
/**
 * NotificationHelper - Helper para enviar Push Notifications (ASSÍNCRONO)
 * Phase 9.3
 */

class NotificationHelper {
    
    /**
     * Envia uma notificação push de forma ASSÍNCRONA (não bloqueia)
     * 
     * @param string $title Título da notificação
     * @param string $body Corpo da mensagem
     * @param string $actionUrl URL para onde navegar ao clicar
     * @param array $data Dados adicionais personalizados
     * @return bool true (sempre retorna true, resposta é processada em background)
     */
    public static function send($title, $body, $actionUrl = '/', $data = []) {
        try {
            $payload = [
                'title' => $title,
                'body' => $body,
                'icon' => '/assets/imagens/logo/logo_padrao.png',
                'badge' => '/assets/imagens/logo/logo_padrao.png',
                'data' => array_merge(['actionUrl' => $actionUrl], $data)
            ];

            // Usar curl com timeout curto e conexão em background
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => 'http://localhost/api/v1/notifications/sendNotification.php',
                CURLOPT_RETURNTRANSFER => false,  // Não aguard resposta
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT_MS => 1000,       // Timeout muito curto para não bloquear
                CURLOPT_CONNECTTIMEOUT_MS => 500, // Timeout da conexão
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Internal-Service: true'
                ],
                CURLOPT_NOSIGNAL => 1,            // Importante para timeouts menores de 1s
                CURLOPT_FORBID_REUSE => true      // Fechar conexão imediatamente
            ]);
            
            // Executar e fechar imediatamente (background)
            curl_exec($ch);
            // curl é fechado automaticamente pelo garbage collector
            
            return true;
        } catch (Exception $e) {
            error_log('[NotificationHelper] Erro ao enviar: ' . $e->getMessage());
            return true; // Não bloqueia o fluxo
        }
    }
    
    /**
     * Notifica sobre novo imóvel criado
     * @param array $propertyData Dados do imóvel (id, name, price, owner_name, etc)
     */
    public static function notifyNewProperty(array $propertyData) {
        $title = 'Novo Imóvel';
        $body = 'Novo imóvel adicionado: ' . ($propertyData['name'] ?? 'Sem nome');
        $actionUrl = '/properties/' . ($propertyData['id'] ?? '#');
        
        return self::send($title, $body, $actionUrl, [
            'type' => 'property_created',
            'property_id' => $propertyData['id'] ?? null,
            'property_name' => $propertyData['name'] ?? null
        ]);
    }
}
