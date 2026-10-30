<?php
/**
 * JSON Response Helper - Centralizado
 * Garante que TODOS os endpoints retornam JSON válido
 * Com tratamento robusto de erros e charset UTF-8
 */

// Usar buffer para prevenir output acidental
if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Envia resposta JSON de sucesso
 * @param mixed $data Dados a enviar
 * @param string $message Mensagem amigável
 * @param int $httpStatus Status HTTP (padrão: 200)
 */
function sendJsonSuccess($data = null, $message = 'Operação realizada com sucesso', $httpStatus = 200) {
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => true,
        'message' => $message,
        'data' => $data
    ];
    
    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK
    );
    
    ob_end_flush();
    exit;
}

/**
 * Envia resposta JSON de erro
 * @param string $error Mensagem de erro amigável
 * @param string $details Detalhes técnicos (apenas em development)
 * @param int $httpStatus Status HTTP (padrão: 400)
 */
function sendJsonError($error = 'Erro ao processar requisição', $details = null, $httpStatus = 400) {
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => false,
        'error' => $error,
    ];
    
    // Adicionar detalhes técnicos apenas em development
    if (getenv('ENVIRONMENT') === 'development' && $details) {
        $response['details'] = $details;
    }
    
    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    
    ob_end_flush();
    exit;
}

/**
 * Envia JSON validado
 * Garante que o JSON é válido antes de enviar
 * @param array $data Dados a enviar
 * @param int $httpStatus Status HTTP
 */
function sendJsonValidated($data, $httpStatus = 200) {
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    
    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK
    );
    
    // Validar se JSON é válido
    if ($json === false) {
        error_log('JSON_ENCODE_ERROR: ' . json_last_error_msg());
        $json = json_encode([
            'success' => false,
            'error' => 'Erro ao processar dados',
            'details' => getenv('ENVIRONMENT') === 'development' ? json_last_error_msg() : null
        ]);
    }
    
    echo $json;
    ob_end_flush();
    exit;
}

/**
 * Shutdown handler para capturar erros fatais e retornar JSON
 */
function setupJsonShutdownHandler() {
    register_shutdown_function(function() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            // Limpar buffers anteriores
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            
            $response = [
                'success' => false,
                'error' => 'Erro interno do servidor',
            ];
            
            if (getenv('ENVIRONMENT') === 'development') {
                $response['details'] = sprintf(
                    '%s em %s:%d',
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
            }
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        }
    });
}

/**
 * Error handler para convertir PHP warnings/notices em JSON
 */
function setupJsonErrorHandler() {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Não tratar @ suppressed errors
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Se já estamos em uma resposta JSON, converter erro em log
        if (headers_sent()) {
            error_log(sprintf(
                'PHP Error [%d]: %s em %s:%d',
                $errno,
                $errstr,
                $errfile,
                $errline
            ));
            return false;
        }
        
        // Se ainda não enviou headers, retornar JSON de erro
        sendJsonError(
            'Erro ao processar requisição',
            sprintf('%s em %s:%d', $errstr, $errfile, $errline),
            500
        );
        return true;
    }, E_ALL);
}

// Inicializar handlers globalmente
setupJsonShutdownHandler();
setupJsonErrorHandler();
?>
