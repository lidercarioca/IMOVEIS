<?php
/**
 * BaseController - Classe base para todos os controllers
 * 
 * Fornece:
 * - Validação de autenticação com type hints
 * - Validação de autorização
 * - Tratamento de erros padronizado
 * - Resposta JSON consistente
 * - Logging de operações
 */
class BaseController {
    protected bool $isAuthenticated = false;
    protected bool $isAdmin = false;
    protected ?int $userId = null;
    protected string $userRole = 'user';
    
    // HTTP Status Codes constantes
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_CONFLICT = 409;
    const HTTP_INTERNAL_ERROR = 500;
    
    public function __construct() {
        $this->checkAuth();
    }
    
    /**
     * Verificar autenticação do usuário
     */
    protected function checkAuth(): void {
        if (!isset($_SESSION['user_id'])) {
            $this->isAuthenticated = false;
            return;
        }
        
        $this->isAuthenticated = true;
        $this->userId = (int)$_SESSION['user_id'];
        $this->userRole = $_SESSION['role'] ?? 'user';
        $userRoleLower = strtolower(trim($this->userRole ?? ''));
        $this->isAdmin = ($userRoleLower === 'admin' || $userRoleLower === 'administrator' || $userRoleLower === 'superadmin');
    }
    
    /**
     * Requer autenticação
     */
    protected function requireAuth(): bool {
        if (!$this->isAuthenticated) {
            $this->sendError('Não autorizado', self::HTTP_UNAUTHORIZED);
            return false;
        }
        return true;
    }
    
    /**
     * Requer permissão de administrador
     */
    protected function requireAdmin(): bool {
        if (!$this->isAdmin) {
            $this->sendError('Acesso negado', self::HTTP_FORBIDDEN);
            return false;
        }
        return true;
    }
    
