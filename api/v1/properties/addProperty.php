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
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/NotificationManager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/EmailNotificationService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/NotificationHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

function podeCriarImovel($tenantId)
{
    global $pdo;

    try {
        $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
        $tenant = $tenantRepo->getTenantData((int)$tenantId);
        $status = $tenantRepo->checkTenantActiveStatus((int)$tenantId);

        if (!$tenant || !$status['ativo']) {
            return ['pode' => false, 'motivo' => 'Tenant não está ativo: ' . ($status['motivo'] ?? 'Status inválido'), 'limite' => null, 'total' => 0];
        }

        $plan = $tenantRepo->getPlanByCode((string)($tenant['plano'] ?? ''));
        $limiteImoveis = $plan && isset($plan['limite_imoveis']) ? (int)$plan['limite_imoveis'] : null;
        $totalImoveis = (new \App\Service\PropertyService($pdo))->getPropertyCountForTenant((int)$tenantId);

        if ($limiteImoveis !== null && $totalImoveis >= $limiteImoveis) {
            return ['pode' => false, 'motivo' => "Limite de imóveis do plano atingido ({$totalImoveis}/{$limiteImoveis}). Atualize o plano para cadastrar mais imóveis.", 'limite' => $limiteImoveis, 'total' => $totalImoveis];
        }

        return ['pode' => true, 'motivo' => 'OK', 'limite' => $limiteImoveis, 'total' => $totalImoveis];
    } catch (Exception $e) {
        error_log('Erro ao validar limite de imóveis: ' . $e->getMessage());
        return ['pode' => false, 'motivo' => 'Erro ao validar limite de imóveis', 'limite' => null, 'total' => 0];
    }
}

use App\Auth\JwtService;
use App\Auth\AuthorizationService;
use App\Auth\AuthMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Observability\RequestTracker;
use App\Service\PropertyService;
use App\Infrastructure\User\UserRepository;

// RequestTracker::start($_SERVER['HTTP_X_REQUEST_ID'] ?? null); // Removido - não existe
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Middleware de autenticação JWT
$jwtService = new JwtService();
$authMiddleware = new AuthMiddleware($jwtService, null);

$request = [
    'path' => $_SERVER['REQUEST_URI'],
    'headers' => getallheaders(),
    'method' => $_SERVER['REQUEST_METHOD'],
];

try {
    // Executar middleware de auth
    $authResult = $authMiddleware->handle($request, function ($req) {
        return ['status' => 200, 'continue' => true];
    });

    if ($authResult['status'] !== 200) {
        http_response_code($authResult['status']);
        echo json_encode(['success' => false, 'error' => $authResult['error'] ?? 'Não autorizado']);
        exit;
    }

    // Middleware de autorização
    $authzService = new AuthorizationService();
    $authzService->requirePermission(\App\Auth\Permissions::CREATE_PROPERTY);

    // Verificar tenant
    initializeTenantMiddleware($pdo);
    $tenant_id = getCurrentTenantId();
    $currentUserId = \App\Auth\AuthContext::getInstance()->getUserId();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    $tenant_id = getCurrentTenantId();
    $currentUserId = $_SESSION['user_id'] ?? null;

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
        'assigned_user_id' => isset($_POST['assigned_user_id']) && is_numeric($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null,
    ];

    if (trim($payload['zip']) === '') {
        throw new Exception('CEP é obrigatório.');
    }

    // Aplicar regra de negócio: usuários comuns sempre atribuem a si mesmos
    $userRepository = new UserRepository($pdo);
    $userRole = $userRepository->getRole($currentUserId, $tenant_id);
    $userRole = $userRole !== null ? strtolower(trim($userRole)) : '';
    $isAdmin = in_array($userRole, ['admin', 'administrator', 'superadmin']);

    if (!$isAdmin) {
        // Usuário comum sempre cadastra para si mesmo
        $payload['assigned_user_id'] = $currentUserId;
        // Usuário comum sempre grava como 'pending', ignorando valor do frontend
        $payload['status'] = 'pending';
    } else {
        // Admin pode escolher ou deixar vazio
        if ($payload['assigned_user_id']) {
            // Se admin informou um usuário, validar se existe no tenant
            canAccessResource('users', $payload['assigned_user_id']);
        }
        // Se não informou, deixa como NULL (permitido)
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

    $limitCheck = podeCriarImovel($tenant_id);
    if (!$limitCheck['pode']) {
        throw new Exception($limitCheck['motivo']);
    }

    $service = new \App\Service\PropertyService($pdo);
    $propertyId = $service->createProperty($payload, $tenant_id, $currentUserId);

    $savedImages = [];
    if (!empty($_FILES['imagens'])) {
        $savedImages = uploadPropertyImages($propertyId, $_FILES['imagens']);
        $service->savePropertyImages($propertyId, $savedImages);
    }

    try {
        $notificationManager = new NotificationManager($pdo, $tenant_id);
        $notificationManager->notifyNewProperty($propertyId, $payload['title']);

        if ($payload['assigned_user_id']) {
            $notificationManager->notifyPropertyAssigned(
                $propertyId,
                $payload['title'],
                $payload['assigned_user_id'],
                $payload['location'],
                $payload['price']
            );
        }

        $emailService = new EmailNotificationService($pdo, $tenant_id);
        $emailService->notifyNewProperty($payload['title'], $payload['price']);
    } catch (Exception $e) {
        error_log('Erro ao enviar notificação de novo imóvel: ' . $e->getMessage());
    }

    log_user_action('property_create', [
        'property_id' => $propertyId,
        'title' => $payload['title']
    ]);

    $response = [
        'success' => true,
        'message' => 'Imóvel cadastrado com sucesso.',
        'id' => $propertyId,
    ];

    if (!empty($savedImages) && count($savedImages) !== count($_FILES['imagens']['tmp_name'] ?? [])) {
        $response['message'] .= ' Atenção: algumas imagens não foram enviadas corretamente.';
    }

    echo json_encode($response);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
    exit;
}

/**
 * Upload de imagens de propriedade e retorno de nomes persistidos.
 *
 * @param int $propertyId
 * @param array $files
 * @return array
 */
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
