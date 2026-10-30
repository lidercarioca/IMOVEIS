<?php

/**
 * API: Listagem de Propriedades com Isolamento de Tenant
 */

header('Content-Type: application/json; charset=utf-8');

// Garante que a sessão está ativa, sem gerar notice
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/JwtService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthorizationService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthMiddleware.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Middleware/AuthorizationMiddleware.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Service/PropertyService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/property_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Domain/DTO/PropertyDTO.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Repository/PropertyRepository.php';

use App\Auth\AuthenticationPolicy;
use App\Auth\JwtService;
use App\Auth\AuthorizationService;
use App\Auth\AuthMiddleware;
use App\Auth\RequestAuthenticationResult;
use App\Service\PropertyService;

// RequestTracker::start($_SERVER['HTTP_X_REQUEST_ID'] ?? null); // Removido - não existe

try {

    // =====================================================
    // DETECÇÃO DE TIPO DE REQUISIÇÃO
    // =====================================================
    $isPanel = isset($_GET['panel']) && $_GET['panel'] == '1';

    $userId  = null;
    $isAdmin = false;
    $tenantId = 0;

    // =====================================================
    // AUTENTICAÇÃO (PAINEL)
    // =====================================================
    if ($isPanel) {

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
            $jwtService = new JwtService();
            $authMiddleware = new AuthMiddleware($jwtService, null);

            $request = [
                'path' => $_SERVER['REQUEST_URI'],
                'headers' => $headers,
                'query' => $_GET,
                'method' => $_SERVER['REQUEST_METHOD'],
            ];

            $authResult = $authMiddleware->handle($request, function ($req) {
                return ['status' => 200, 'continue' => true];
            });

            $jwtResult = $authResult['authentication_result'] ?? $jwtResult;
        }

        $resolved = AuthenticationPolicy::decide($jwtResult, $sessionResult, $hasBearerHeader);

        if (!$resolved->isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $resolved->getStatus() === RequestAuthenticationResult::STATUS_JWT_EXPIRED ? 'Token expirado' : ($resolved->getStatus() === RequestAuthenticationResult::STATUS_JWT_REVOKED ? 'Token revogado' : ($resolved->getStatus() === RequestAuthenticationResult::STATUS_JWT_INVALID ? 'Token inválido' : 'Não autorizado'))
            ]);
            exit;
        }

        $authContext = \App\Auth\AuthContext::getInstance();

        if ($resolved->getSource() === RequestAuthenticationResult::SOURCE_JWT) {
            $userId = (int)($resolved->getUserId() ?? 0);
            $role = $resolved->getRole() ?? \App\Auth\Roles::USER;
            $authContext->authenticate($userId, $role);
        } else {
            $sessionRole = $_SESSION['role'] ?? \App\Auth\Roles::USER;
            $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
            $authContext->authenticate($sessionUserId, $sessionRole);
        }

        // =================================================
        // AUTORIZAÇÃO
        // =================================================
        try {
            $authzService = new AuthorizationService();
            $authzService->requirePermission(\App\Auth\Permissions::VIEW_PROPERTY);
        } catch (\RuntimeException $e) {
            // Permissão negada
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }

        initializeTenantMiddleware($pdo);

        $userId = $authContext->getUserId();
        $isAdmin = $authContext->hasRole(\App\Auth\Roles::ADMIN);
        $tenantId = (int) getCurrentTenantId();

        if ($tenantId <= 0) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Tenant não resolvido para esta sessão.'
            ]);
            exit;
        }
    }

    // =====================================================
    // REQUISIÇÃO PÚBLICA
    // =====================================================
    else {
        $tenantId = (int) getRequestTenantId();

        if ($tenantId <= 0) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Tenant não resolvido para esta requisição pública.'
            ]);
            exit;
        }
    }

    // =====================================================
    // BUSCA DE DADOS
    // =====================================================
    $service = new PropertyService($pdo);

    $options = [
        'isPanel'  => $isPanel,
        'tenantId' => $tenantId,
        'userId'   => $userId,
        'isAdmin'  => $isAdmin
    ];

    $properties = $service->getProperties($options);

    echo json_encode([
        'success' => true,
        'data' => $properties
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {

    error_log("getProperties ERROR: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    error_log("Stack Trace: " . $e->getTraceAsString());

    http_response_code(500);

    // Resposta de erro padronizada
    $response = [
        'success' => false,
        'error' => getenv('ENVIRONMENT') === 'development' ? $e->getMessage() : 'Erro ao processar requisição'
    ];

    // Em development, adicionar debug info
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
