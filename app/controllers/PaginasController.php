<?php
/**
 * PaginasController - Roteador de Páginas
 * 
 * Responsável por:
 * - Carregar e exibir páginas
 * - Validar autorização
 * - Gerenciar abas do painel
 */

function loadPage($page = 'dashboard') {
    // Carregar mapeamento de rotas
    $routes = require __DIR__ . '/../routes/web-routes.php';
    
    // Verificar se a página existe no mapeamento
    if (!isset($routes[$page])) {
        // Página não encontrada - carregar 404
        include __DIR__ . '/../views/errors/404.php';
        return;
    }
    
    // Verificar se o arquivo existe
    $filePath = __DIR__ . '/../../' . $routes[$page];
    if (!file_exists($filePath)) {
        // Arquivo não encontrado - carregar 404
        include __DIR__ . '/../views/errors/404.php';
        return;
    }
    
    // Carregar a página
    include $filePath;
}

/**
 * Roteador centralizado para o painel administrativo
 * Gerencia a lógica de abas e componentes
 */
class PainelRouter {
    private static $tabsAbertas = [
        'properties' => ['user', 'admin'],
        'add-property' => ['user', 'admin'],
        'leads' => ['user', 'admin'],
        'agendamentos' => ['user', 'admin'],
        'settings' => ['admin'],
        'users' => ['admin'],
        'relatorios' => ['admin'],
        'financeiro' => ['admin'],
    ];
    
    /**
     * Carrega uma aba do painel
     * @param string $tab Nome da aba
     * @param array $userRole Role do usuário
     * @return bool True se carregado com sucesso
     */
    public static function loadTab($tab, $userRole = 'user') {
        // Validar se aba existe
        if (!isset(self::$tabsAbertas[$tab])) {
            return false;
        }
        
        // Validar permissão
        if (!in_array($userRole, self::$tabsAbertas[$tab])) {
            return false;
        }
        
        // Retornar permissão
        return true;
    }
    
    /**
     * Obter todas as abas permitidas para um usuário
     */
    public static function getPermittedTabs($userRole) {
        $tabs = [];
        foreach (self::$tabsAbertas as $tab => $roles) {
            if (in_array($userRole, $roles)) {
                $tabs[] = $tab;
            }
        }
        return $tabs;
    }
}
