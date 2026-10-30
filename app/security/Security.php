<?php
require_once dirname(__DIR__, 2) . '/config/session_manager.php';

class Security {
    // Tempo máximo de inatividade da sessão (1 hora)
    const SESSION_LIFETIME = 3600;

    // Número máximo de tentativas de login
    const MAX_LOGIN_ATTEMPTS = 5;

    // Tempo de bloqueio após exceder tentativas (15 minutos)
    const LOCKOUT_TIME = 900;

    public static function init() {
        // Sessão já inicializada pelo session_manager.php
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
        // Inicializa array de tentativas se não existir
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        
        // Limpa tentativas antigas
        foreach ($_SESSION['login_attempts'] as $user => $attempts) {
            if (time() - $attempts['timestamp'] > self::LOCKOUT_TIME) {
                unset($_SESSION['login_attempts'][$user]);
            }
        }
        
        // Verifica se usuário está bloqueado
        if (isset($_SESSION['login_attempts'][$username])) {
            $attempts = $_SESSION['login_attempts'][$username];
            if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS && 
                time() - $attempts['timestamp'] < self::LOCKOUT_TIME) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function recordLoginAttempt($username, $success) {
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

    public static function sanitizeUsername($username) {
        $username = trim($username);
        $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
        return substr($username, 0, 64);
    }

    public static function sanitizeRole($role) {
        $role = strtolower(trim($role));
        $allowed = ['admin', 'administrator', 'superadmin', 'user', 'viewer'];
        return in_array($role, $allowed, true) ? $role : 'user';
    }

    public static function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
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

    /**
     * Define headers de segurança HTTP
     */
    public static function setSecurityHeaders() {
        // Definir o charset UTF-8 para evitar problemas de encoding
        header('Content-Type: text/html; charset=UTF-8');
        
        // Headers básicos de segurança
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // Content Security Policy - uso mais seguro em produção.
        // Em desenvolvimento pode reativar unsafe-inline e unsafe-eval se necessário.
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' " .
               "https://cdn.jsdelivr.net " .
               "https://code.jquery.com " .
               "https://cdnjs.cloudflare.com " .
               "https://unpkg.com " .
               "https://cdn.plot.ly " .
               "localhost; " .
               "style-src 'self' 'unsafe-inline' " .
               "https://cdn.jsdelivr.net " .
               "https://fonts.googleapis.com " .
               "https://cdnjs.cloudflare.com " .
               "https://unpkg.com " .
               "localhost; " .
               "font-src 'self' " .
               "https://fonts.gstatic.com " .
               "https://cdnjs.cloudflare.com " .
               "https://unpkg.com " .
               "data:; " .
               "img-src 'self' data: https: blob: localhost; " .
               "connect-src 'self' " .
               "https://api.github.com " .
               "https://www.googleapis.com " .
               "https://cdn.jsdelivr.net " .
               "https://cdnjs.cloudflare.com " .
               "https://unpkg.com " .
               "localhost; " .
               "frame-src 'none'; " .
               "object-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'; " .
               "frame-ancestors 'none';";

        header("Content-Security-Policy: $csp");

        // HSTS (HTTP Strict Transport Security) - apenas em HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Bloqueia acesso público a arquivos de log
     * Deve ser chamado no início de qualquer script acessível via web
     */
    public static function blockLogsAccess() {
        // Obtém o caminho solicitado
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Bloqueia acesso direto a arquivos de log
        if (strpos($requestUri, '/logs/') !== false || 
            preg_match('/\.log$|\.txt$/', $requestUri)) {
            
            http_response_code(403);
            die('Acesso negado');
        }
    }
}
?>
