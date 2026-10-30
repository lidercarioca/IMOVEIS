<?php

/**
 * API para marcar anomalias como resolvidas
 * PUT /api/v1/debug/resolveAnomaly.php
 * 
 * Requisitos de Segurança:
 * - Apenas administradores
 * - CSRF token válido
 * - JSON body com anomaly_id
 */

require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/security/Logger.php';
require_once __DIR__ . '/../../../app/utils/logger_functions.php';
require_once __DIR__ . '/../../../app/security/Security.php';
require_once __DIR__ . '/../../../app/helpers/tenant_isolation_helpers.php';

// Authentication: Bearer-first via central middleware. Fallback para sessão somente se sem Bearer.
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$hasBearer = is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
$authContext = \App\Auth\AuthContext::getInstance();
$resolvedUserId = null;
$isAdmin = false;

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

    if (!$authContext->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    $resolvedUserId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
} else {
    checkAuth();

    if (!$authContext->isAuthenticated() && !empty($_SESSION['user_id'] ?? '')) {
        $authContext->authenticate((int)$_SESSION['user_id'], $_SESSION['role'] ?? 'user');
    }

    $resolvedUserId = $authContext->getUserId();
    if ($resolvedUserId === null && !empty($_SESSION['user_id'] ?? '')) {
        $resolvedUserId = (int)$_SESSION['user_id'];
    }

    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
    if (!$isAdmin && !empty($_SESSION['role'])) {
        $sessionRole = strtolower(trim((string)$_SESSION['role']));
        $isAdmin = in_array($sessionRole, ['admin', 'administrator', 'superadmin'], true);
    }
}

header('Content-Type: application/json; charset=utf-8');

initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

// Apenas admins podem resolver anomalias
if (!$isAdmin) {
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

    if (!isset($input['anomaly_id'])) {
        throw new Exception('ID de anomalia não fornecido');
    }

    $anomalyId = (int) $input['anomaly_id'];

    if ($anomalyId <= 0) {
        throw new Exception('ID de anomalia inválido');
    }

    $service = new \App\Service\SecurityMonitoringService($pdo);
    $anomaly = $service->resolveAnomaly((int) $tenantId, $anomalyId, (int) $resolvedUserId);

    // Log de auditoria
    log_user_action('anomaly_resolved', [
        'anomaly_id' => $anomalyId,
        'anomaly_type' => $anomaly['anomaly_type'] ?? null,
        'severity' => $anomaly['severity'] ?? null,
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Anomalia marcada como resolvida'
    ]);
} catch (Exception $e) {
    error_log('Erro ao resolver anomalia: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação']);
}
