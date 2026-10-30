<?php

use App\Auth\AuthenticationPolicy;
use App\Auth\JwtService;
use App\Auth\AuthorizationService;
use App\Auth\AuthMiddleware;
use App\Auth\RequestAuthenticationResult;
use App\Services\AlertConfigService;

/**
 * GET /api/v1/alertas/getAlertConfigs.php
 *
 * Obtém configurações de alertas do usuário logado
 * Retorna lista de tipos de alerta com valores atuais
 *
 * Compatibilidade: JWT + Sessão legada
 * Autorização: VIEW_PROPERTY (compatibilidade com painel)
 */

// =====================================================
// BOOTSTRAP E DEPENDÊNCIAS
// =====================================================

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/JwtService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthorizationService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthMiddleware.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Services/AlertConfigService.php';

try {
    error_log("[ALERT_CONFIGS] Iniciando carregamento de configurações de alertas");

    $userId = null;
    $isAdmin = false;
    $tenantId = 0;

    $headers = getallheaders();
    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? '';

    $hasBearerHeader = !empty($authHeader) && preg_match('/^Bearer\s+.+$/i', trim($authHeader));

    $jwtResult = new RequestAuthenticationResult(
        RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
        RequestAuthenticationResult::SOURCE_NONE
    );

    $sessionResult = function_exists('buildSessionAuthenticationResult')
        ? buildSessionAuthenticationResult()
        : new RequestAuthenticationResult(
            RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            RequestAuthenticationResult::SOURCE_NONE
        );

    if ($hasBearerHeader) {
        error_log("[ALERT_CONFIGS] Usando autenticação JWT");
        $jwtService = new JwtService();
        $authMiddleware = new AuthMiddleware($jwtService, null);

        $request = [
            'path' => $_SERVER['REQUEST_URI'] ?? '',
            'headers' => $headers,
            'query' => $_GET,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ];

        $authResult = $authMiddleware->handle($request, function ($req) {
            return ['status' => 200, 'continue' => true];
        });

        $jwtResult = $authResult['authentication_result'] ?? $jwtResult;
    }

    $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, $hasBearerHeader);

    if (!$resolved->isAuthenticated()) {
        error_log("[ALERT_CONFIGS] Autenticação falhou: " . ($resolved->getErrorCode() ?? 'desconhecido'));
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $resolved->getStatus() === RequestAuthenticationResult::STATUS_JWT_EXPIRED ? 'Token expirado' : ($resolved->getStatus() === RequestAuthenticationResult::STATUS_JWT_REVOKED ? 'Token revogado' : ($resolved->getStatus() === RequestAuthenticationResult::STATUS_JWT_INVALID ? 'Token inválido' : 'Não autorizado')),
            'error_code' => 'ALERT_CONFIG_NOT_AUTHENTICATED'
        ]);
        exit;
    }

    if ($resolved->getSource() === RequestAuthenticationResult::SOURCE_JWT) {
        $userId = (int)($resolved->getUserId() ?? 0);
        $role = $resolved->getRole() ?? \App\Auth\Roles::USER;
        \App\Auth\AuthContext::getInstance()->authenticate($userId, $role);
    } else {
        $sessionRole = $_SESSION['role'] ?? \App\Auth\Roles::USER;
        $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
        $userId = $sessionUserId;
        \App\Auth\AuthContext::getInstance()->authenticate($sessionUserId, $sessionRole);
    }

    try {
        $authzService = new AuthorizationService();
        $authzService->requirePermission(\App\Auth\Permissions::VIEW_PROPERTY);
        error_log("[ALERT_CONFIGS] Autorização concedida");
    } catch (\RuntimeException $e) {
        error_log("[ALERT_CONFIGS] Autorização negada: " . $e->getMessage());
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => 'ALERT_CONFIG_ACCESS_DENIED'
        ]);
        exit;
    }

    try {
        initializeTenantMiddleware($pdo);
        $tenantId = (int) getRequestTenantId();
        if ($tenantId <= 0) {
            throw new RuntimeException('Tenant não identificado');
        }
        error_log("[ALERT_CONFIGS] Tenant middleware inicializado");
    } catch (Throwable $e) {
        error_log("[ALERT_CONFIGS] Erro no tenant middleware: " . $e->getMessage());
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Tenant não identificado',
            'error_code' => 'ALERT_CONFIG_TENANT_NOT_RESOLVED'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    try {
        error_log("[ALERT_CONFIGS] Carregando AlertConfigService");

        $service = new AlertConfigService($pdo);
        $configs = $service->getUserConfigs($tenantId, $userId);
        $alertTypes = AlertConfigService::getAlertTypes();
        $defaults = AlertConfigService::getDefaultThresholds();

        $configsByType = [];
        foreach ($configs as $config) {
            $configsByType[$config['alert_type']] = $config;
        }

        $enriched = [];
        foreach ($alertTypes as $alertKey => $alertInfo) {
            $config = $configsByType[$alertKey] ?? null;

            $enriched[] = [
                'id' => $config['id'] ?? null,
                'alert_type' => $alertKey,
                'nome' => $alertInfo['nome'],
                'descricao' => $alertInfo['descricao'],
                'unidade' => $alertInfo['unidade'],
                'threshold' => (int)($config['threshold'] ?? $defaults[$alertKey] ?? 0),
                'enabled' => (int)($config['enabled'] ?? 1),
                'default_threshold' => $defaults[$alertKey] ?? 0,
            ];
        }

        error_log("[ALERT_CONFIGS] Service retornou " . count($enriched) . " configurações enriquecidas");

        echo json_encode([
            'success' => true,
            'data' => $enriched
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        error_log("[ALERT_CONFIGS] Resposta enviada com sucesso");
    } catch (Exception $e) {
        error_log("[ALERT_CONFIGS] Erro no service layer: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao carregar configurações de alertas',
            'error_code' => 'ALERT_CONFIG_SERVICE_ERROR'
        ]);
    }
} catch (Throwable $e) {
    error_log("[ALERT_CONFIGS] ERRO FATAL: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("[ALERT_CONFIGS] Stack Trace: " . $e->getTraceAsString());

    http_response_code(500);

    $response = [
        'success' => false,
        'message' => 'Erro interno ao carregar configurações de alertas',
        'error_code' => 'ALERT_CONFIG_INTERNAL_ERROR'
    ];

    if (getenv('ENVIRONMENT') === 'development' && getenv('DEBUG_MODE') === '1') {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
            'class' => get_class($e)
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
