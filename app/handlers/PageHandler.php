<?php
function loadPage($page = 'dashboard') {
    // Carregar mapeamento de rotas
    $routes = require __DIR__ . '/../config/routes.php';
    
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