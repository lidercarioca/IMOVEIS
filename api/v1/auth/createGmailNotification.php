<?php

/**
 * API para criar notificações de EMAILS reais do Gmail
 * SEPARADO de mensagens de formulário (createEmailNotification.php)
 */

// limpa output buffer ANTES de qualquer include
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

try {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $hasBearer = is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
    $authContext = \App\Auth\AuthContext::getInstance();

    if ($hasBearer) {
        $request = [
            'headers' => ['Authorization' => $authHeader],
            'query' => $_GET,
            'path' => $_SERVER['REQUEST_URI'] ?? ''
        ];

        $middleware = new \App\Middleware\AuthMiddleware();
        $res = $middleware->handle($request, function ($r) {
            return ['ok' => true];
        });

        if (isset($res['status']) && $res['status'] !== 200 && isset($res['error'])) {
            http_response_code((int)$res['status']);
            echo json_encode(['success' => false, 'error' => $res['error']], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        checkAuth();
    }

    if (!$authContext->isAuthenticated() && !empty($_SESSION['user_id'] ?? '')) {
        $authContext->authenticate((int)$_SESSION['user_id'], $_SESSION['role'] ?? 'user');
    }

    if (!$authContext->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    ob_end_clean();
    ob_start();

    // Apenas admins podem criar notificações de emails do cliente (silenciosamente retorna sucesso)
    if (!$authContext->isAdmin() && !$authContext->isSuperAdmin()) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Operação concluída',
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['gmail_id']) || !isset($data['from']) || !isset($data['subject'])) {
        http_response_code(400);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetros obrigatórios faltando: gmail_id, from, subject'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Validação de email
    if (!filter_var($data['from'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Email do remetente inválido'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $service = new \App\Service\NotificationService($pdo);

    if ($service->gmailNotificationExists($authContext->getUserId(), $data['gmail_id'])) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Notificação já existe para este email do Gmail'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $created = $service->createGmailNotification(
        $authContext->getUserId(),
        $data['gmail_id'],
        $data['from'],
        $data['subject'],
        $data['snippet'] ?? ''
    );

    if (!$created) {
        http_response_code(500);
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível criar a notificação de email'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Notificação de email Gmail criada com sucesso'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log('[createGmailNotification] Erro: ' . $e->getMessage(), 3, __DIR__ . '/../logs/api_errors.log');
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao criar notificação de email: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
