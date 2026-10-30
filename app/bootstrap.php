<?php

/**
 * Bootstrap - Ponto de entrada centralizado da aplicação
 *
 * Responsabilidades:
 * - Inicializar sessão e configurações
 * - Carregar autoloaders
 * - Inicializar middleware
 * - Rotear requisição
 */

// Carregar autoloader do Composer
if (file_exists(dirname(dirname(__FILE__)) . '/vendor/autoload.php')) {
    require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';
}

// Inicializar sessão com configurações de segurança
require_once dirname(__DIR__) . '/config/session_manager.php';

// Definir configurações globais
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);

if (getenv('ENVIRONMENT') === 'development') {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}

// Carregar helpers e utilitários
require_once __DIR__ . '/helpers/property_helpers.php';
require_once __DIR__ . '/controllers/PaginasController.php';
require_once __DIR__ . '/handlers/ErrorHandler.php';
require_once __DIR__ . '/middleware/api_auth.php';

// Carregar middlewares centralizados de /api/v1
$apiV1MiddlewareDir = dirname(__DIR__) . '/api/v1/middleware';
if (is_dir($apiV1MiddlewareDir)) {
    if (file_exists($apiV1MiddlewareDir . '/AuthMiddleware.php')) {
        require_once $apiV1MiddlewareDir . '/AuthMiddleware.php';
    }
    if (file_exists($apiV1MiddlewareDir . '/TenantMiddleware.php')) {
        require_once $apiV1MiddlewareDir . '/TenantMiddleware.php';
    }
}

// Adapter de compatibilidade para resolução canônica de tenant
if (file_exists(__DIR__ . '/security/TenantContext.php')) {
    require_once __DIR__ . '/security/TenantContext.php';
}

if (!function_exists('api_v1_require_auth')) {
    function api_v1_require_auth(): void
    {
        \Api\V1\Middleware\AuthMiddleware::handle();
    }
}

if (!function_exists('api_v1_resolve_tenant')) {
    function api_v1_resolve_tenant(): int
    {
        // Delegar ao adapter compatível que usa TenantResolver
        \TenantContext::initialize();
        return (int) \TenantContext::getTenantId();
    }
}

// Carregar configuração de banco de dados
require_once dirname(__DIR__) . '/config/database.php';

// Armazenar PDO em global para uso nas APIs
$GLOBALS['pdo'] = $pdo;

// Função auxiliar para debug (apenas em desenvolvimento)
if (!function_exists('debug')) {
    function debug($data)
    {
        if (getenv('ENVIRONMENT') === 'development') {
            echo '<pre>';
            var_dump($data);
            echo '</pre>';
        }
    }
}

// Carregar a página requisitada (compatibilidade com código antigo)
// Não carregar se for uma requisição de API
if (isset($_GET['page']) && strpos($_SERVER['REQUEST_URI'], '/api/') === false) {
    // Validação para prevenir path traversal / caracteres inválidos
    $currentPage = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['page']);
    if (empty($currentPage)) {
        $currentPage = 'dashboard';
    }

    // Se não for rota válida, direciona para dashboard ou 404 interno
    $validPages = ['dashboard', 'properties', 'add-property', 'leads', 'agendamentos', 'settings', 'users', 'relatorios', 'financeiro'];
    if (!in_array($currentPage, $validPages, true)) {
        $currentPage = 'dashboard';
    }

    loadPage($currentPage);
}
