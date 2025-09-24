<?php
// Configurações de segurança do PHP
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Limitar upload de arquivos
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_file_uploads', '5');

// Configurações de sessão
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);

// Configurações de segurança adicionais
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);
ini_set('expose_php', 0);
ini_set('max_input_time', 60);
ini_set('memory_limit', '128M');

// Desabilitar funções perigosas
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