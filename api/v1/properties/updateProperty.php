<?php

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Observability/RequestTracker.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/JwtService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthorizationService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Auth/AuthMiddleware.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Middleware/AuthorizationMiddleware.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Service/PropertyService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/logger_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/AsyncJobQueue.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/property_helpers.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/NotificationManager.php';

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

try {

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

    if ($resolved->getSource() === RequestAuthenticationResult::SOURCE_JWT) {
        $userId = (int)($resolved->getUserId() ?? 0);
        $role = $resolved->getRole() ?? \App\Auth\Roles::USER;
        \App\Auth\AuthContext::getInstance()->authenticate($userId, $role);
    } else {
        $sessionRole = $_SESSION['role'] ?? \App\Auth\Roles::USER;
        $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
        \App\Auth\AuthContext::getInstance()->authenticate($sessionUserId, $sessionRole);
    }

    $authzService = new AuthorizationService();
    $authzService->requirePermission(\App\Auth\Permissions::UPDATE_PROPERTY);

    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    $id = InputValidator::getInt($_POST['id'] ?? null, 'ID do imóvel', 1);
    if (!$id) {
        throw new Exception('ID do imóvel não foi informado.');
    }

    $payload = [
        'title' => $_POST['title'] ?? '',
        'price' => $_POST['price'] ?? null,
        'location' => $_POST['location'] ?? '',
        'type' => $_POST['type'] ?? '',
        'transactionType' => $_POST['transactionType'] ?? '',
        'area' => $_POST['area'] ?? null,
        'yearBuilt' => $_POST['yearBuilt'] ?? null,
        'description' => $_POST['description'] ?? '',
        'bedrooms' => $_POST['bedrooms'] ?? null,
        'bathrooms' => $_POST['bathrooms'] ?? null,
        'garage' => $_POST['garage'] ?? null,
        'suites' => $_POST['suites'] ?? null,
        'neighborhood' => $_POST['neighborhood'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'zip' => $_POST['zip'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'sale_commission_percentage' => $_POST['sale_commission_percentage'] ?? null,
        'condominium' => $_POST['condominium'] ?? null,
        'iptu' => $_POST['iptu'] ?? null,
    ];

    if (array_key_exists('assigned_user_id', $_POST)) {
        $payload['assigned_user_id'] = is_numeric($_POST['assigned_user_id'])
            ? (int)$_POST['assigned_user_id']
            : null;
    }

    $features = [];
    if (isset($_POST['features'])) {
        if (is_array($_POST['features'])) {
            $features = $_POST['features'];
        } elseif (is_string($_POST['features'])) {
            $decoded = json_decode($_POST['features'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $features = $decoded;
            } else {
                $features = explode(',', $_POST['features']);
            }
        }
    }
    $payload['features'] = array_values(array_filter(array_map(fn($f) => trim(strip_tags($f)), $features)));

    $service = new PropertyService($pdo);
    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $isExplicitGlobalAdmin = \TenantContext::isSuperAdmin() || (!empty($_SESSION['is_superadmin']));

    $updated = $service->updateProperty($id, $payload, $tenantId, $userId, $isExplicitGlobalAdmin);
    if (!$updated) {
        throw new Exception('Nenhuma alteração foi aplicada.');
    }

    $savedImages = [];
    if (!empty($_FILES['imagens'])) {
        $savedImages = uploadPropertyImages($id, $_FILES['imagens']);
        if (!empty($savedImages)) {
            $service->savePropertyImages($id, $savedImages);
        }
    }

    if (!empty($payload['status'])) {
        try {
            $notificationData = [
                'property_id' => $id,
                'title' => $payload['title'],
                'status' => $payload['status'],
                'price' => $payload['price'],
            ];
            $queue = new AsyncJobQueue($pdo);
            $queue->enqueue('send_notifications', $notificationData, 'normal');
        } catch (Exception $e) {
            error_log('Erro ao enfileirar notificacoes: ' . $e->getMessage());
        }
    }

    if ($payload['assigned_user_id'] !== null) {
        try {
            $notificationManager = new NotificationManager($pdo, $tenantId);
            $notificationManager->notifyPropertyAssigned(
                $id,
                $payload['title'],
                $payload['assigned_user_id'],
                $payload['location'],
                $payload['price']
            );
        } catch (Exception $e) {
            error_log('Erro ao notificar atribuição de imóvel: ' . $e->getMessage());
        }
    }

    log_user_action('property_update', [
        'property_id' => $id,
        'title' => $payload['title'],
    ]);

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Imóvel atualizado com sucesso.',
        'id' => $id,
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

function uploadPropertyImages(int $propertyId, array $files): array
{
    $saved = [];
    if (empty($files['name'])) {
        return $saved;
    }

    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'imagens' . DIRECTORY_SEPARATOR . $propertyId . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new Exception('Não foi possível criar diretório de upload de imagens.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $count = is_array($files['name']) ? count($files['name']) : 1;

    for ($index = 0; $index < $count; $index++) {
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$index] : $files['tmp_name'];
        $name = is_array($files['name']) ? $files['name'][$index] : $files['name'];
        $error = is_array($files['error']) ? $files['error'][$index] : $files['error'];

        if ($error !== UPLOAD_ERR_OK || empty($tmpName) || !is_uploaded_file($tmpName)) {
            continue;
        }

        $mimeType = finfo_file($finfo, $tmpName);
        if (!isset($allowedTypes[$mimeType])) {
            continue;
        }

        $filename = uniqid('img_', true) . '.' . $allowedTypes[$mimeType];
        $destination = $uploadDir . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            continue;
        }

        $saved[] = $filename;
    }

    finfo_close($finfo);

    return $saved;
}
