<?php
require_once __DIR__ . '/config/session_manager.php';
require_once __DIR__ . '/src/Auth/AuthContext.php';

/**
 * Funções de autenticação e autorização
 * Note: isAdmin(), checkAuth(), isLoggedIn() já são definidas em session_manager.php
 */

if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'], $_SESSION['role'])) {
    \App\Auth\AuthContext::getInstance()->authenticate(
        (int)$_SESSION['user_id'],
        (string)$_SESSION['role']
    );
}

function getUserName() {
    return $_SESSION['username'] ?? '';
}

function getUserRole() {
    return $_SESSION['role'] ?? '';
}

function checkCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || 
            !Security::validateCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }
}

function getCSRFToken() {
    return Security::generateCSRFToken();
}

// Note: As funções abaixo estão definidas em session_manager.php:
// - isAdmin()     - Verifica se o usuário é admin
// - isLoggedIn()  - Verifica se o usuário está logado
// - checkAuth()   - Força autenticação (redireciona se não autenticado)
// - checkAdmin()  - Força ser admin (redireciona se não admin)
?>
