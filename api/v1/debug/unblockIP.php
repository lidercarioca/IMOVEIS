<?php

/**
 * API para desbloquear endereços IP
 * POST /api/v1/debug/unblockIP.php
 * 
 * Requisitos de Segurança:
 * - Apenas administradores
 * - CSRF token válido
 * - JSON body com ip_address
 * - Validação de formato IP
 */

require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/utils/logger_functions.php';
require_once __DIR__ . '/../../../app/security/Security.php';

// Authentication: Bearer-first via central middleware. Fallback para sessão somente se sem Bearer.
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$hasBearer = is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
$authContext = \App\Auth\AuthContext::getInstance();
if ($hasBearer) {
    $request = ['headers' => ['Authorization' => $authHeader], 'query' => $_GET, 'path' => $_SERVER['REQUEST_URI'] ?? ''];
    $middleware = new \App\Middleware\AuthMiddleware();
    $res = $middleware->handle($request, function ($r) {
        return ['ok' => true];
    });
    if (isset($res['status']) && $res['status'] !== 200 && isset($res['error'])) {
        http_response_code((int)$res['status']);
        echo json_encode(['success' => false, 'message' => $res['error']]);
        exit;
    }
    $unblockedBy = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
} else {
    checkAuth();
    $unblockedBy = $_SESSION['user_id'] ?? null;
    if (class_exists('App\\Auth\\AuthContext') && $unblockedBy === null) {
        $unblockedBy = \App\Auth\AuthContext::getInstance()->getUserId();
    }
    // compatibilidade legada: determinar admin via sessão quando não houver Bearer
    $isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'superadmin'], true);
    if (class_exists('App\\Auth\\AuthContext')) {
        $isAdmin = \App\Auth\AuthContext::getInstance()->isAdmin() || \App\Auth\AuthContext::getInstance()->isSuperAdmin();
    }
}

header('Content-Type: application/json; charset=utf-8');

// Apenas admins podem desbloquear IPs
// Quando Bearer está presente, $isAdmin foi calculado a partir do AuthContext.
// Usar $isAdmin para decidir autorização (mantendo fallback de sessão quando aplicável).
if (!($isAdmin ?? false)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Operação concluída']);
    exit;
}

// Valida método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Valida header AJAX
if ($_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
    exit;
}

// Valida CSRF token
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['csrf_token']) || !Security::validateCSRFToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
    exit;
}

try {
    // Lê JSON body novamente após validação CSRF
    $input = json_decode(file_get_contents('php://input'), true);
    // Lê JSON body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['ip_address'])) {
        throw new Exception('Endereço IP não fornecido');
    }

    $ipAddress = trim($input['ip_address']);

    // Validação de formato IP
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        throw new Exception('Formato de IP inválido');
    }

    // Determina tenant atual com proteção explícita: não há fallback para tenant_id = 1.
    try {
        \TenantContext::initialize();
        $tenantId = \TenantContext::getTenantId();
    } catch (Throwable $t) {
        $tenantId = null;
    }

    if (!isset($tenantId) || (int)$tenantId <= 0) {
        throw new Exception('Tenant não identificado');
    }

    $service = new \App\Service\SecurityMonitoringService($pdo);
    $service->unblockIp((int)$tenantId, $ipAddress);

    // Log de auditoria
    log_user_action('ip_unblocked', [
        'ip_address' => $ipAddress
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'IP desbloqueado com sucesso'
    ]);
} catch (Exception $e) {
    error_log('Erro ao desbloquear IP: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
