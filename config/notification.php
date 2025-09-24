<?php
// Carrega as variáveis de ambiente se ainda não foram carregadas
if (!function_exists('loadEnv')) {
    require_once __DIR__ . '/env_loader.php';
}

// Email do administrador que receberá as notificações
$ADMIN_EMAIL = getenv('ADMIN_EMAIL') ?: 'admin@exemplo.com';

// Configurações SMTP
$SMTP_CONFIG = [
    'host' => getenv('SMTP_HOST') ?: 'smtp.exemplo.com',
    'port' => getenv('SMTP_PORT') ?: 587,
    'username' => getenv('SMTP_USER') ?: 'seu_usuario',
    'password' => getenv('SMTP_PASS') ?: 'sua_senha',
    'secure' => getenv('SMTP_SECURE') ?: 'tls'
];

// Configurações de notificação
$NOTIFICATION_CONFIG = [
    'notify_on_error' => true,      // Notificar em caso de erro
    'notify_on_success' => false,   // Notificar em caso de sucesso
    'include_logs' => true,         // Incluir logs recentes na notificação
    'max_log_lines' => 20,          // Número máximo de linhas de log a incluir
];

// Nome do remetente para as notificações
$FROM_NAME = 'Sistema de Backup';
