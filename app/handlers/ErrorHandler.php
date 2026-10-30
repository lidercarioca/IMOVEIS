<?php
/**
 * ErrorHandler - Tratador de erros global
 * Gerencia logging e tratamento centralizado de erros
 */

require_once __DIR__ . '/../controllers/BaseController.php';

class ErrorHandler {
    public static function handleError($errno, $errstr, $errfile, $errline) {
        $error = [
            'type' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Log do erro
        $logDir = __DIR__ . '/../../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        error_log(json_encode($error) . "\n", 3, $logDir . '/error.log');
        
        // Não exibir nada em requisições de API
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            // Log de API error somente
            error_log("[API ERROR] $errstr in $errfile:$errline\n", 3, $logDir . '/api_errors.log');
            return false; // Retornar false para que o erro padrão de PHP seja mostrado
        }
        
        // Apenas para requisições não-API mostrar HTML
        if (ini_get('display_errors')) {
            printf("<div class='error-message'>Erro: %s</div>", $errstr);
        } else {
            echo "<div class='error-message'>Ocorreu um erro. Por favor, tente novamente mais tarde.</div>";
        }
        
        return true; // Previne que o handler de erro padrão do PHP seja chamado
    }

    public static function register() {
        set_error_handler([self::class, 'handleError']);
    }
}

// Registra o handler
ErrorHandler::register();
