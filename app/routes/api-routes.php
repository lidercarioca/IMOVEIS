<?php
/**
 * API Routes - Dispatcher centralizado de endpoints AJAX
 * 
 * Este arquivo roteia todas as requisições AJAX para os controllers corretos
 * Exemplo: GET /api/v1/properties → incluir properties.php e executar
 */

// Importar middleware de autenticação
require_once __DIR__ . '/../middleware/api_auth.php';

/**
 * API Routes Dispatcher
 * Mapeia endpoints AJAX para controladores
 */
class ApiRouter {
    private static $routes = [
        'GET' => [
            'properties' => '/getProperties.php',
            'properties/:id' => '/getPropertyById.php',
            'leads' => '/getLeads.php',
            'notifications' => '/getNotifications.php',
            'messages' => '/getMessages.php',
            'agendamentos' => '/getAgendamentos.php',
            'financeiro' => '/getFinanceiro.php',
            'reports' => '/getReports.php',
            'banners' => '/getBanners.php',
            'settings' => '/getCompanySettings.php',
        ],
        'POST' => [
            'properties' => '/addProperty.php',
            'properties/update' => '/updateProperty.php',
            'properties/delete' => '/deleteProperty.php',
            'properties/image' => '/uploadImagem.php',
            'leads' => '/addLead.php',
            'leads/update' => '/updateLead.php',
            'leads/delete' => '/deleteLead.php',
            'agendamentos' => '/addAgendamento.php',
            'agendamentos/update' => '/updateAgendamento.php',
            'agendamentos/delete' => '/deleteAgendamento.php',
            'financeiro' => '/addFinanceiro.php',
            'financeiro/update' => '/updateFinanceiro.php',
            'financeiro/delete' => '/deleteFinanceiro.php',
            'notifications/mark-read' => '/markNotificationRead.php',
            'messages/mark-read' => '/markMessageRead.php',
        ],
        'DELETE' => [
            'properties/:id' => '/deleteProperty.php',
            'leads/:id' => '/deleteLead.php',
            'agendamentos/:id' => '/deleteAgendamento.php',
            'financeiro/:id' => '/deleteFinanceiro.php',
        ]
    ];
    
    /**
     * Rotear uma requisição AJAX
     * @param string $method HTTP method (GET, POST, DELETE, etc)
     * @param string $endpoint Endpoint da API
     * @return bool Se rota foi encontrada
     */
    public static function dispatch($method, $endpoint) {
        if (!isset(self::$routes[$method])) {
            return false;
        }
        
        if (!isset(self::$routes[$method][$endpoint])) {
            return false;
        }
        
        $controller = __DIR__ . '/../../api' . self::$routes[$method][$endpoint];
        if (file_exists($controller)) {
            include $controller;
            return true;
        }
        
        return false;
    }
    
    /**
     * Obter todas as rotas registradas
     */
    public static function getRoutes() {
        return self::$routes;
    }
}
