<?php

/**
 * API para obter dados do painel de monitoramento de segurança
 * GET /api/v1/debug/getSecurityMonitoring.php
 *
 * Requisitos de Segurança:
 * - Apenas administradores
 * - Protegido contra acesso direto
 * - Isolado por tenant
 */

require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/security/TenantContext.php';
require_once __DIR__ . '/../../../app/helpers/tenant_isolation_helpers.php';

require_once __DIR__ . '/../../../vendor/autoload.php';

// Resolver autenticação: preferir JWT via AuthMiddleware quando Authorization header presente
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
} else {
    // Fallback para sessão (compatibilidade legado)
    checkAuth();
}

// Inicializar tenant context (mantendo proteção existente)
initializeTenantMiddleware($GLOBALS['pdo']);
$tenantId = getCurrentTenantId();

// Autorização administrativa derivada do AuthContext
$isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
// Apenas admins podem acessar dados de monitoramento (silenciosamente retorna vazio)
if (!$isAdmin) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => [], 'message' => 'Nenhum dado disponível']);
    exit;
}

// Valida método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($tenantId) || (int)$tenantId <= 0) {
        throw new Exception('Tenant não identificado');
    }

    $service = new \App\Service\SecurityMonitoringService($pdo);
    $data = $service->getOverview((int)$tenantId);

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Erro em getSecurityMonitoring: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor'], JSON_UNESCAPED_UNICODE);
}
