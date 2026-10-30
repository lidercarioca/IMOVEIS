<?php

/**
 * API para obter EMAILS não lidos do Gmail
 * Profissional, sem buffer output
 */

use Api\V1\Middleware\AuthMiddleware;

@error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(dirname(dirname(__DIR__))) . '/app/bootstrap.php';
    require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
    require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

    AuthMiddleware::handle();

    // Por enquanto, retorna array vazio (Gmail API não está ativa)
    // Integração com Gmail pode ser implementada via GoogleCalendarService::getUnreadEmails()

    echo json_encode([
        'success' => true,
        'emails' => [],
        'unread_count' => 0,
        'type' => 'gmail_emails'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar emails do Gmail'
    ], JSON_UNESCAPED_UNICODE);
    error_log('getUnreadGmailEmails.php Error: ' . $e->getMessage());
}
