<?php
/**
 * Router - Motor de Roteamento Centralizado
 * 
 * Responsável por:
 * - Rotear requisições HTTP para controllers/views
 * - Validar rotas e permissões
 * - Gerenciar fluxo de requisição
 */

class Router {
    private $routes = [];
    private $currentRoute = null;
    private $params = [];
    
    public function __construct() {
        // Carrega rotas do arquivo de configuração
        $this->routes = require __DIR__ . '/../routes/web-routes.php';
    }
    
    /**
     * Registra uma nova rota
     * @param string $path Caminho da rota
     * @param string|array $handler Controller ou função
     */
    public function register($path, $handler) {
        $this->routes[$path] = $handler;
    }
    
    /**
     * Roteia a requisição
     * @param string $path Caminho requisitado
     * @return mixed Resultado da execução
     */
    public function route($path) {
        // Remove barra final se existir
        $path = rtrim($path, '/');
        if (empty($path)) {
            $path = '/';
        }
        
        // Verifica rota exata
        if (isset($this->routes[$path])) {
            $this->currentRoute = $this->routes[$path];
            return $this->execute();
        }
        
        // Verifica rotas parametrizadas
        foreach ($this->routes as $route => $handler) {
            if ($this->matchesPattern($route, $path)) {
                $this->currentRoute = $handler;
                return $this->execute();
            }
        }
        
        // Rota não encontrada
        return $this->notFound();
    }
    
    /**
     * Verifica se a rota corresponde ao padrão
     * @param string $pattern Padrão da rota
     * @param string $path Caminho solicitado
     * @return bool
     */
    private function matchesPattern($pattern, $path) {
        // Converte padrão em regex
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $pattern = str_replace('/', '\/', $pattern);
        
        if (preg_match('/^' . $pattern . '$/', $path, $matches)) {
            // Extrai parâmetros
            foreach ($matches as $key => $value) {
                if (!is_numeric($key)) {
                    $this->params[$key] = $value;
                }
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Executa o handler da rota
     * @return mixed Resultado da execução
     */
    private function execute() {
        if (is_callable($this->currentRoute)) {
            return call_user_func_array($this->currentRoute, [$this->params]);
        }
        
        if (is_string($this->currentRoute)) {
            return $this->loadView($this->currentRoute);
        }
        
        return null;
    }
    
    /**
     * Carrega uma view
     * @param string $view Caminho da view
     * @return void
     */
    private function loadView($view) {
        $filePath = __DIR__ . '/../views/' . $view . '.php';
        
        if (file_exists($filePath)) {
            include $filePath;
        } else {
            $this->notFound();
        }
    }
    
    /**
     * Renderiza página 404
     * @return void
     */
    private function notFound() {
        header("HTTP/1.0 404 Not Found");
        include __DIR__ . '/../views/errors/404.php';
    }
    
    /**
     * Obter parâmetros da rota
     * @return array
     */
    public function getParams() {
        return $this->params;
    }
    
    /**
     * Obter rota atual
     * @return string|array
     */
    public function getCurrentRoute() {
        return $this->currentRoute;
    }
}
