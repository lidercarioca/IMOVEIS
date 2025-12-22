<?php
/**
 * app/security/RateLimiter.php
 * Rate limiting baseado em IP para prevenir ataques de força bruta
 */

class RateLimiter {
    
    private static $cacheDir = __DIR__ . '/../../logs/rate_limit/';
    private static $requestsPerMinute = 60;
    private static $requestsPerHour = 1000;
    
    public static function init() {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Verifica se o IP excedeu o limite de requisições
     */
    public static function checkLimit($identifier = null) {
        self::init();
        
        if (!$identifier) {
            $identifier = self::getClientIP();
        }
        
        $now = time();
        $cacheFile = self::$cacheDir . 'rl_' . md5($identifier) . '.json';
        
        $data = [];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        
        // Limpar requisições antigas
        $data['requests'] = array_filter($data['requests'] ?? [], function($time) use ($now) {
            return $time > ($now - 3600); // Manter últimas 1 hora
        });
        
        // Contar requisições no último minuto
        $recentRequests = array_filter($data['requests'], function($time) use ($now) {
            return $time > ($now - 60);
        });
        
        // Contar requisições na última hora
        $hourlyRequests = count($data['requests']);
        
        // Verificar limites
        if (count($recentRequests) > self::$requestsPerMinute) {
            return false; // Excedeu limite por minuto
        }
        
        if ($hourlyRequests > self::$requestsPerHour) {
            return false; // Excedeu limite por hora
        }
        
        // Adicionar requisição atual
        $data['requests'][] = $now;
        file_put_contents($cacheFile, json_encode($data));
        
        return true;
    }
    
    /**
     * Obter IP do cliente
     */
    private static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// Inicializar ao carregar
RateLimiter::init();
