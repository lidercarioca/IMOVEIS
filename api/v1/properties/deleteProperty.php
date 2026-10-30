<?php

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Observability/RequestTracker.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/JwtService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthorizationService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthMiddleware.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Middleware/AuthorizationMiddleware.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Service/PropertyService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/logger_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';

use App\Auth\AuthenticationPolicy;
use App\Auth\JwtService;
use App\Auth\AuthorizationService;
use App\Auth\AuthMiddleware;
use App\Auth\RequestAuthenticationResult;
use App\Middleware\AuthorizationMiddleware;
use App\Observability\RequestTracker;
use App\Service\PropertyService;

// RequestTracker::start($_SERVER['HTTP_X_REQUEST_ID'] ?? null); // Removido - não existe
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$headers = getallheaders();
$authHeader = $headers['Authorization']
    ?? $headers['authorization']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? '';
$hasBearerHeader = !empty($authHeader) && preg_match('/^Bearer\s+.+$/i', trim($authHeader));

try {
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
            'method' => $_SERVER['REQUEST_METHOD'],
        ];

        $authResult = $authMiddleware->handle($request, function ($req) {
            return ['status' => 200, 'continue' => true];
        });

        $jwtResult = $authResult['authentication_result'] ?? $jwtResult;
    }

    $resolvedResult = AuthenticationPolicy::decide($jwtResult, $sessionResult, $hasBearerHeader);

    if (!$resolvedResult->isAuthenticated()) {
        $status = 401;
        $error = 'Não autorizado';

        if ($resolvedResult->getStatus() === RequestAuthenticationResult::STATUS_JWT_INVALID) {
            $error = 'Token inválido';
        } elseif ($resolvedResult->getStatus() === RequestAuthenticationResult::STATUS_JWT_EXPIRED) {
            $error = 'Token expirado';
        } elseif ($resolvedResult->getStatus() === RequestAuthenticationResult::STATUS_JWT_REVOKED) {
            $error = 'Token revogado';
        } elseif ($resolvedResult->getStatus() === RequestAuthenticationResult::STATUS_MALFORMED_CREDENTIAL) {
            $error = 'Token malformado';
        } elseif ($resolvedResult->getStatus() === RequestAuthenticationResult::STATUS_AUTHENTICATION_ERROR) {
            $error = 'Sessão expirada';
        }

        http_response_code($status);
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }

    if ($resolvedResult->getSource() === RequestAuthenticationResult::SOURCE_JWT) {
        $userId = (int)($resolvedResult->getUserId() ?? 0);
        $role = $resolvedResult->getRole() ?? \App\Auth\Roles::USER;
        \App\Auth\AuthContext::getInstance()->authenticate($userId, $role);
    } else {
        $sessionRole = $_SESSION['role'] ?? \App\Auth\Roles::USER;
        $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
        \App\Auth\AuthContext::getInstance()->authenticate($sessionUserId, $sessionRole);
    }

    // Middleware de autorização
    $authzService = new AuthorizationService();
    $authzService->requirePermission(\App\Auth\Permissions::DELETE_PROPERTY);

    // Verificar tenant
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// ===============================
// FUNÇÃO PARA APAGAR PASTA RECURSIVAMENTE
// ===============================
function deleteFolderRecursive($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                deleteFolderRecursive($path);
            } else {
                @unlink($path);
            }
        }
    }
    @rmdir($dir);
}

// ===============================
// VALIDAÇÕES
// ===============================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Método não permitido."
    ]);
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID inválido."
    ]);
    exit;
}

$propertyId = (int) $_POST['id'];

try {
    $service = new PropertyService($pdo);
    $authContext = \App\Auth\AuthContext::getInstance();
    $isExplicitGlobalAdmin = \TenantContext::isSuperAdmin() || (!empty($_SESSION['is_superadmin']));
    $deleted = $service->deleteProperty(
        $propertyId,
        $tenantId,
        $authContext->getUserId(),
        $isExplicitGlobalAdmin
    );
    if (!$deleted) {
        echo json_encode([
            "success" => false,
            "message" => "Imóvel não encontrado ou já removido."
        ]);
        exit;
    }

    // ===============================
    // REMOVE PASTA DE IMAGENS (FORA DA TRANSAÇÃO)
    // ===============================
    $imagesFolder = $_SERVER['DOCUMENT_ROOT'] . "/assets/imagens/$propertyId";
    deleteFolderRecursive($imagesFolder);

    // Log da ação
    log_user_action('property_delete', [
        'property_id' => $propertyId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Imóvel e imagens excluídos com sucesso."
    ]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao excluir imóvel.",
        "error" => $e->getMessage()
    ]);
    exit;
}
