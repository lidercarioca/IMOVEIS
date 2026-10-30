<?php
/**
 * Verificar estado da sessão
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';

$logged_in = !empty($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$user_email = $_SESSION['user_email'] ?? null;

echo json_encode([
    'logged_in' => $logged_in,
    'user_id' => $user_id,
    'user_name' => $user_name,
    'user_email' => $user_email,
    'user_role' => $user_role,
    'session_id' => session_id(),
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
