<?php
require_once dirname(dirname(__DIR__)) . '/app/security/Security.php';
require_once dirname(dirname(__DIR__)) . '/config/session_manager.php';
require_once dirname(dirname(__DIR__)) . '/config/session_timeout.php';
require_once dirname(dirname(__DIR__)) . '/src/Auth/AuthContext.php';
require_once dirname(dirname(__DIR__)) . '/src/Auth/Roles.php';

// Exceção customizada para erros de autenticação
class ApiAuthException extends Exception {}

function checkApiAuth() {
    // Sessão já inicializada pelo session_manager.php

    // Verifica se é uma requisição AJAX
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
              
    // Verifica autenticação
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new ApiAuthException('Não autorizado - faça login primeiro');
    }
    
    // Verifica timeout da sessão (configurado em session_timeout.php)
    $sessionTimeout = SESSION_TIMEOUT;
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
        session_unset();
        session_destroy();
        http_response_code(401);
        throw new ApiAuthException('Sessão expirada - faça login novamente');
    }
    
    // Atualiza último acesso APENAS se não foi atualizado no último segundo
    // Isto evita problemas com múltiplas requisições simultâneas
    if (!isset($_SESSION['last_activity']) || 
        (time() - $_SESSION['last_activity'] > 1)) {
        $_SESSION['last_activity'] = time();
    }

    // 🔑 CRÍTICO: Inicializar AuthContext com sessão para que permissões funcionem
    $sessionRole = $_SESSION['role'] ?? \App\Auth\Roles::USER;
    $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
    \App\Auth\AuthContext::getInstance()->authenticate($sessionUserId, $sessionRole);
    
    return true;
}


