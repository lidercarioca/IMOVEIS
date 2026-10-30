<?php
// Arquivo de configuração SMTP
// Prefere variáveis de ambiente; carrega um `.env` simples se necessário
require_once __DIR__ . '/load_env.php';

return [
    'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'port' => intval(getenv('SMTP_PORT') ?: 587),
    'secure' => getenv('SMTP_SECURE') ?: 'tls',
    'username' => getenv('SMTP_USER') ?: getenv('SMTP_USERNAME') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: getenv('SMTP_PASS') ?: '',
    'from_email' => getenv('SMTP_FROM') ?: '',
    'from_name' => getenv('SMTP_FROM_NAME') ?: 'RR Imoveis'
];