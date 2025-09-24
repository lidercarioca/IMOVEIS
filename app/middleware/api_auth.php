<?php
require_once '../app/security/Security.php';

function checkApiAuth() {
    // Inicia a sessão se ainda não foi iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verifica se é uma requisição AJAX
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
              
    // Verifica autenticação
    if (!isset($_SESSION['user_id'])) {
        if ($isAjax) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Não autorizado'
            ]);
        } else {
            header('Location: /login.php');
        }
        exit;
    }
    
    // Verifica timeout da sessão
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
        session_unset();
        session_destroy();
        if ($isAjax) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Sessão expirada'
            ]);
        } else {
            header('Location: /login.php?error=session_expired');
        }
        exit;
    }
    
    // Atualiza último acesso
    $_SESSION['last_activity'] = time();
    
    return true;
}
