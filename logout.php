<?php
// Headers de segurança
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src \'self\' https://ka-f.fontawesome.com https://cdnjs.cloudflare.com https://*.jsdelivr.net https://cdn.jsdelivr.net;');

session_start();

// Helper de logging de ações do usuário
require_once __DIR__ . '/app/utils/logger_functions.php';

// Captura informações do usuário, se existirem
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;

// Log de logout
log_user_action('logout', [
	'user_id' => $userId,
	'username' => $username
]);

// Destroi sessão e redireciona
session_destroy();
header("Location: login.php");
exit;
?>
