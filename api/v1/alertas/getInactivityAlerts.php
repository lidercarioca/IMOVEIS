<?php

/**
 * GET /api/v1/alertas/getInactivityAlerts.php
 * 
 * Obtém alertas de leads inativos (sem contato > 7 dias)
 * Query params:
 * - status: novo|acknowledged|resolved (opcional)
 * - severity: low|medium|high|critical (opcional)
 * - limit: número de alertas (padrão: 20)
 */

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Garantir que nenhuma saída prévia quebre o JSON
if (ob_get_level() === 0) {
    ob_start();
}

try {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Auth/JwtService.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Auth/AuthMiddleware.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/auth.php';
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

    $headers = getallheaders();
    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? '';

    $userId = null;
    $tenantId = 0;
    $isAdmin = false;

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
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => $authResult['error'] ?? 'Token inválido',
                'error_code' => 'INACTIVITY_ALERTS_AUTH_INVALID'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = \App\Auth\AuthContext::getInstance()->getUserId();
        $isAdmin = \App\Auth\AuthContext::getInstance()->hasRole(\App\Auth\Roles::ADMIN);
        initializeTenantMiddleware($pdo);
        $tenantId = (int) getRequestTenantId();
    } else {
        if (!function_exists('checkAuth')) {
            throw new \RuntimeException('Sistema de autenticação indisponível');
        }

        try {
            checkAuth();
        } catch (\Throwable $e) {
            http_response_code(401);
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Usuário não autenticado',
                'error_code' => 'UNAUTHENTICATED'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'superadmin'], true);
        initializeTenantMiddleware($pdo);
        $tenantId = (int) getRequestTenantId();
    }

    if ($tenantId <= 0) {
        http_response_code(403);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Tenant não identificado',
            'error_code' => 'INACTIVITY_ALERTS_TENANT_NOT_RESOLVED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    global $pdo;

    $status = $_GET['status'] ?? null;
    $severity = $_GET['severity'] ?? null;
    $limit = (int)($_GET['limit'] ?? 20);
    $limit = max(1, min($limit, 100));

    $alertService = new \App\Services\LeadInactivityAlertService($pdo, $tenantId, $userId);
    $alerts = $alertService->getActiveAlerts($isAdmin ? null : $userId, $limit);

    foreach ($alerts as &$alert) {
        if (isset($alert['severity'])) {
            $alert['severity'] = strtolower($alert['severity']);
        }
    }
    unset($alert);

    if ($status) {
        $alerts = array_filter($alerts, fn($a) => isset($a['status']) && $a['status'] === $status);
    }

    if ($severity) {
        $alerts = array_filter($alerts, fn($a) => isset($a['severity']) && $a['severity'] === strtolower($severity));
    }

    $stats = [
        'total' => count($alerts),
        'critical' => count(array_filter($alerts, fn($a) => ($a['severity'] ?? '') === 'critical')),
        'high' => count(array_filter($alerts, fn($a) => ($a['severity'] ?? '') === 'high')),
        'medium' => count(array_filter($alerts, fn($a) => ($a['severity'] ?? '') === 'medium')),
        'low' => count(array_filter($alerts, fn($a) => ($a['severity'] ?? '') === 'low')),
        'new' => count(array_filter($alerts, fn($a) => ($a['status'] ?? '') === 'novo')),
    ];

    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => array_values($alerts),
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[INACTIVITY_ALERTS] ERRO: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    if (ob_get_length() > 0) {
        ob_clean();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar alertas de inatividade',
        'error_code' => 'INACTIVITY_ALERTS_ERROR'
    ], JSON_UNESCAPED_UNICODE);
}
