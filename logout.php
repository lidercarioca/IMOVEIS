<?php
// Headers de segurança
require_once __DIR__ . '/app/security/Security.php';
Security::setSecurityHeaders();

require_once __DIR__ . '/config/session_manager.php';

require_once __DIR__ . '/app/utils/logger_functions.php';

// Evitar cache da página de logout/confirm
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Fluxo compatível:
// - GET sem params: exibe confirmação
// - GET ?confirm=1: força logout imediato (mantém links existentes funcionando)
// - POST: logout seguro via CSRF

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['confirm'])) {
    $token = Security::generateCSRFToken();
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Confirmar Logout</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<style>body{background:#f6f8ff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;} .confirm-card{width:min(420px,95%);padding:22px;border-radius:14px;background:#ffffff;box-shadow:0 12px 30px rgba(24,39,75,.12);} .confirm-card h2{font-size:1.3rem;margin-bottom:0.5rem;} .confirm-card p{color:#55606f;margin-bottom:1.3rem;} .btn-cancel{color:#6c757d;text-decoration:none;} .btn-cancel:hover{text-decoration:underline;}</style>';
    echo '</head><body><div class="confirm-card text-center"><h2>Você deseja sair?</h2><p>Para evitar logout acidental, confirme abaixo sua saída segura.</p><form method="POST" action="logout.php"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><button type="submit" class="btn btn-danger w-100 mb-2">Sair</button></form><p><a href="?confirm=1" class="btn-cancel d-inline-block mb-2">Sair sem confirmação (compatível)</a></p><p><a href="painel.php" class="btn-cancel">Cancelar</a></p></div></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['confirm']) && $_GET['confirm'] === '1') {
    // compatibilidade: logout imediato para links existentes
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        session_unset();
        session_destroy();
        header('Location: login.php?error=csrf_invalid');
        exit;
    }
} else {
    http_response_code(405);
    echo 'Método não permitido';
    exit;
}

$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;

log_user_action('logout', [
	'user_id' => $userId,
	'username' => $username
]);

// Limpa sessão com segurança
session_unset();
session_destroy();
$cookieParams = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);

header("Location: login.php");
exit;
?>