    /**
     * Enviar resposta de sucesso
     */
    protected function respond($data = '', $success = true, $message = ''): string {
        // Se chamado com apenas um argumento, $data é o resultado
        // Se chamado com 3 argumentos, $data é o resultado, $success é TRUE/FALSE, $message é a mensagem
        if (is_bool($success)) {
            $useSuccess = $success;
        } else {
            // Compatibilidade: se o segundo argumento não é booleano, é a mensagem
            $message = (string)$success;
            $useSuccess = true;
        }
        
        header('Content-Type: application/json; charset=utf-8');
        return json_encode([
            'success' => $useSuccess,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Enviar resposta de erro
     */
    protected function sendError(string $message, int $statusCode = self::HTTP_BAD_REQUEST): string {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode([
            'success' => false,
            'message' => $message,
            'error' => $message,
            'data' => null,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Alias para sendError - retorna resposta de erro
     */
    protected function error(string $message, int $statusCode = self::HTTP_BAD_REQUEST): string {
        return $this->sendError($message, $statusCode);
    }
    
    /**
     * Validação genérica de entrada
     */
    protected function validateInput(array $data, array $requiredFields): array {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "Campo '{$field}' é obrigatório";
            }
        }
        
        return $errors;
    }
    
    /**
     * Log de operações (para auditoria)
     */
    protected function logOperation(string $action, ?int $resourceId = null, ?string $details = null): void {
        if (getenv('ENVIRONMENT') === 'development') {
            $logMessage = sprintf(
                "[%s] User:%d | Action:%s | Resource:%s | Details:%s",
                date('Y-m-d H:i:s'),
                $this->userId ?? 0,
                $action,
                $resourceId ?? 'null',
                $details ?? 'none'
            );
            error_log($logMessage);
        }
    }
    
    /**
     * Obter o usuário atual
     */
    protected function getCurrentUser(): ?array {
        if (!$this->isAuthenticated) {
            return null;
        }
        
        return [
            'id' => $this->userId,
            'role' => $this->userRole,
            'is_admin' => $this->isAdmin
        ];
    }
    
    /**
     * Requer permissão específica
     * 
     * Exemplo: $this->requirePermission('edit_users')
     */
    protected function requirePermission(string $permission): bool {
        // TODO: Implementar verificação de permissão no banco de dados
        return true;
    }
    
    /**
     * Obter parâmetro GET com validação
     */
    protected function getParam(string $name, $default = null, string $type = 'string') {
        $value = $_GET[$name] ?? $default;
        
        if ($value === null) return null;
        
        switch($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                return (bool)$value;
            case 'string':
                return (string)$value;
            case 'array':
                return (array)$value;
            default:
                return $value;
        }
    }
    
    /**
     * Obter parâmetro POST com validação
     */
    protected function postParam(string $name, $default = null, string $type = 'string') {
        $value = $_POST[$name] ?? $default;
        
        if ($value === null) return null;
        
        switch($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                return (bool)$value;
            case 'string':
                return (string)$value;
            case 'array':
                return (array)$value;
            default:
                return $value;
        }
    }
    
    /**
     * Obter JSON do request body
     */
    protected function getJsonInput(): array {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return $data ?? [];
    }
    
    /**
     * Enviar arquivo para download
     */
    protected function downloadFile(string $filePath, ?string $fileName = null): void {
        if (!file_exists($filePath)) {
            http_response_code(404);
            exit;
        }
        
        $fileName = $fileName ?? basename($filePath);
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"{$fileName}\"");
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
    
    /**
     * Redirecionar com status code
     */
    protected function redirect(string $url, int $code = 301): void {
        http_response_code($code);
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Verificar método HTTP
     */
    protected function isMethod(string $method): bool {
        return $_SERVER['REQUEST_METHOD'] === strtoupper($method);
    }
    
    /**
     * Requer método HTTP específico
     */
    protected function requireMethod(string $method): bool {
        if (!$this->isMethod($method)) {
            http_response_code(405);
            $this->sendError("Método {$method} requerido");
            return false;
        }
        return true;
    }
    
    /**
     * Adicionar header customizado
     */
    protected function addHeader(string $name, string $value): void {
        header("{$name}: {$value}");
    }
    
    /**
     * Verificar header customizado
     */
    protected function getHeader(string $name): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }
    
    /**
     * Obter informação do usuário do banco de dados
     * (Útil quando precisa de dados mais completos)
     */
    protected function loadUserData(): ?array {
        // TODO: Implementar carregamento completo de usuário do banco
        return null;
    }
    
    /**
     * Validar padrão de entrada (regex)
     */
    protected function validatePattern(string $value, string $pattern, string $fieldName): array {
        if (!preg_match($pattern, $value)) {
            return [
                $fieldName => "Formato inválido para '{$fieldName}'"
            ];
        }
        return [];
    }
    
    /**
     * Validação com múltiplos campos e regras
     * 
     * Exemplo:
     * $rules = [
     *     'email' => 'required|email',
     *     'name' => 'required|min:3|max:100',
     *     'age' => 'required|integer|min:18|max:120'
     * ];
     */
    protected function validate(array $data, array $rules): array {
        $errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            
            foreach ($ruleList as $rule) {
                $error = $this->applyRule($field, $data[$field] ?? null, $rule);
                if ($error) {
                    $errors[$field] = $error;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Aplicar regra individual de validação
     */
    private function applyRule(string $field, $value, string $rule): ?string {
        list($ruleName, $param) = explode(':', $rule . ':');
        
        $ruleName = trim($ruleName);
        
        switch($ruleName) {
            case 'required':
                return empty($value) ? "Campo '{$field}' é obrigatório" : null;
            case 'email':
                return (!filter_var($value, FILTER_VALIDATE_EMAIL)) ? "Email inválido" : null;
            case 'min':
                return (strlen((string)$value) < (int)$param) ? "Mínimo {$param} caracteres" : null;
            case 'max':
                return (strlen((string)$value) > (int)$param) ? "Máximo {$param} caracteres" : null;
            case 'integer':
                return (!is_numeric($value) || strpos($value, '.') !== false) ? "Deve ser inteiro" : null;
            case 'numeric':
                return (!is_numeric($value)) ? "Deve ser numérico" : null;
            case 'url':
                return (!filter_var($value, FILTER_VALIDATE_URL)) ? "URL inválida" : null;
            default:
                return null;
        }
    }
}
