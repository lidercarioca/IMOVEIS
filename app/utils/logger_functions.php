<?php
require_once __DIR__ . '/UserActionLogger.php';

/**
 * Helper para logar ações do usuário. Tenta obter user_id e username da sessão.
 * @param string $action
 * @param array|null $details
 */
function log_user_action($action, $details = null) {
    // Tenta recuperar usuário da sessão se possível
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : (isset($_SESSION['email']) ? $_SESSION['email'] : null);

    // Se detalhes contêm username ou user_id, prioriza
    if (is_array($details)) {
        if (isset($details['user_id']) && !$userId) $userId = $details['user_id'];
        if (isset($details['username']) && !$username) $username = $details['username'];
    }

    UserActionLogger::log($userId, $username, $action, $details);
}

?>
