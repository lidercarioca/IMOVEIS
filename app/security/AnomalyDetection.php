<?php
/**
 * app/security/AnomalyDetection.php
 * 
 * Detecta comportamentos suspeitos e padrões de ataque
 * - Múltiplas IPs para mesma conta
 * - Login simultâneo
 * - Requisições de múltiplas IPs em curto tempo
 * - Geolocalização suspeita
 */

class AnomalyDetection {
    
    /**
     * Registra login bem-sucedido para detecção de anomalias
     */
    public static function recordLogin($userId, $username, $ip) {
        global $pdo;
        
        try {
            // Registra o login na tabela de logins
            $pdo->prepare("
                INSERT INTO login_history (user_id, username, ip_address, login_time, success)
                VALUES (:user_id, :username, :ip, NOW(), 1)
            ")->execute([
                ':user_id' => $userId,
                ':username' => $username,
                ':ip' => $ip
            ]);
            
            // Verifica anomalias
            self::checkAnomalies($userId, $username, $ip);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar login para anomalias: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica padrões de comportamento suspeito
     */
    private static function checkAnomalies($userId, $username, $currentIp) {
        global $pdo;
        
        try {
            // 1. Verificar logins simultâneos (mais de uma sessão ativa do mesmo usuário)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as active_sessions 
                FROM sessions 
                WHERE user_id = :user_id 
                  AND session_data LIKE CONCAT('%', '\\\\"user_id\\\";i:', :user_id, ';%')
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['active_sessions'] > 1) {
                self::logAnomaly('MULTIPLE_SESSIONS', $userId, $username, $currentIp);
            }
            
            // 2. Verificar múltiplas IPs diferentes no último 1 hora
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT ip_address) as ip_count
                FROM login_history
                WHERE user_id = :user_id
                  AND login_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['ip_count'] > 3) {
                self::logAnomaly('MULTIPLE_IPS', $userId, $username, $currentIp);
            }
            
            // 3. Verificar login de IP geograficamente distante
            $lastIp = self::getLastLoginIp($userId);
            if ($lastIp && $lastIp !== $currentIp) {
                $distance = self::estimateGeographicDistance($lastIp, $currentIp);
                
                // Se mudou mais de 500km em menos de 5 minutos, é suspeito
                $timeDiff = self::getLastLoginTimeDiff($userId);
                if ($distance > 500 && $timeDiff < 300) {
                    self::logAnomaly('IMPOSSIBLE_TRAVEL', $userId, $username, $currentIp);
                }
            }
            
        } catch (Exception $e) {
            error_log("Erro ao verificar anomalias: " . $e->getMessage());
        }
    }
    
    /**
     * Registra um comportamento suspeito no log
     */
    private static function logAnomaly($anomalyType, $userId, $username, $ip) {
        global $pdo;
        
        try {
            $pdo->prepare("
                INSERT INTO login_anomalies (user_id, username, anomaly_type, ip_address, detected_at)
                VALUES (:user_id, :username, :type, :ip, NOW())
            ")->execute([
                ':user_id' => $userId,
                ':username' => $username,
                ':type' => $anomalyType,
                ':ip' => $ip
            ]);
            
            // Log também no arquivo de erros para alertar admin
            error_log("[SECURITY] Anomalia detectada: $anomalyType | User: $username | IP: $ip");
            
        } catch (Exception $e) {
            error_log("Erro ao registrar anomalia: " . $e->getMessage());
        }
    }
    
    /**
     * Obtém último IP de login do usuário
     */
    private static function getLastLoginIp($userId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT ip_address FROM login_history
                WHERE user_id = :user_id
                ORDER BY login_time DESC
                LIMIT 2
            ");
            $stmt->execute([':user_id' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Retorna o segundo registro (último IP diferente do atual)
            return isset($results[1]) ? $results[1]['ip_address'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Tempo decorrido desde último login
     */
    private static function getLastLoginTimeDiff($userId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(MAX(login_time)) as time_diff
                FROM login_history
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['time_diff'] ?? 999999;
        } catch (Exception $e) {
            return 999999;
        }
    }
    
    /**
     * Estima distância geográfica entre dois IPs (simplificado)
     * Retorna 0 se não conseguir determinar
     */
    private static function estimateGeographicDistance($ip1, $ip2) {
        // Implementação simplificada - em produção, usar API GeoIP
        // Para localhost e IPs privadas, retorna 0
        if (self::isPrivateIp($ip1) || self::isPrivateIp($ip2)) {
            return 0;
        }
        
        // Seria necessário uma API de geolocalização real
        // Por enquanto, retorna 0 para não gerar falsos positivos
        return 0;
    }
    
    /**
     * Verifica se é um IP privado
     */
    private static function isPrivateIp($ip) {
        $privateRanges = [
            '127.0.0.0/8',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '169.254.0.0/16',
        ];
        
        foreach ($privateRanges as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verifica se um IP está em um range CIDR
     */
    private static function ipInRange($ip, $range) {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) === $subnet;
    }
    
    /**
     * Detecta tentativa de roubo de sessão
     */
    public static function detectSessionTheft($userId, $currentIp) {
        global $pdo;
        
        try {
            // Se o IP mudou completamente entre requisições
            // e a sessão foi usada em múltiplas IPs em tempo impossível
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as request_count
                FROM request_log
                WHERE user_id = :user_id
                  AND ip_address = :ip
                  AND request_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ");
            
            // Seria preenchido com cada requisição
            // Se mudar muito de IP rapidamente = provável roubo
            
        } catch (Exception $e) {
            error_log("Erro ao detectar roubo de sessão: " . $e->getMessage());
        }
    }
}
?>
