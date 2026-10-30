<?php
/**
 * app/utils/SecureLogger.php
 * Logging seguro com redação de dados sensíveis
 */

class SecureLogger {
    
    // Palavras-chave que indicam dados sensíveis
    private static $sensitiveKeys = [
        'password', 'passwd', 'pass', 'pwd',
        'secret', 'token', 'auth', 'api_key',
        'private_key', 'secret_key', 'credit_card',
        'cc', 'cvv', 'ssn', 'pin'
    ];
    
    /**
     * Sanitiza dados para logging removendo informações sensíveis
     */
    public static function sanitizeForLog($data) {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = self::sanitizeValue($key, $value);
            }
            return $sanitized;
        } elseif (is_object($data)) {
            $sanitized = (array) $data;
            foreach ($sanitized as $key => $value) {
                $sanitized[$key] = self::sanitizeValue($key, $value);
            }
            return (object) $sanitized;
        }
        return $data;
    }
    
    private static function sanitizeValue($key, $value) {
        $keyLower = strtolower($key);
        
        // Verifica se a chave contém palavras-chave sensíveis
        foreach (self::$sensitiveKeys as $sensitive) {
            if (strpos($keyLower, $sensitive) !== false) {
                return '***REDACTED***';
            }
        }
        
        if (is_array($value)) {
            return self::sanitizeForLog($value);
        }
        
        return $value;
    }
    
    /**
     * Log seguro com redação automática
     */
    public static function logRequest($endpoint, $method, $data = [], $extra = '') {
        $dataSafe = self::sanitizeForLog($data);
        $logMsg = date('c') . " [$method $endpoint] " 
            . "User: " . ($_SESSION['username'] ?? 'N/A') 
            . " | Data: " . json_encode($dataSafe, JSON_UNESCAPED_UNICODE);
        
        if (!empty($extra)) {
            $logMsg .= " | " . $extra;
        }
        
        $logMsg .= "\n";
        
        $logFile = __DIR__ . '/../../logs/api_requests.log';
        @file_put_contents($logFile, $logMsg, FILE_APPEND);
    }
    
    /**
     * Log de erros com contexto seguro
     */
    public static function logError($endpoint, $error, $context = []) {
        $contextSafe = self::sanitizeForLog($context);
        $logMsg = date('c') . " [ERROR $endpoint] "
            . "Message: " . $error
            . " | Context: " . json_encode($contextSafe, JSON_UNESCAPED_UNICODE)
            . " | User: " . ($_SESSION['username'] ?? 'N/A')
            . "\n";
        
        $logFile = __DIR__ . '/../../logs/api_errors.log';
        @file_put_contents($logFile, $logMsg, FILE_APPEND);
    }
}
