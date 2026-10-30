<?php
/**
 * Mapeamento de rotas do sistema (LEGADO)
 * Mantido para compatibilidade. Use app/routes/web-routes.php para novas rotas.
 */
return [
    'dashboard' => 'views/admin/dashboard.php',
    'imoveis' => 'views/admin/imoveis.php',
    'adicionar-imovel' => 'views/admin/adicionar-imovel.php',
    'leads' => 'views/admin/leads.php',
    'configuracoes' => 'views/admin/configuracoes.php',
    'usuarios' => 'views/admin/usuarios.php',
    'backup' => 'views/admin/backup.php'
];

/**
 * NOVA ESTRUTURA - Web Routes
 * Mapeamento centralizado de páginas e abas do painel
 */
return [
    // Páginas públicas
    'pages' => [
        'home' => 'views/public/index.html',
    ],
    
    // Páginas de autenticação
    'auth' => [
        'login' => 'login.php',
        'logout' => 'logout.php',
        'forgot-password' => 'forgot-password.php',
        'reset-password' => 'reset-password.php',
    ],
    
    // Painel administrativo - Abas
    'dashboard' => [
        'properties' => 'views/pages/dashboard/properties-tab.php',
        'add-property' => 'views/pages/dashboard/add-property-tab.php',
        'leads' => 'views/pages/dashboard/leads-tab.php',
        'agendamentos' => 'views/pages/dashboard/agendamentos-tab.php',
        'settings' => 'views/pages/dashboard/settings-tab.php',
        'users' => 'views/pages/dashboard/users-tab.php',
        'relatorios' => 'views/pages/dashboard/relatorios-tab.php',
        'financeiro' => 'views/pages/dashboard/financeiro-tab.php',
    ],
];