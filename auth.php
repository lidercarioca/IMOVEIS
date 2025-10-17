<?php
require_once __DIR__ . '/app/security/Security.php';

// Inicializa configurações de segurança (já inclui session_start)
Security::init();

/**
 * Verifica se o usuário está autenticado
 * Redireciona para a página de login se não estiver
 */
function checkAuth() {
    $isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
    
    // Verifica timeout da sessão
    if (!Security::checkSessionTimeout()) {
        if ($isApiRequest) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Sessão expirada']);
            exit;
        }
        header("Location: login.php?error=session_expired");
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        if ($isApiRequest) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Usuário não autenticado']);
            exit;
        }
        header("Location: login.php");
        exit;
    }
    
    // Regenera ID da sessão periodicamente
    if (!isset($_SESSION['last_regeneration']) || 
        time() - $_SESSION['last_regeneration'] > 300) {
        Security::regenerateSession();
    }
}

/**
 * Verifica se o usuário tem perfil de administrador
 * Redireciona para página não autorizada se não tiver
 */
function checkAdmin() {
    checkAuth();
    if (!isAdmin()) {
        header("Location: unauthorized.php");
        exit;
    }
}

/**
 * Verifica se o usuário logado tem papel de administrador
 * @return bool true se for admin, false caso contrário
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Retorna o nome do usuário logado
 * @return string Nome do usuário
 */
function getUserName() {
    return $_SESSION['username'] ?? '';
}

/**
 * Retorna o papel/função do usuário logado
 * @return string Papel do usuário (admin, user, etc)
 */
function getUserRole() {
    return $_SESSION['role'] ?? '';
}

/**
 * Verifica o token CSRF para prevenção de ataques
 * @return bool true se o token for válido, false caso contrário
 */
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
/**
 * Gera ou retorna o token CSRF atual
 * @return string Token CSRF
 */
function getCSRFToken() {
    return Security::generateCSRFToken();
}
?>
