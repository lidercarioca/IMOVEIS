<?php

/**
 * API para obter vendas pendentes que precisam de transação financeira
 */

// limpa output buffer ANTES de qualquer include
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
    require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
    require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
    require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/JwtService.php';
    require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthMiddleware.php';
    require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

    $userId = null;
    $tenantId = null;
    $isAdmin = false;

    $headers = getallheaders();
    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? '';

    if (!empty($authHeader) && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $jwtService = new \App\Auth\JwtService();
        $authMiddleware = new \App\Auth\AuthMiddleware($jwtService, null);

        $request = [
            'path' => $_SERVER['REQUEST_URI'] ?? '',
            'headers' => $headers,
            'query' => $_GET,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ];

        $authResult = $authMiddleware->handle($request, function ($request) {
            return ['status' => 200, 'continue' => true];
        });

        if (($authResult['status'] ?? 500) !== 200) {
            http_response_code($authResult['status'] ?? 401);
            echo json_encode([
                'success' => false,
                'message' => $authResult['error'] ?? 'Token inválido',
                'error_code' => 'UNAUTHENTICATED'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $authContext = \App\Auth\AuthContext::getInstance();
        $userId = $authContext->getUserId();
        $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
        initializeTenantMiddleware($pdo);
        $tenantId = getCurrentTenantId();
    } else {
        require_once dirname(dirname(dirname(__DIR__))) . '/api/check_auth.php';
        try {
            checkApiAuth();
        } catch (\Throwable $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Usuário não autenticado',
                'error_code' => 'UNAUTHENTICATED'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $authContext = \App\Auth\AuthContext::getInstance();
        $userId = $authContext->getUserId();
        $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
        initializeTenantMiddleware($pdo);
        $tenantId = getCurrentTenantId();
    }

    // Verifica permissão de admin
    if (!$isAdmin) {
        http_response_code(403);
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $reportService = new \App\Service\ReportService($pdo);
    $result = $reportService->getPendingSales([
        'tenantId' => $tenantId,
    ]);

    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
} catch (\Throwable $e) {
    ob_end_clean();
    if ($e instanceof SessionAuthException || strpos($e->getMessage(), 'Usuário não autenticado') !== false) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário não autenticado',
            'error_code' => 'UNAUTHENTICATED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
