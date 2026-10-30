<?php
// Configurações de segurança do PHP
// Evita chamar ini_set para configurações de sessão quando a sessão
// já estiver ativa (isso causa warnings que quebram APIs JSON)

// BLOCO 1: Configurações que DEVEM ser feitas ANTES de session_start()
if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('ini_set')) {
        // Existem apenas para referência; já estão em session_manager.php
        ini_set('session.gc_maxlifetime', 3600);
    }
}

// BLOCO 2: Configurações que podem ser feitas a qualquer momento
if (function_exists('ini_set')) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(0);

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Limitar upload de arquivos
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_file_uploads', '5');

// Configurações de segurança adicionais (podem ser mudadas após session_start)
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);
ini_set('expose_php', 0);
ini_set('max_input_time', 60);
ini_set('memory_limit', '128M');

// Desabilitar funções perigosas (não é configuração de sessão)
$disabled_functions = array(
    'exec',
    'passthru',
    'shell_exec',
    'system',
    'proc_open',
    'popen',
    'curl_multi_exec',
    'parse_ini_file',
    'show_source'
);

if (function_exists('ini_set')) {
    ini_set('disable_functions', implode(',', $disabled_functions));
}

// ======================
// Limites de segurança
// ======================
return [
    // Limites de login
    'login_max_attempts' => 5,
    'login_decay_seconds' => 60, // 1 min

    // Limites de refresh token
    'refresh_max_requests' => 20,
    'refresh_decay_seconds' => 60, // 1 min

    // Limite geral de API
    'api_max_requests' => 100,
    'api_decay_seconds' => 60, // 1 min

    // Limites de endpoints críticos
    'property_update_max_requests' => 20,
    'property_update_decay_seconds' => 60,
    'property_delete_max_requests' => 10,
    'property_delete_decay_seconds' => 60,
    'property_add_max_requests' => 20,
    'property_add_decay_seconds' => 60,
];