<?php
class Security {
    // Tempo máximo de inatividade da sessão (1 hora)
    const SESSION_LIFETIME = 3600;
    
    // Número máximo de tentativas de login
    const MAX_LOGIN_ATTEMPTS = 5;
    
    // Tempo de bloqueio após exceder tentativas (15 minutos)
    const LOCKOUT_TIME = 900;
    
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurações de segurança da sessão antes de iniciar
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            // Ativa cookie_secure apenas quando a requisição estiver em HTTPS
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
            ini_set('session.cookie_secure', $isHttps ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);
            
            // Inicia a sessão após definir as configurações
            session_start();
        }
        
        // Regenera ID da sessão periodicamente
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            self::regenerateSession();
        }
    }
    
    public static function regenerateSession() {
        // Regenera o ID da sessão
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    public static function validateLoginAttempts($username) {
        global $pdo;
        
        if (!$pdo) {
            // Fallback para sessão se BD não disponível
            return self::validateLoginAttemptsSession($username);
        }
        
        try {
            $ip = self::getClientIP();
            $timeWindow = date('Y-m-d H:i:s', time() - self::LOCKOUT_TIME);
            
            // Conta tentativas falhadas recentes
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as attempt_count 
                FROM login_attempts 
                WHERE username = :username 
                  AND ip_address = :ip 
                  AND attempt_time > :timeWindow 
                  AND success = 0
            ");
            $stmt->execute([
                ':username' => $username,
                ':ip' => $ip,
                ':timeWindow' => $timeWindow
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $attemptCount = $result['attempt_count'] ?? 0;
            
            // Bloqueia se excedeu limite
            if ($attemptCount >= self::MAX_LOGIN_ATTEMPTS) {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao validar tentativas de login: " . $e->getMessage());
            // Fallback para sessão em caso de erro
            return self::validateLoginAttemptsSession($username);
        }
    }
    
    public static function recordLoginAttempt($username, $success) {
        global $pdo;
        
        if (!$pdo) {
            // Fallback para sessão se BD não disponível
            self::recordLoginAttemptSession($username, $success);
            return;
        }
        
        try {
            $ip = self::getClientIP();
            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (username, ip_address, success) 
                VALUES (:username, :ip, :success)
            ");
            $stmt->execute([
                ':username' => $username,
                ':ip' => $ip,
                ':success' => $success ? 1 : 0
            ]);
            
            // Se sucesso, limpa tentativas antigas para este usuário/IP
            if ($success) {
                $pdo->prepare("
                    DELETE FROM login_attempts 
                    WHERE username = :username 
                      AND ip_address = :ip 
                      AND success = 0 
                      AND attempt_time < DATE_SUB(NOW(), INTERVAL ? SECOND)
                ")->execute([$username, $ip, self::LOCKOUT_TIME]);
            }
        } catch (Exception $e) {
            error_log("Erro ao registrar tentativa de login: " . $e->getMessage());
            // Fallback para sessão
            self::recordLoginAttemptSession($username, $success);
        }
    }
    
    // Métodos de fallback para sessão
    private static function validateLoginAttemptsSession($username) {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        
        foreach ($_SESSION['login_attempts'] as $user => $attempts) {
            if (time() - $attempts['timestamp'] > self::LOCKOUT_TIME) {
                unset($_SESSION['login_attempts'][$user]);
            }
        }
        
        if (isset($_SESSION['login_attempts'][$username])) {
            $attempts = $_SESSION['login_attempts'][$username];
            if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS && 
                time() - $attempts['timestamp'] < self::LOCKOUT_TIME) {
                return false;
            }
        }
        
        return true;
    }
    
    private static function recordLoginAttemptSession($username, $success) {
        if ($success) {
            unset($_SESSION['login_attempts'][$username]);
        } else {
            if (!isset($_SESSION['login_attempts'][$username])) {
                $_SESSION['login_attempts'][$username] = ['count' => 0, 'timestamp' => time()];
            }
            $_SESSION['login_attempts'][$username]['count']++;
            $_SESSION['login_attempts'][$username]['timestamp'] = time();
        }
    }
    
    private static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }
    
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeInput($value);
            }
        } else {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }
    
    public static function validatePassword($password) {
        // Mínimo 8 caracteres, pelo menos uma letra maiúscula, uma minúscula e um número
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) && 
               preg_match('/[a-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }
    
    public static function checkSessionTimeout() {
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > self::SESSION_LIFETIME)) {
            session_unset();
            session_destroy();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
}
?>
