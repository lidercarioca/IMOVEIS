<?php
require_once __DIR__ . '/app/security/Security.php';

// Inicializa configurações de segurança (já inclui session_start)
Security::init();

function checkAuth() {
    // Verifica timeout da sessão
    if (!Security::checkSessionTimeout()) {
        header("Location: login.php?error=session_expired");
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    
    // Regenera ID da sessão periodicamente
    if (!isset($_SESSION['last_regeneration']) || 
        time() - $_SESSION['last_regeneration'] > 300) {
        Security::regenerateSession();
    }
}

function checkAdmin() {
    checkAuth();
    if (!isAdmin()) {
        header("Location: unauthorized.php");
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
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

// Adiciona CSRF token em todos os formulários
function getCSRFToken() {
    return Security::generateCSRFToken();
}
?>
