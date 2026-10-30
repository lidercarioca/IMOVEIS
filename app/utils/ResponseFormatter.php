<?php
/**
 * ResponseFormatter - Formatador de respostas padrão para API
 * 
 * Garante consistência 100% em todas as respostas da aplicação
 * Uso: ResponseFormatter::success($data, 'mensagem')
 */

namespace App\Utils;

class ResponseFormatter {
    
    // HTTP Status Codes
    const OK = 200;
    const CREATED = 201;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const CONFLICT = 409;
    const UNPROCESSABLE = 422;
    const INTERNAL_ERROR = 500;
    
    /**
     * Resposta de sucesso
     */
    public static function success($data = null, string $message = '', int $statusCode = self::OK): string {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        return json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Resposta de erro
     */
    public static function error(string $message, int $statusCode = self::BAD_REQUEST, $errors = null): string {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'message' => $message,
            'data' => null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Adicionar detalhes de erro se fornecidos
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Resposta com paginação
     */
    public static function paginated(array $data, int $page, int $limit, int $total, string $message = ''): string {
        $pages = ceil($total / $limit);
        
        return self::success([
            'items' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
                'hasNextPage' => $page < $pages,
                'hasPreviousPage' => $page > 1
            ]
        ], $message, self::OK);
    }
    
    /**
     * Resposta 404
     */
    public static function notFound(string $message = 'Recurso não encontrado'): string {
        return self::error($message, self::NOT_FOUND);
    }
    
    /**
     * Resposta 401
     */
    public static function unauthorized(string $message = 'Não autorizado'): string {
        return self::error($message, self::UNAUTHORIZED);
    }
    
    /**
     * Resposta 403
     */
    public static function forbidden(string $message = 'Acesso negado'): string {
        return self::error($message, self::FORBIDDEN);
    }
    
    /**
     * Resposta 422 - Dados não processáveis
     */
    public static function unprocessable(string $message = 'Dados inválidos', array $errors = []): string {
        return self::error($message, self::UNPROCESSABLE, $errors);
    }
    
    /**
     * Resposta 500
     */
    public static function serverError(string $message = 'Erro interno do servidor'): string {
        return self::error($message, self::INTERNAL_ERROR);
    }
    
    /**
     * Resposta 409 - Conflito (recurso já existe)
     */
    public static function conflict(string $message = 'Recurso já existe'): string {
        return self::error($message, self::CONFLICT);
    }
    
    /**
     * Resposta com dados cacheados
     * Adiciona headers de cache HTTP
     */
    public static function cached($data = null, string $message = '', int $cacheDuration = 3600): string {
        http_response_code(self::OK);
        header('Content-Type: application/json; charset=utf-8');
        header("Cache-Control: public, max-age={$cacheDuration}");
        header("ETag: " . md5(json_encode($data)));
        
        return json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'cached' => true,
            'cache_duration' => $cacheDuration
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Resposta 201 - Criado com sucesso
     * Atalho para created()
     */
    public static function created($data = null, string $message = 'Recurso criado com sucesso'): string {
        return self::success($data, $message, self::CREATED);
    }
    
    /**
     * Resposta com múltiplos erros de validação
     * Ideal para validar vários campos
     * 
     * Exemplo:
     * $errors = [
     *     'email' => 'Email inválido',
     *     'phone' => 'Telefone obrigatório',
     *     'cpf' => 'CPF inválido'
     * ];
     * echo ResponseFormatter::validationError($errors);
     */
    public static function validationError(array $errors): string {
        return self::unprocessable('Erro de validação', $errors);
    }
    
    /**
     * Wrapper para respostas de sucesso com tipo de contenido
     * Usado para retornar dados em diferentes formatos
     */
    public static function raw(string $content, string $contentType = 'application/json', int $statusCode = 200): void {
        http_response_code($statusCode);
        header("Content-Type: {$contentType}");
        echo $content;
        exit;
    }
    
    /**
     * Retorna metadados úteis para debug
     * APENAS em ambiente de desenvolvimento
     */
    public static function debug($data = null, array $metadata = []): string {
        if (!getenv('DEBUG_MODE')) {
            return self::error('Debug desabilitado em produção');
        }
        
        return json_encode([
            'success' => true,
            'data' => $data,
            'debug' => array_merge([
                'timestamp' => date('Y-m-d H:i:s'),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] . ' segundos',
                'php_version' => PHP_VERSION,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ], $metadata)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    /**
     * Resposta para redirecionamento (apenas JSON)
     * Frontend deve interpretar 'redirect_to'
     */
    public static function redirect(string $url, string $message = 'Redirecionando'): string {
        return self::success([
            'redirect_to' => $url
        ], $message, 200);
    }
}
