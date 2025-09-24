<?php
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
        error_log(json_encode($error) . "\n", 3, __DIR__ . '/../../logs/error.log');
        
        if (ini_get('display_errors')) {
            printf("<div class='error-message'>Erro: %s</div>", $errstr);
        } else {
            echo "<div class='error-message'>Ocorreu um erro. Por favor, tente novamente mais tarde.</div>";
        }
    }

    public static function register() {
        set_error_handler([self::class, 'handleError']);
    }
}

// Registra o handler
ErrorHandler::register();
?>
