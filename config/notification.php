<?php
// Carrega as variáveis de ambiente se ainda não foram carregadas
if (!function_exists('loadEnv')) {
    require_once __DIR__ . '/env_loader.php';
}

// Email do administrador
$ADMIN_EMAIL = getenv('ADMIN_EMAIL') ?: 'admin@exemplo.com';

// Configurações SMTP
$SMTP_CONFIG = [
    'host' => getenv('SMTP_HOST'),
    'port' => getenv('SMTP_PORT') ? (int) getenv('SMTP_PORT') : 587,
    'username' => getenv('SMTP_USER'),
    'password' => getenv('SMTP_PASS'),
    'secure' => getenv('SMTP_SECURE') ?: 'tls'
];

// 🔥 VALIDAÇÃO OBRIGATÓRIA (EVITA ERRO SILENCIOSO)
if (
    empty($SMTP_CONFIG['host']) ||
    empty($SMTP_CONFIG['username']) ||
    empty($SMTP_CONFIG['password'])
) {
    throw new Exception("❌ Configuração SMTP incompleta. Verifique o arquivo .env");
}

// Configurações de notificação
$NOTIFICATION_CONFIG = [
    'notify_on_error' => true,
    'notify_on_success' => false,
    'include_logs' => true,
    'max_log_lines' => 20,
];

// Nome do remetente
$FROM_NAME = 'CRM Imobiliário';