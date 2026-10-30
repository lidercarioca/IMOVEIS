<?php
require_once __DIR__ . '/UserActionLogger.php';
require_once __DIR__ . '/../../config/session_manager.php';

/**
 * Helper para logar ações do usuário. Tenta obter user_id e username da sessão.
 * @param string $action
 * @param array|null $details
 */
function log_user_action($action, $details = null)
{
    $userId = null;
    $username = null;

    // Prioridade 1: identidade autenticada via AuthContext.
    // Isso evita que uma sessão antiga ou diferente sobrescreva o usuário do JWT.
    if (class_exists('\\App\\Auth\\AuthContext')) {
        $authContext = \App\Auth\AuthContext::getInstance();
        $authUserId = $authContext->getUserId();
        if ($authUserId !== null && $authUserId !== false && (int)$authUserId > 0) {
            $userId = (int)$authUserId;
        }
    }

    // Prioridade 2: sessão legada, compatibilidade com fluxos existentes.
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }

    // Prioridade 3: dados explícitos do chamador, apenas como fallback.
    if ($userId === null && is_array($details) && isset($details['user_id'])) {
        $userId = $details['user_id'];
    }

    // Username: manter compatibilidade e não introduzir dependência adicional.
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username'];
    } elseif (isset($_SESSION['email'])) {
        $username = $_SESSION['email'];
    } elseif (is_array($details) && isset($details['username'])) {
        $username = $details['username'];
    }

    UserActionLogger::log($userId, $username, $action, $details);
}
