<?php

/**
 * API para bloquear endereços IP suspeitos
 * POST /api/v1/debug/blockIP.php
 * 
 * Requisitos de Segurança:
 * - Apenas administradores
 * - CSRF token válido
 * - JSON body com ip_address e reason
 * - Validação de formato IP (IPv4 e IPv6)
 */

require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/utils/logger_functions.php';
require_once __DIR__ . '/../../../app/security/Security.php';
require_once __DIR__ . '/../../../app/security/TenantContext.php';

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
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
} else {
    checkAuth();
    // compatibilidade legada: preferir AuthContext se materializado, senão usar sessão
    $isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'superadmin'], true);
    if (class_exists('App\\Auth\\AuthContext')) {
        $isAdmin = \App\Auth\AuthContext::getInstance()->isAdmin() || \App\Auth\AuthContext::getInstance()->isSuperAdmin();
    }
}

header('Content-Type: application/json; charset=utf-8');

// Apenas admins podem bloquear IPs
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

    if (!isset($input['ip_address'])) {
        throw new Exception('Endereço IP não fornecido');
    }

    $ipAddress = trim($input['ip_address']);
    $reason = isset($input['reason']) ? trim($input['reason']) : 'Sem razão especificada';

    // Validação de formato IP
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        throw new Exception('Formato de IP inválido');
    }

    // Valida comprimento da razão
    if (strlen($reason) < 3 || strlen($reason) > 255) {
        throw new Exception('Razão deve ter entre 3 e 255 caracteres');
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

    // Determina usuário que bloqueou (Prioridade: AuthContext quando Bearer; senão sessão)
    $blockedBy = null;
    if (class_exists('App\\Auth\\AuthContext')) {
        $blockedBy = \App\Auth\AuthContext::getInstance()->getUserId();
    }
    if ($blockedBy === null && isset($_SESSION['user_id'])) {
        $blockedBy = $_SESSION['user_id'];
    }

    $service = new \App\Service\SecurityMonitoringService($pdo);
    $result = $service->blockIp((int)$tenantId, $ipAddress, $reason, $blockedBy !== null ? (int)$blockedBy : null);
    $blockedId = $result['blocked_id'] ?? null;

    // Log de auditoria
    log_user_action('ip_blocked', [
        'ip_address' => $ipAddress,
        'reason' => $reason,
        'blocked_id' => $blockedId
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'IP bloqueado com sucesso',
        'blocked_id' => $blockedId
    ]);
} catch (Exception $e) {
    error_log('Erro ao bloquear IP: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
